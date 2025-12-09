<?php

namespace Drupal\rte_mis_dashboard\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rte_mis_report\Services\RteReportHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Leaders Board' Block.
 *
 * @Block(
 *   id = "rte_leaders_board_block",
 *   admin_label = @Translation("RTE Leaders Board"),
 *   category = @Translation("RTE MIS Dashboard"),
 * )
 */
class LeadersBoardBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The report helper service.
   *
   * @var \Drupal\rte_mis_report\Services\RteReportHelper
   */
  protected $reportHelper;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a LeadersBoardBlock instance.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user,
    RteReportHelper $report_helper,
    ConfigFactoryInterface $config_factory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->reportHelper = $report_helper;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $config_factory = $container->get('config.factory');
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('rte_mis_report.report_helper'),
      $config_factory,
    );
  }

  /**
   * Calculates performance percentage.
   *
   * @param int $admissions
   *   Number of student admissions.
   * @param int $seats
   *   Total available seats.
   *
   * @return float
   *   Performance percentage.
   */
  protected function calcPerformance(int $admissions, int $seats): float {
    if ($seats === 0) {
      return 0.0;
    }
    return round(($admissions / $seats) * 100, 2);
  }

  /**
   * Returns top 5 performers sorted by performance.
   *
   * @param array $items
   *   Performance data array.
   *
   * @return array
   *   Top five performers.
   */
  protected function getTopFive(array $items): array {
    usort($items, static function (array $a, array $b): int {

      // 1. Sort by performance DESC
      $cmp = $b['performance'] <=> $a['performance'];
      if ($cmp !== 0) {
        return $cmp;
      }

      // 2. Sort by admissions DESC
      $cmp = $b['admissions'] <=> $a['admissions'];
      if ($cmp !== 0) {
        return $cmp;
      }

      // 3. Sort by seats DESC
      return $b['seats'] <=> $a['seats'];
    });

    return array_slice($items, 0, 5);
  }

  /**
   * Fetches total admissions + total RTE seats.
   */
  private function getTotalAdmissions(array $location_ids): array {
    // Ensure locations exist.
    if (empty($location_ids)) {
      return [0, 0];
    }

    // Load language config safely.
    $school_config = $this->configFactory->get('rte_mis_school.settings');
    $languages = $school_config->get('field_default_options.field_medium') ?? [];

    $schools_list = [];
    $total_rte_seats = 0;

    // STEP 1 — Load schools from locations.
    foreach ($location_ids as $location_id) {
      $schools = $this->entityTypeManager->getStorage('mini_node')
        ->getQuery()
        ->condition('type', 'school_details')
        ->condition('field_location', [$location_id], 'IN')
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($schools)) {
        $schools_list = array_merge($schools_list, $schools);
      }
    }

    if (empty($schools_list)) {
      return [0, 0];
    }

    // STEP 2 — Count admissions for these schools.
    $admissions = $this->entityTypeManager->getStorage('mini_node')
      ->getQuery()
      ->condition('type', 'allocation')
      ->condition('field_student_allocation_status', 'student_admission_workflow_admitted')
      ->condition('field_school', $schools_list, 'IN')
      ->accessCheck(FALSE)
      ->execute();

    // STEP 3 — Calculate RTE seats.
    foreach ($schools_list as $id) {
      $school = $this->entityTypeManager->getStorage('mini_node')->load($id);

      if (!$school) {
        continue;
      }

      // Ensure field exists.
      if (!$school->hasField('field_entry_class')) {
        continue;
      }

      $entry_classes = $school->get('field_entry_class')->referencedEntities();

      foreach ($entry_classes as $entry_class) {
        $class_value = $entry_class->get('field_entry_class')->getString();

        // Loop through all languages.
        foreach ($languages as $key => $lang) {
          $field_name = 'field_rte_student_for_' . $key;

          if ($entry_class->hasField($field_name)) {
            $value = (int) $entry_class->get($field_name)->value;
            $total_rte_seats += $value;
          }
        }
      }
    }

    return [count($admissions), $total_rte_seats];
  }

  /**
   * Fetches top performing districts.
   *
   * @return array
   *   Top districts array.
   */
  protected function getTopDistricts(): array {
    /** @var \Drupal\taxonomy\TermStorage $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $districts = $term_storage->loadTree('location', 0, 1, TRUE);

    $output = [];

    foreach ($districts as $district) {
      $district_id = $district->id();
      $location_tree = $term_storage->loadTree('location', $district_id, NULL, TRUE);
      $location_ids = [];

      foreach ($location_tree as $term) {
        if ($term->id() != $district_id) {
          $location_ids[] = $term->id();
        }
      }

      // $total_seats = $this->reportHelper->getSeatsCount($location_ids);
      $admissions = $this->getTotalAdmissions($location_ids);

      $output[] = [
        'name' => $district->label(),
        'admissions' => $admissions[0],
        'seats' => $admissions[1],
        'performance' => $this->calcPerformance($admissions[0], $admissions[1]),
      ];
    }

    return $this->getTopFive($output);
  }

  /**
   * Fetches top performing blocks.
   *
   * @return array
   *   Top blocks array.
   */
  protected function getTopBlocks(): array {
    /** @var \Drupal\taxonomy\TermStorage $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $districts = $term_storage->loadTree('location', 0, 1, TRUE);
    $currentUser = $this->currentUser;
    $currentUserRoles = $currentUser->getRoles();
    if (in_array('district_admin', $currentUserRoles)) {
      $districts = [];
      $currentUserId = $this->currentUser->id();
      /** @var \Drupal\user\Entity\User */
      $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);
      $tid = $currentUser->get('field_location_details')->getString() ?? NULL;
      $districts[] = $term_storage->load($tid);
    }

    $output = [];
    foreach ($districts as $district) {
      if (!$district) {
        continue;
      }
      $blocks = $term_storage->loadTree('location', $district->id(), 1, TRUE);
      foreach ($blocks as $block) {
        $block_id = $block->id();
        $location_tree = $term_storage->loadTree('location', $block_id, NULL, TRUE);
        $location_ids = [];

        foreach ($location_tree as $term) {
          if ($term->id() != $block_id) {
            $location_ids[] = $term->id();
          }
        }

        $admissions = $this->getTotalAdmissions($location_ids);

        $output[] = [
          'name' => $block->label(),
          'admissions' => $admissions[0],
          'seats' => $admissions[1],
          'performance' => $this->calcPerformance($admissions[0], $admissions[1]),
        ];
      }
    }

    return $this->getTopFive($output);
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $currentUser = $this->currentUser;
    $currentUserRoles = $currentUser->getRoles();
    $districts = [];
    $blocks = [];
    if (array_intersect(['app_admin', 'state_admin'], $currentUserRoles)) {
      $districts = $this->getTopDistricts();
      $blocks = $this->getTopBlocks();
    }
    if (in_array('district_admin', $currentUserRoles)) {
      $blocks = $this->getTopBlocks();
    }
    $district_rows = [];
    foreach ($districts as $record) {
      $district_rows[] = [
        '#markup' => $record['name'],
        '#wrapper_attributes' => [
          'data-performance' => $record['performance'],
          'data-admissions'  => $record['admissions'],
          'data-seats'       => $record['seats'],
          'class'            => ['leader-district-item'],
        ],
      ];
    }

    $block_rows = [];
    foreach ($blocks as $record) {
      $block_rows[] = [
        '#markup' => $record['name'],
        '#wrapper_attributes' => [
          'data-performance' => $record['performance'],
          'data-admissions'  => $record['admissions'],
          'data-seats'       => $record['seats'],
          'class'            => ['leader-block-item'],
        ],
      ];
    }

    $items = [];

    // Add districts if available.
    if (!empty($district_rows)) {
      $items[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['leaders-wrapper', 'district-wrapper']],
        'title' => [
          '#markup' => '<strong>Top Performing Districts</strong>',
        ],
        'list' => [
          '#theme' => 'item_list',
          '#items' => $district_rows,
          '#attributes' => [
            'class' => ['district-list', 'leaders-items'],
          ],
        ],
      ];
    }

    // Add blocks if available.
    if (!empty($block_rows)) {
      $items[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['leaders-wrapper', 'block-wrapper']],
        'title' => [
          '#markup' => '<strong>Top Performing Blocks</strong>',
        ],
        'list' => [
          '#theme' => 'item_list',
          '#items' => $block_rows,
          '#attributes' => [
            'class' => ['block-list', 'leaders-items'],
          ],
        ],
      ];
    }

    // If nothing to show.
    if (empty($items)) {
      return [
        '#markup' => '<div>No performance data available.</div>',
      ];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['leadership-board-wrapper']],
      'title' => [
        '#markup' => '<h5 class="title-text">Leaderboard</h5>',
      ],
      'content' => [
        '#theme' => 'item_list',
        '#items' => $items,
        '#attributes' => [
          'class' => ['blocks-list', 'leaders-block-items'],
        ],
      ],
    ];

    $build['#attached']['library'][] = 'rte_mis_gin/rte_mis_dashboard';
    return $build;
  }

}
