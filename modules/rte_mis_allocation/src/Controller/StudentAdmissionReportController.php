<?php

declare(strict_types=1);

namespace Drupal\rte_mis_allocation\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides role based details for student.
 */
final class StudentAdmissionReportController extends ControllerBase {

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
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  public $configFactory;

  /**
   * Constructs the controller instance.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    ConfigFactoryInterface $config_factory,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('config.factory'),
    );
  }

  /**
   * Displays the role based details.
   *
   * @return array
   *   A render array.
   */
  public function build() {
    // Create a table with data.
    $build = [
      '#type' => 'table',
      '#header' => $this->getHeaders(),
      '#rows' => $this->getData(),
      '#attributes' => ['class' => ['student-reports']],
      '#cache' => [
        'contexts' => ['user.roles'],
        'tags' => [
          'user_list',
          'taxonomy_term_list',
          'mini_node_list',
        ],
      ],
    ];

    return $build;
  }

  /**
   * Function to get the headers.
   */
  protected function getHeaders() {
    // Return header based on the user role.
    $header = [];
    $currentUserRole = $this->currentUser->getRoles(TRUE);

    if (array_intersect(['app_admin', 'state_admin'], $currentUserRole)) {
      $header = ['No.', 'District Name', 'Total Block', 'Total Schools', 'Total RTE seats',
        'Total Applications', 'Total Applied', 'Total Duplicate', 'Total Incomplete',
        'Total Rejected', 'Total Approved', 'Total Allotted', 'Total Unallotted', 'Total Admitted',
        'Total Not Admitted', 'Total Dropped Out',
      ];
    }
    elseif (in_array('district_admin', $currentUserRole)) {
      $header = ['No.', 'Total Block', 'Total Schools', 'Total RTE seats',
        'Total Applications', 'Total Applied', 'Total Duplicate', 'Total Incomplete',
        'Total Rejected', 'Total Approved', 'Total Allotted', 'Total Unallotted', 'Total Admitted',
        'Total Not Admitted', 'Total Dropped Out',
      ];
    }
    elseif (in_array('block_admin', $currentUserRole)) {
      $header = ['No.', 'Total Schools', 'Total RTE seats',
        'Total Applications', 'Total Applied', 'Total Duplicate', 'Total Incomplete',
        'Total Rejected', 'Total Approved', 'Total Allotted', 'Total Unallotted', 'Total Admitted',
        'Total Not Admitted', 'Total Dropped Out',
      ];
    }

    return (array) $header;
  }

  /**
   * Function to get the row data.
   */
  protected function getData() {
    // Row data based on user current user role.
    $currentUserRole = $this->currentUser->getRoles(TRUE);

    $content = [];

    if (array_intersect(['app_admin', 'state_admin'], $currentUserRole)) {
      $content = $this->getStateAdminContent();
    }
    elseif (in_array('district_admin', $currentUserRole)) {
      $content = $this->getDistrictAdminContent();
    }
    elseif (in_array('block_admin', $currentUserRole)) {
      $content = $this->getBlockAdminContent();
    }
    return $content;
  }

  /**
   * Get content for state admin.
   */
  protected function getStateAdminContent() {
    // Implement data fetching logic.
    $serialNumber = 1;
    $districts = $this->entityTypeManager()->getStorage('taxonomy_term')->loadTree('location', 0, 1, TRUE);
    $data = [];
    foreach ($districts as $district) {
      $blocks = $this->getBlocksCount($district->id());
      $schools = count($this->getSchoolList($district->id()));
      $total_rte_seats = $this->totalRteSeats($district->id());
      $total_applications = $this->studentDetails($district->id());
      $total_applied = $this->studentDetails($district->id(), 'applied');
      $total_duplicate = $this->studentDetails($district->id(), 'duplicate');
      $total_incomplete = $this->studentDetails($district->id(), 'incomplete');
      $total_rejected = $this->studentDetails($district->id(), 'rejected');
      $total_approved = $this->studentDetails($district->id(), 'approved');
      $total_allotted = $this->studentStatus($district->id(), 'allotted');
      $total_admitted = $this->studentStatus($district->id(), 'admitted');
      $total_not_admitted = $this->studentStatus($district->id(), 'not_admitted');
      $total_dropout = $this->studentStatus($district->id(), 'dropout');
      $total_unallotted = $total_approved - ($total_allotted + $total_admitted + $total_not_admitted + $total_dropout);

      $data[] = [$serialNumber, $district->label(), $blocks, $schools, $total_rte_seats,
        $total_applications, $total_applied, $total_duplicate, $total_incomplete,
        $total_rejected, $total_approved, $total_allotted, $total_unallotted, $total_admitted, $total_not_admitted, $total_dropout,
      ];
      $serialNumber++;
    }

    return $data;
  }

