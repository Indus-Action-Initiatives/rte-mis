<?php

namespace Drupal\rte_mis_dashboard\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'RTE Dashboard Stats' Block.
 *
 * @Block(
 *   id = "rte_dashboard_stats_block",
 *   admin_label = @Translation("RTE Dashboard Statistics"),
 *   category = @Translation("RTE MIS Dashboard"),
 * )
 */
class RteDashboardStatsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new RteDashboardStatsBlock instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
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
      $container->get('current_user')
    );
  }

  /**
   * Gets the school_admins based on the user's role and location.
   *
   * @param string $locationId
   *   The location id to get the student details.
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
   * Get the list of applied students.
   *
   * @param array $current_role
   *   The current user role.
   * @param string $id
   *   The location id to get the student details for state & district.
   *   And the mini node id for block admin.
   * @param string $status
   *   The current status of the student application.
   *
   * @return int
   *   The count of total rte seats in a particular district.
   */
  public function studentDetails($current_role, ?string $id = NULL, $status = NULL): int {
    $total_students = 0;
    if (array_intersect(['app_admin', 'state_admin', 'district_admin'], (array) $current_role)) {
      /** @var \Drupal\taxonomy\TermStorage $term_storage */
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $location_tree = $term_storage->loadTree('location', $id, NULL, TRUE);
      $locationIds = [];

      if (!empty($location_tree)) {
        foreach ($location_tree as $value) {
          $locationIds[] = $value->id();
        }
      }

      if ($locationIds) {

        $query = $this->entityTypeManager->getStorage('mini_node')
          ->getQuery()
          ->condition('type', 'student_details')
          ->condition('field_location', $locationIds, 'IN')
          ->accessCheck(FALSE);

        // Add conditions dynamically based on $status.
        $status_map = [
          'applied'    => 'student_workflow_submitted',
          'duplicate'  => 'student_workflow_duplicate',
          'incomplete' => 'student_workflow_incomplete',
          'rejected'   => 'student_workflow_rejected',
          'approved'   => 'student_workflow_approved',
        ];

        if (!empty($status) && isset($status_map[$status])) {
          $query->condition('field_student_verification', $status_map[$status]);
        }

        $students = $query->execute();
        $total_students = is_array($students) ? count($students) : 0;
        return $total_students;
      }
    }
    elseif ($current_role == 'block_admin') {
      // List down the students which have a particular school with a id.
      $query = $this->entityTypeManager->getStorage('mini_node')
        ->getQuery()
        ->condition('type', 'student_details')
        ->accessCheck(FALSE);

      // Add conditions dynamically based on $status.
      $status_map = [
        'applied'    => 'student_workflow_submitted',
        'duplicate'  => 'student_workflow_duplicate',
        'incomplete' => 'student_workflow_incomplete',
        'rejected'   => 'student_workflow_rejected',
        'approved'   => 'student_workflow_approved',
      ];

      if (!empty($status) && isset($status_map[$status])) {
        $query->condition('field_student_verification', $status_map[$status]);
      }
      $students = $query->execute();
      // Total student preferrence for a single school.
      $count = [];
      foreach ($students as $student) {
        $student = $this->entityTypeManager->getStorage('mini_node')->load($student);
        // Each School preference entity.
        $preferences = $student->get('field_school_preferences')->referencedEntities();
        $preferenceSingleStudent = [];
        foreach ($preferences as $value) {
          $referencedSchool = $value->get('field_school_id')->referencedEntities();
          $preferenceSingleStudent[] = reset($referencedSchool)->id();
        }
        // If the student has a preference of current school
        // Add and return it via count.
        if (in_array($id, array_unique($preferenceSingleStudent))) {
          $count[] = $student->id();
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
      $districtStats = $this->getStateAdminContent();
      $totalDistricts = count($districtStats);
      $totalBlocks = array_sum(array_column($districtStats, 'blocks'));
      $totalSchools = array_sum(array_column($districtStats, 'schools'));
      $totalStudents = array_sum(array_map(function ($stats) {
        return $stats['students'];
      }, $districtStats));

    }
    elseif (array_intersect(['district_admin'], $roles)) {
      $districtStats = $this->getDistrictAdminContent();
      $totalBlocks = count($districtStats);
      $totalSchools = array_sum(array_column($districtStats, 'schools'));
      $totalStudents = array_sum(array_map(function ($stats) {
        return $stats['students'];
      }, $districtStats));
    }
    elseif (array_intersect(['block_admin'], $roles)) {
      $districtStats = $this->getBlockAdminContent();
      $totalWards = count($districtStats);
      $totalSchools = array_sum(array_column($districtStats, 'schools'));
      $totalStudents = array_sum(array_column($districtStats, 'students'));
    }

    $build = [];

    // --- Total Summary Cards ---
    $markup = '<div class="rte-summary-cards">';

    if (!empty($totalDistricts)) {
      $markup .= '
        <div class="rte-summary-card">
          <div class="rte-summary-title">Districts</div>
          <div class="rte-summary-value">' . $totalDistricts . '</div>
        </div>';
    }

    if (!empty($totalBlocks)) {
      $markup .= '
        <div class="rte-summary-card">
          <div class="rte-summary-title">Blocks</div>
          <div class="rte-summary-value">' . $totalBlocks . '</div>
        </div>';
    }

    if (!empty($totalWards)) {
      $markup .= '
        <div class="rte-summary-card">
          <div class="rte-summary-title">Wards</div>
          <div class="rte-summary-value">' . $totalWards . '</div>
        </div>';
    }

    if (!empty($totalSchools)) {
      $markup .= '
        <div class="rte-summary-card">
          <div class="rte-summary-title">Schools</div>
          <div class="rte-summary-value">' . $totalSchools . '</div>
        </div>';
    }

    if (!empty($totalStudents)) {
      $markup .= '
        <div class="rte-summary-card">
          <div class="rte-summary-title">Students</div>
          <div class="rte-summary-value">' . $totalStudents . '</div>
        </div>';
    }

    $markup .= '</div>';

    $build['summary_cards'] = [
      '#markup' => $markup,
    ];

    // Attach CSS.
    $build['#attached']['library'][] = 'rte_mis_dashboard/dashboard_cards';

    return $build;
  }

  /**
   * Collects State-admin stats.
   */
  protected function getStateAdminContent(): array {
    $stats = [];
    $roles = $this->currentUser->getRoles();
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    // Load top-level districts (parent = 0).
    $districts = $term_storage->loadByProperties([
      'vid' => 'location',
      'parent' => 0,
    ]);

    foreach ($districts as $district) {
      // Load blocks under district.
      $blocks = $term_storage->loadByProperties([
        'vid' => 'location',
        'parent' => $district->id(),
      ]);
      $block_count = count($blocks);

      // Load schools under each block.
      $ward_count = 0;
      foreach ($blocks as $block) {
        $wards = $term_storage->loadByProperties([
          'vid' => 'location',
          'parent' => $block->id(),
        ]);
        $ward_count += count($wards);
      }
      $school_count = 0;
      $schoolList = $this->getSchoolList($district->id());
      $school_count += count($schoolList);

      $studentDetails = $this->studentDetails($roles, $district->id());
      $stats[$district->label()] = [
        'blocks' => $block_count,
        'wards' => $ward_count,
        'schools' => $school_count,
        'students' => $studentDetails,
      ];
    }

    return $stats;
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

    if (!empty($locationId)) {
      $stats = [];
      /** @var \Drupal\taxonomy\TermStorage $term_storage */
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $blocks = $term_storage->loadTree('location', $locationId, 1, TRUE) ?? NULL;
      if (!empty($blocks)) {
        foreach ($blocks as $block) {
          $block_id = $block->id();
          $school_count = 0;
          $schools = $this->getSchoolList($block_id);
          $school_count += count($schools);
          $total_applications = $this->studentDetails(['district_admin'], $block_id);

          $stats[$block->label()] = [
            'blocks' => $block_id,
            'schools' => $school_count,
            'students' => $total_applications,
          ];
        }
        return $stats;
      }

    }
    return 0;

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
      $stats = [];
      $wards = 0;
      /** @var \Drupal\taxonomy\TermStorage $term_storage */
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $wards = $term_storage->loadTree('location', $locationId, 1, TRUE) ?? NULL;
      if (!empty($wards)) {
        foreach ($wards as $ward) {
          $ward_id = $ward->id();
          $school_count = 0;
          $schools = $this->getSchoolList($ward_id);
          $school_count += count($schools);
          $total_applications = $this->studentDetails(['district_admin'], $ward_id);
          $ward_name = $ward->label();
          $stats[$ward_name] = [
            'Wards' => $ward_id,
            'schools' => $school_count,
            'students' => $total_applications,
          ];
        }
      }

      return $stats;
    }
    // Return a markup about missing location.
    return 0;
  }

}
