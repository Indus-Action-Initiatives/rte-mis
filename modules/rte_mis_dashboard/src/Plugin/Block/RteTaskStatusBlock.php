<?php

namespace Drupal\rte_mis_dashboard\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rte_mis_report\Services\RteReportHelper;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'RTE Dashboard Stats' Block.
 *
 * @Block(
 *   id = "rte_task_status_block",
 *   admin_label = @Translation("RTE Task Status Block"),
 *   category = @Translation("RTE MIS Dashboard"),
 * )
 */
class RteTaskStatusBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The rte mis helper service.
   *
   * @var \Drupal\rte_mis_report\Services\RteReportHelper
   */
  protected $rteReportHelper;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new RteTaskStatusBlock instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user,
    RteReportHelper $rte_report_helper,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->rteReportHelper = $rte_report_helper;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('rte_mis_report.report_helper'),
    );
  }

  /**
   * Gets the count of active users with the "district_admin" role.
   */
  public function getDistrictCount() {
    $query = $this->entityTypeManager->getStorage('user')
      ->getQuery()
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->condition('roles', 'district_admin');
    $uids = $query->execute();

    return count($uids);
  }

  /**
   * Gets the school_admins based on the user's role and location.
   */
  public function getSchoolList(?string $locationId = NULL) {
    /** @var \Drupal\taxonomy\TermStorage $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $location_tree = $term_storage->loadTree('location', $locationId, NULL, TRUE);
    $locations = [];
    $schools = [];

    if ($location_tree) {
      foreach ($location_tree as $value) {
        $locations[] = $value->id();
      }
    }

    // Query to count active user with same location id &
    // `school_admin` user role.
    if (!empty($locations)) {
      $query = $this->entityTypeManager->getStorage('user')
        ->getQuery()
        ->condition('roles', 'school_admin')
        ->condition('status', 1)
        ->condition('field_school_details.entity:mini_node.field_location', $locations, 'IN')
        ->condition('field_school_details.entity:mini_node.field_school_verification', 'school_registration_verification_approved_by_deo')
        ->accessCheck(FALSE);
      $schools = $query->execute();
      // Return an array of all the schools under a location id.
      return $schools;
    }

    return [];
  }

  /**
   * Retrieves the count of students based on role, location, and status.
   */
  public function studentDetails($current_role, ?string $id = NULL, $status = NULL): int {
    $status_map = [
      'applied'    => 'student_workflow_submitted',
      'duplicate'  => 'student_workflow_duplicate',
      'incomplete' => 'student_workflow_incomplete',
      'rejected'   => 'student_workflow_rejected',
      'approved'   => 'student_workflow_approved',
    ];

    // For state, district, and app admins.
    if (array_intersect(['app_admin', 'state_admin', 'district_admin'], (array) $current_role)) {
      /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $location_tree = $term_storage->loadTree('location', $id, NULL, TRUE);

      $location_ids = array_map(static fn($term) => $term->id(), $location_tree);

      if ($location_ids) {
        $query = $this->entityTypeManager->getStorage('mini_node')
          ->getQuery()
          ->condition('type', 'student_details')
          ->condition('field_location', $location_ids, 'IN')
          ->accessCheck(FALSE);

        if (!empty($status) && isset($status_map[$status])) {
          $query->condition('field_student_verification', $status_map[$status]);
        }

        $students = $query->execute();
        return is_array($students) ? count($students) : 0;
      }
    }

    // For block admin.
    if ($current_role === 'block_admin') {
      $query = $this->entityTypeManager->getStorage('mini_node')
        ->getQuery()
        ->condition('type', 'student_details')
        ->accessCheck(FALSE);

      if (!empty($status) && isset($status_map[$status])) {
        $query->condition('field_student_verification', $status_map[$status]);
      }

      $students = $query->execute();
      $count = [];

      foreach ($students as $student_id) {
        $student = $this->entityTypeManager->getStorage('mini_node')->load($student_id);
        if (!$student) {
          continue;
        }

        // Check if the student's preferences include the given school ID.
        foreach ($student->get('field_school_preferences')->referencedEntities() as $preference) {
          $schools = $preference->get('field_school_id')->referencedEntities();
          if ($schools && reset($schools)->id() === $id) {
            $count[] = $student_id;
            break;
          }
        }
      }
      return count($count);
    }
    return 0;

  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $roles = $this->currentUser->getRoles();

    // Get district-wise stats.
    if (array_intersect(['app_admin', 'state_admin'], $roles)) {
      $taskStats = $this->getStateAdminContent();
    }
    elseif (array_intersect(['district_admin'], $roles)) {
      $taskStats = $this->getDistrictAdminContent();
    }
    elseif (array_intersect(['block_admin'], $roles)) {
      $taskStats = $this->getBlockAdminContent();
    }

    // Define table header.
    $header = [
      $this->t('Task Type'),
      $this->t('Total'),
      $this->t('Completed'),
      $this->t('Pending'),
    ];

    // Build table rows from array.
    $rows = [];
    foreach ($taskStats as $task) {
      $rows[] = [
        'data' => [
          $task['task'],
          $task['total'],
          $task['completed'],
          $task['pending'],
        ],
      ];
    }

    // Build render array for table.
    $build = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => [
        'class' => ['rte-task-status-table', 'table', 'table-striped', 'table-bordered'],
      ],
    ];

    // Optional: attach CSS library for styling.
    $build['#attached']['library'][] = 'rte_mis_gin/rte_mis_dashboard';

    return $build;
  }

  /**
   * Collects State-admin stats.
   */
  protected function getStateAdminContent(): array {
    // District users creation stats.
    /** @var \Drupal\taxonomy\TermStorage $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $districts = $term_storage->loadTree('location', 0, 1, TRUE);
    $totalDistrict = !empty($districts) ? count($districts) : 0;
    $districtRegistered = (int) $this->getDistrictCount();
    $pendingDistrict = $totalDistrict - $districtRegistered;
    $pendingDistrict = max(0, $pendingDistrict);

    $districtStats = [
      'task' => 'District Users Creation',
      'total' => $totalDistrict,
      'completed' => $districtRegistered,
      'pending' => $pendingDistrict,
      'year' => '2025–26',
    ];

    // School Mapping stats.
    $approved_school = 0;
    $mapping_completed = 0;
    $mapping_pending = 0;
    if (!empty($districts)) {
      foreach ($districts as $district) {
        $location_ids = $this->rteReportHelper->getLocationsForParent('state_admin', $district->id());
        // School Mapping stats.
        $approved_school += count($this->rteReportHelper->getRegisteredSchoolList($district->id(), 'approved'));
        $mapping_completed += count($this->rteReportHelper->mappingStatus($district->id(), TRUE));
        $mapping_pending += count($this->rteReportHelper->mappingStatus($district->id()));
        // Reimbursement Claims stats.
        $claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids));
        $reimbursed_claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_payment_completed'));
        $pending_claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_payment_pending'));
      }
    }

    $taskStats = [];

    // Add District stats.
    $taskStats[] = $districtStats;

    // Add next task (for example School Mapping).
    $taskStats[] = [
      'task' => 'Neighbourhood Mapping',
      'total' => $approved_school,
      'completed' => $mapping_completed,
      'pending' => $mapping_pending,
      'year' => '2025–26',
    ];

    // Add Reimbursement Claims stats.
    $taskStats[] = [
      'task' => 'Reimbursement Claims',
      'total' => $claims_count,
      'completed' => $reimbursed_claims_count,
      'pending' => $pending_claims_count,
      'year' => '2025–26',
    ];

    return $taskStats;
  }

  /**
   * Get content for district admin.
   */
  protected function getDistrictAdminContent() {
    $currentUserId = $this->currentUser->id();
    /** @var \Drupal\user\Entity\User */
    $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);
    if ($currentUser instanceof UserInterface) {
      // Get location ID from user field.
      $locationId = $currentUser->get('field_location_details')->getString() ?? NULL;
    }
    /** @var \Drupal\taxonomy\TermStorage $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $blocks = $term_storage->loadTree('location', $locationId, 1, TRUE) ?? NULL;
    $location_ids = $this->rteReportHelper->getLocationsForParent('district_admin', $locationId);
    // Reimbursement Claims stats.
    $claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids));
    $reimbursed_claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_payment_completed'));
    $pending_claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_payment_pending'));

    if ($blocks) {
      $total_blocks = 0;
      $block_admin_counts = 0;

      foreach ($blocks as $block) {
        $total_blocks += $this->rteReportHelper->getBlocksCount($block->id());

        // Use injected entity type manager instead of \Drupal::entityQuery().
        $query = $this->entityTypeManager
          ->getStorage('user')
          ->getQuery()
          ->condition('roles', 'block_admin')
          ->condition('field_location_details', $block->id())
          ->accessCheck(FALSE);
        $user_ids = $query->execute();
        $block_admin_counts += count($user_ids);
      }
      $taskStats = [];
      $taskStats[] = [
        'task' => 'Block Users Creation',
        'total' => $total_blocks,
        'completed' => $block_admin_counts,
        'pending' => max(0, $total_blocks - $block_admin_counts),
      ];

      // Add Reimbursement Claims stats.
      $taskStats[] = [
        'task' => 'Reimbursement Claims',
        'total' => $claims_count,
        'completed' => $reimbursed_claims_count,
        'pending' => $pending_claims_count,
      ];

    }
    return $taskStats;
  }

  /**
   * Get content for block admin.
   */
  protected function getBlockAdminContent() {
    $currentUserId = $this->currentUser->id();

    /** @var \Drupal\user\Entity\User */
    $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);
    $locationId = NULL;
    if ($currentUser instanceof UserInterface) {
      // Get location ID from user field.
      $locationId = $currentUser->get('field_location_details')->getString() ?? NULL;
    }

    if (!empty($locationId)) {
      $totalSchools = count($this->rteReportHelper->getSchoolList($locationId));
      $schools = count($this->getSchoolList($locationId));

    }

    /** @var \Drupal\taxonomy\TermStorage $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $blocks = $term_storage->loadTree('location', $locationId, 1, TRUE) ?? NULL;
    if (!empty($blocks)) {
      foreach ($blocks as $block) {
        $block_id = $block->id();
        $location_ids = $this->rteReportHelper->getLocationsForParent('block_admin', $block_id);

        $claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids));
        $reimbursed_claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_payment_completed'));
      }
    }

    $taskStats = [];
    $taskStats[] = [
      'task' => 'School Registration',
      'total' => $totalSchools,
      'completed' => $schools,
      'pending' => max(0, $totalSchools - $schools),
      'year' => '2025–26',
    ];
    // Add Reimbursement Claims stats.
    $taskStats[] = [
      'task' => 'Reimbursement Claims',
      'total' => $claims_count,
      'completed' => $reimbursed_claims_count,
      'pending' => $claims_count - $reimbursed_claims_count,
      'year' => '2025–26',
    ];
    // Return a markup about missing location.
    return $taskStats;
  }

}