  /**
   * Get content for district admin.
   */
  protected function getDistrictAdminContent() {
    // Implement data fetching logic.
    $serialNumber = 1;
    $currentUserId = $this->currentUser->id();

    /** @var \Drupal\user\Entity\User */
    $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);
    if ($currentUser instanceof UserInterface) {
      // Get location ID from user field.
      $locationId = $currentUser->get('field_location_details')->getString() ?? NULL;
    }
    $data = [];

    if ($locationId) {
      $blocks = $this->getBlocksCount($locationId);
      $schools = count($this->getSchoolList($locationId));
      $total_rte_seats = $this->totalRteSeats($locationId);
      $total_applications = $this->studentDetails($locationId);
      $total_applied = $this->studentDetails($locationId, 'applied');
      $total_duplicate = $this->studentDetails($locationId, 'duplicate');
      $total_incomplete = $this->studentDetails($locationId, 'incomplete');
      $total_rejected = $this->studentDetails($locationId, 'rejected');
      $total_approved = $this->studentDetails($locationId, 'approved');
      $total_allotted = $this->studentStatus($locationId, 'allotted');
      $total_admitted = $this->studentStatus($locationId, 'admitted');
      $total_not_admitted = $this->studentStatus($locationId, 'not_admitted');
      $total_dropout = $this->studentStatus($locationId, 'dropout');
      $total_unallotted = $total_approved - ($total_allotted + $total_admitted + $total_not_admitted + $total_dropout);

      $data[] = [$serialNumber, $blocks, $schools, $total_rte_seats,
        $total_applications, $total_applied, $total_duplicate, $total_incomplete,
        $total_rejected, $total_approved, $total_allotted, $total_unallotted, $total_admitted, $total_not_admitted, $total_dropout,
      ];

      return $data;
    }
    return [['#markup' => $this->t('Please check your location.')]];

  }

  /**
   * Get content for block admin.
   */
  protected function getBlockAdminContent() {
    $serialNumber = 1;
    $currentUserId = $this->currentUser->id();

    /** @var \Drupal\user\Entity\User */
    $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);

    if ($currentUser instanceof UserInterface) {
      // Get location ID from user field.
      $locationId = $currentUser->get('field_location_details')->getString() ?? NULL;
    }

    $data = [];

    if ($locationId) {
      $schools = count($this->getSchoolList($locationId));
      $total_rte_seats = $this->totalRteSeats('block_admin', $locationId);
      $total_applications = $this->studentDetails($locationId);
      $total_applied = $this->studentDetails($locationId, 'applied');
      $total_duplicate = $this->studentDetails($locationId, 'duplicate');
      $total_incomplete = $this->studentDetails($locationId, 'incomplete');
      $total_rejected = $this->studentDetails($locationId, 'rejected');
      $total_approved = $this->studentDetails($locationId, 'approved');
      $total_allotted = $this->studentStatus($locationId, 'allotted');
      $total_admitted = $this->studentStatus($locationId, 'admitted');
      $total_not_admitted = $this->studentStatus($locationId, 'not_admitted');
      $total_dropout = $this->studentStatus($locationId, 'dropout');
      $total_unallotted = $total_approved - ($total_allotted + $total_admitted + $total_not_admitted + $total_dropout);

      $data[] = [$serialNumber, $schools, $total_rte_seats,
        $total_applications, $total_applied, $total_duplicate, $total_incomplete,
        $total_rejected, $total_approved, $total_allotted, $total_unallotted, $total_admitted, $total_not_admitted, $total_dropout,
      ];

      return $data;
    }
    return [['#markup' => $this->t('Please check your location.')]];
  }

  /**
   * Wrapper function to get block admin count.
   *
   * @param string $locationId
   *   The location id to get the student details.
   *
   * @return int
   *   Count of block admin users or user location details based on role.
   */
  public function getBlocksCount(string $locationId = NULL): int {
    if ($locationId) {
      // Get the blocks list of taxonomy term based
      // on the district id provided.
      $location_tree = $this->entityTypeManager()->getStorage('taxonomy_term')->loadTree('location', $locationId, 1, FALSE);
      return count($location_tree);
    }
    return 0;
  }

  /**
   * Gets the school_admins based on the user's role and location.
   *
   * @param string $locationId
   *   The location id to get the student details.
   */
  public function getSchoolList(string $locationId = NULL) {

    $location_tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $locationId, NULL, FALSE);
    $locations = [];
    foreach ($location_tree as $value) {
      $locations[] = $value->tid;
    }

    // Query to count user with same location id.
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
   * Get the total list of RTE seats.
   *
   * @param string $locationId
   *   The location id to get the student details.
   *
   * @return int
   *   The count of total rte seats in a particular district.
   */
  public function totalRteSeats(string $locationId = NULL): int {
    $seats = 0;
    $locationIds = [];
    // Get the language from default option config.
    $school_config = $this->configFactory->get('rte_mis_school.settings');
    $languages = $school_config->get('field_default_options.field_medium') ?? [];
    $location = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $locationId, NULL, FALSE) ?? NULL;
    if ($location) {
      foreach ($location as $value) {
        $locationIds[] = $value->tid;
      }
    }

    // Get all the registered schools
    // with location as $district_id.
    if ($locationIds) {
      $query = $this->entityTypeManager->getStorage('mini_node')
        ->getQuery()
        ->condition('type', 'school_details')
        ->condition('field_school_verification', 'school_registration_verification_approved_by_deo')
        ->condition('field_location', $locationIds, 'IN')
        ->accessCheck(FALSE);

      $schools = $query->execute();

      foreach ($schools as $value) {
        $total_for_each_school = 0;
        $school_details = $this->entityTypeManager->getStorage('mini_node')->load($value);
        // Check for both single and dual entry.
        foreach ($school_details->get('field_entry_class')->referencedEntities() as $entry_class) {
          foreach ($languages as $key => $language) {
            $rte_seats[$entry_class->get('field_entry_class')->getString()]['rte_seat'][$key] = $entry_class->get('field_rte_student_for_' . $key)->getString();
            $total_for_each_school += $rte_seats[$entry_class->get('field_entry_class')->getString()]['rte_seat'][$key];
          }
        }
        $seats += $total_for_each_school;
      }

      return $seats;
    }
    return 0;
  }

  /**
   * Get the list of applied students.
   *
   * @param string $locationId
   *   The location id to get the student details.
   * @param string $status
   *   The current status of the student application.
   *
   * @return int
   *   The count of total rte seats in a particular district.
   */
  public function studentDetails(string $locationId = NULL, $status = NULL): int {
    // Load all the locations under current location id.
    $location = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $locationId, NULL, FALSE);
    $locationIds = [];
    if ($location) {
      foreach ($location as $value) {
        $locationIds[] = $value->tid;
      }
    }

    // Get all the registered schools
    // with location as $locationIds.
    if ($locationIds) {
      $query = $this->entityTypeManager->getStorage('mini_node')
        ->getQuery()
        ->condition('type', 'student_details')
        ->condition('field_location', $locationIds, 'IN')
        ->accessCheck(FALSE);

      if ($status == 'applied') {
        $query->condition('field_student_verification', 'student_workflow_submitted');
      }
      elseif ($status == 'duplicate') {
        $query->condition('field_student_verification', 'student_workflow_duplicate');
      }
      elseif ($status == 'incomplete') {
        $query->condition('field_student_verification', 'student_workflow_incomplete');
      }
      elseif ($status == 'rejected') {
        $query->condition('field_student_verification', 'student_workflow_rejected');
      }
      elseif ($status == 'approved') {
        $query->condition('field_student_verification', 'student_workflow_approved');
      }

      $students = $query->execute();
      $total_students = count($students);

      return $total_students;
    }
    return 0;
  }

  /**
   * Function to check student allotment status.
   *
   * @param string $locationId
   *   The location id to get the student details.
   * @param string $status
   *   The current status of the student application.
   *
   * @return int
   *   The count of total student with requested status.
   */
  public function studentStatus(string $locationId = NULL, $status = NULL): int {
    // Total student count.
    $student_count = 0;
    $school_list = [];
    // Get the list of school for the user role and user location.
    $school_admin = $this->getSchoolList($locationId);
    // Get the school mini node list from the school admin user id.
    if (!empty($school_admin)) {
      foreach ($school_admin as $value) {
        $school_miniNode = $this->entityTypeManager->getStorage('user')->load($value);
        if ($school_miniNode instanceof UserInterface) {
          $school_id = $school_miniNode->get('field_school_details')->getString() ?? NULL;
          if ($school_id !== NULL) {
            $school_list[] = $school_id;
          }
        }
      }
    }

    // Query to count the number of student with different status values.
    if ($school_list) {
      $query = $this->entityTypeManager->getStorage('mini_node')
        ->getQuery()
        ->condition('type', 'allocation')
        ->condition('field_school', $school_list, 'IN')
        ->accessCheck(FALSE);

      if ($status == 'admitted') {
        $query->condition('field_student_allocation_status', 'student_admission_workflow_admitted');
      }
      elseif ($status == 'not_admitted') {
        $query->condition('field_student_allocation_status', 'student_admission_workflow_not_admitted');
      }
      elseif ($status == 'dropout') {
        $query->condition('field_student_allocation_status', 'student_admission_workflow_dropout');
      }
      elseif ($status == 'allotted') {
        $query->condition('field_student_allocation_status', 'student_admission_workflow_allotted');
      }

      $student_count = $query->execute();
      return count($student_count);
    }
    return 0;
  }

}
