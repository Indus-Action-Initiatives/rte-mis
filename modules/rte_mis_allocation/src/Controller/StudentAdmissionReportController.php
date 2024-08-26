<?php

declare(strict_types=1);

namespace Drupal\rte_mis_allocation\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
   * Acces for district and block admin.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current user account.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Current routeMatch.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   If the user can access to the allotment report dashboard.
   */
  public function access(AccountInterface $account, RouteMatchInterface $routeMatch) {
    // Checks if a district/block admin cannot access
    // their adjacent or above hierarchy data.
    $id = $routeMatch->getParameter('id') ?? NULL;
    $currentUser = $this->entityTypeManager->getStorage('user')->load($account->id());
    $currentUserRole = $currentUser->getRoles(TRUE);
    $currentUserLocation = $currentUser->get('field_location_details')->getString() ?? NULL;

    if (in_array('district_admin', $currentUserRole) && $currentUserLocation) {
      $locationTree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $currentUserLocation, NULL, FALSE);
      if ($currentUserLocation == $id) {
        return AccessResult::allowed()->setCacheMaxAge(0);
      }
      foreach ($locationTree as $value) {
        if ($value->tid == $id) {
          // If the location id is below block return access denied.
          if ($value->depth >= 1) {
            return AccessResult::forbidden()->setCacheMaxAge(0);
          }
          return AccessResult::allowed()->setCacheMaxAge(0);
        }
      }
      return AccessResult::allowedIf($id == NULL)->setCacheMaxAge(0);
    }
    elseif (in_array('block_admin', $currentUserRole) && $currentUserLocation) {
      if ($currentUserLocation == $id) {
        return AccessResult::allowed()->setCacheMaxAge(0);
      }
      return AccessResult::allowedIf($id == NULL)->setCacheMaxAge(0);
    }

    return AccessResult::allowed()->setCacheMaxAge(0);
  }

  /**
   * Displays the role based details.
   *
   * @return array
   *   A render array.
   */
  public function build(string $id = NULL) {

    if ((is_numeric($id) && $this->checkLocation($id)) || $id == NULL) {
      $currentUserId = $this->currentUser->id();
      $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);

      if ($currentUser instanceof UserInterface) {
        if (array_intersect(['district_admin', 'block_admin'], $currentUser->getRoles(TRUE))) {
          // Get location ID from user field.
          $locationId = $currentUser->get('field_location_details')->getString() ?? NULL;
          if (!$id && $locationId) {
            $url = Url::fromRoute('rte_mis_allocation.controller.student_admission_report', ['id' => $locationId])->toString();

            // Return a redirect response.
            return new RedirectResponse($url);
          }
        }
      }
      // Create a table with data.
      $build = [
        '#type' => 'table',
        '#header' => $this->getHeaders($id),
        '#rows' => $this->getData($id),
        '#attributes' => ['class' => ['student-reports']],
        '#empty' => $this->t('No data to display.'),
        '#cache' => [
          'contexts' => ['user'],
          'tags' => [
            'user_list',
            'taxonomy_term_list',
            'mini_node_list',
          ],
        ],
      ];

      return $build;

    }
    else {
      throw new NotFoundHttpException();
    }
  }

  /**
   * Function to check if location exists.
   */
  protected function checkLocation($id) {
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
      'vid' => 'location',
      'tid' => $id,
      'status' => 1,
    ]);
    return (bool) $term;
  }

  /**
   * Function to get the headers.
   */
  protected function getHeaders($id = NULL) {
    // Return header based on the user role.
    $header = [
      $this->t('Total Schools'), $this->t('Total RTE seats'), $this->t('Total Applications'), $this->t('Total Applied'), $this->t('Total Duplicate'), $this->t('Total Incomplete'), $this->t('Total Rejected'), $this->t('Total Approved'), $this->t('Total Allotted'), $this->t('Total Unallotted'), $this->t('Total Admitted'), $this->t('Total Not Admitted'), $this->t('Total Dropped Out'),
    ];

    $currentUserRole = $this->currentUser->getRoles(TRUE);

    if (array_intersect(['app_admin', 'state_admin'], $currentUserRole) && !$id) {
      $header = array_merge([
        $this->t('No.'), $this->t('District Name'), $this->t('Total Block'),
      ], $header);
    }
    else {
      $locationMap = [];
      $locationTree = $this->entityTypeManager->getStorage('taxonomy_term')->loadtree('location', 0, 2, FALSE);
      foreach ($locationTree as $term) {
        // Add tid and depth to the map.
        $locationMap[$term->tid] = $term->depth;
      }

      if (array_key_exists($id, $locationMap)) {
        $depth = $locationMap[$id];
        if ($depth == 0) {
          // It is a district location.
          $header = array_merge([
            $this->t('No.'), $this->t('Total Block'),
          ], $header);
        }
        else {
          // It is a block location.
          $header = array_merge([
            $this->t('No.'),
          ], $header);
        }
      }

    }

    return (array) $header;
  }

  /**
   * Function to get the row data.
   */
  protected function getData($id = NULL) {
    $content = [];
    $currentUserRole = $this->currentUser->getRoles(TRUE);

    if (array_intersect(['app_admin', 'state_admin'], $currentUserRole) && !$id) {
      $content = $this->getStateAdminContent($id);
    }
    else {
      $locationMap = [];
      $locationTree = $this->entityTypeManager->getStorage('taxonomy_term')->loadtree('location', 0, 2, FALSE);
      foreach ($locationTree as $term) {
        // Add tid and depth to the map.
        $locationMap[$term->tid] = $term->depth;
      }

      if (array_key_exists($id, $locationMap)) {
        $depth = $locationMap[$id];
        if ($depth == 0) {
          // It is a district location.
          $content = $this->getDistrictAdminContent($id);
        }
        else {
          // It is a block location.
          $content = $this->getBlockAdminContent($id);
        }
      }

    }

    return $content;
  }

  /**
   * Get content for state admin.
   */
  protected function getStateAdminContent($id = NULL) {
    // Implemented data fetching logic.
    // Serial Number.
    $serialNumber = 1;
    $districts = $this->entityTypeManager()->getStorage('taxonomy_term')->loadTree('location', 0, 1, TRUE) ?? NULL;
    $data = [];
    if ($districts) {
      foreach ($districts as $district) {
        $blocks = $this->getBlocksCount($district->id());
        $schools = count($this->getSchoolList($district->id()));
        $total_rte_seats = $this->totalRteSeats('state_admin', $district->id());
        $total_applications = $this->studentDetails('state_admin', $district->id());
        $total_applied = $this->studentDetails('state_admin', $district->id(), 'applied');
        $total_duplicate = $this->studentDetails('state_admin', $district->id(), 'duplicate');
        $total_incomplete = $this->studentDetails('state_admin', $district->id(), 'incomplete');
        $total_rejected = $this->studentDetails('state_admin', $district->id(), 'rejected');
        $total_approved = $this->studentDetails('state_admin', $district->id(), 'approved');
        $total_allotted = $this->studentStatus('state_admin', $district->id(), 'allotted');
        $total_admitted = $this->studentStatus('state_admin', $district->id(), 'admitted');
        $total_not_admitted = $this->studentStatus('state_admin', $district->id(), 'not_admitted');
        $total_dropout = $this->studentStatus('state_admin', $district->id(), 'dropout');
        $total_unallotted = $total_approved - ($total_allotted + $total_admitted + $total_not_admitted + $total_dropout);

        // Create link render array.
        $block_id = $district->id();
        $url = Url::fromUri("internal:/student-admission-report/{$block_id}");
        $link = Link::fromTextAndUrl($district->label(), $url)->toRenderable();

        $data[] = [$serialNumber, ['data' => $link], $blocks, $schools, $total_rte_seats,
          $total_applications, $total_applied, $total_duplicate, $total_incomplete,
          $total_rejected, $total_approved, $total_allotted, $total_unallotted, $total_admitted, $total_not_admitted, $total_dropout,
        ];
        $serialNumber++;
      }

      return $data;
    }
    // Return a markup about missing location.
    return 0;
  }

  /**
   * Get content for district admin.
   */
  protected function getDistrictAdminContent($id = NULL) {
    // Implemented data fetching logic.
    // Serial Number.
    $serialNumber = 1;
    if ($id == NULL) {
      $currentUserId = $this->currentUser->id();
      /** @var \Drupal\user\Entity\User */
      $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);
      if ($currentUser instanceof UserInterface) {
        // Get location ID from user field.
        $locationId = $currentUser->get('field_location_details')->getString() ?? NULL;
      }
    }
    else {
      $locationId = $id;
    }

    if ($locationId) {
      $data = [];
      $blocks = $this->entityTypeManager()->getStorage('taxonomy_term')->loadTree('location', $locationId, 1, TRUE) ?? NULL;
      if ($blocks) {
        foreach ($blocks as $block) {
          $schools = count($this->getSchoolList($block->id()));
          $total_rte_seats = $this->totalRteSeats('district_admin', $block->id());
          $total_applications = $this->studentDetails('district_admin', $block->id());
          $total_applied = $this->studentDetails('district_admin', $block->id(), 'applied');
          $total_duplicate = $this->studentDetails('district_admin', $block->id(), 'duplicate');
          $total_incomplete = $this->studentDetails('district_admin', $block->id(), 'incomplete');
          $total_rejected = $this->studentDetails('district_admin', $block->id(), 'rejected');
          $total_approved = $this->studentDetails('district_admin', $block->id(), 'approved');
          $total_allotted = $this->studentStatus('district_admin', $block->id(), 'allotted');
          $total_admitted = $this->studentStatus('district_admin', $block->id(), 'admitted');
          $total_not_admitted = $this->studentStatus('district_admin', $block->id(), 'not_admitted');
          $total_dropout = $this->studentStatus('district_admin', $block->id(), 'dropout');
          $total_unallotted = $total_approved - ($total_allotted + $total_admitted + $total_not_admitted + $total_dropout);

          // Create link render array.
          $block_id = $block->id();
          $url = Url::fromUri("internal:/student-admission-report/{$block_id}");
          $link = Link::fromTextAndUrl($block->label(), $url)->toRenderable();

          $data[] = [$serialNumber, ['data' => $link], $schools, $total_rte_seats,
            $total_applications, $total_applied, $total_duplicate, $total_incomplete,
            $total_rejected, $total_approved, $total_allotted, $total_unallotted, $total_admitted, $total_not_admitted, $total_dropout,
          ];
          $serialNumber++;
        }
        return $data;
      }

    }
    return 0;

  }

  /**
   * Get content for block admin.
   */
  protected function getBlockAdminContent($id = NULL) {
    // Implemented data fetching logic.
    // Serial Number.
    $serialNumber = 1;

    if ($id == NULL) {
      $currentUserId = $this->currentUser->id();

      /** @var \Drupal\user\Entity\User */
      $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);

      if ($currentUser instanceof UserInterface) {
        // Get location ID from user field.
        $locationId = $currentUser->get('field_location_details')->getString() ?? NULL;
      }
    }
    else {
      $locationId = $id;
    }

    if ($locationId) {
      $data = [];
      $schools = $this->getSchoolList($locationId);
      if (empty($schools)) {
        return 0;
      }
      foreach ($schools as $school) {
        $school_miniNode = $this->entityTypeManager->getStorage('user')->load($school)->get('field_school_details')->referencedEntities();
        $school_miniNode = reset($school_miniNode);
        $total_rte_seats = $this->totalRteSeats('block_admin', $school_miniNode->id());
        $total_applications = $this->studentDetails('block_admin', $school_miniNode->id());
        $total_applied = $this->studentDetails('block_admin', $school_miniNode->id(), 'applied');
        $total_duplicate = $this->studentDetails('block_admin', $school_miniNode->id(), 'duplicate');
        $total_incomplete = $this->studentDetails('block_admin', $school_miniNode->id(), 'incomplete');
        $total_rejected = $this->studentDetails('block_admin', $school_miniNode->id(), 'rejected');
        $total_approved = $this->studentDetails('block_admin', $school_miniNode->id(), 'approved');
        $total_allotted = $this->studentStatus('block_admin', $school_miniNode->id(), 'allotted');
        $total_admitted = $this->studentStatus('block_admin', $school_miniNode->id(), 'admitted');
        $total_not_admitted = $this->studentStatus('block_admin', $school_miniNode->id(), 'not_admitted');
        $total_dropout = $this->studentStatus('block_admin', $school_miniNode->id(), 'dropout');
        $total_unallotted = $total_approved - ($total_allotted + $total_admitted + $total_not_admitted + $total_dropout);

        $data[] = [$serialNumber, $school_miniNode->get('field_school_name')->getString(), $total_rte_seats,
          $total_applications, $total_applied, $total_duplicate, $total_incomplete,
          $total_rejected, $total_approved, $total_allotted, $total_unallotted, $total_admitted, $total_not_admitted, $total_dropout,
        ];
        $serialNumber++;
      }

      return $data;
    }
    // Return a markup about missing location.
    return 0;
  }

  /**
   * Function to get block admin count.
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
      $location_tree = $this->entityTypeManager()->getStorage('taxonomy_term')->loadTree('location', $locationId, 1, FALSE) ?? NULL;
      return $location_tree ? count($location_tree) : 0;
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

    $location_tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $locationId, NULL, FALSE) ?? NULL;
    $locations = [];

    if ($location_tree) {
      foreach ($location_tree as $value) {
        $locations[] = $value->tid;
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
   * Get the total list of RTE seats.
   *
   * @param string $current_role
   *   The current user role.
   * @param string $id
   *   The location id to get the student details for state & district.
   *   And the mini node id for block admin.
   *
   * @return int
   *   The count of total rte seats in a particular location.
   */
  public function totalRteSeats(string $current_role, string $id = NULL): int {
    // Get the language from default option config.
    $school_config = $this->configFactory->get('rte_mis_school.settings');
    $languages = $school_config->get('field_default_options.field_medium') ?? [];
    if (in_array($current_role, ['state_admin', 'district_admin'])) {
      $seats = 0;
      $locationIds = [];
      $location = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $id, NULL, FALSE) ?? NULL;
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
          // Get the seat information of each school
          // and add it to total seats.
          $seats += $this->eachSchoolSeatCount($languages, $value);
        }

        return $seats;
      }

    }
    elseif ($current_role == 'block_admin') {
      // Gte the seat information of the school.
      return $this->eachSchoolSeatCount($languages, $id);
    }
    return 0;
  }

  /**
   * Function to count the seats in each school.
   *
   * @param array $languages
   *   The languages from the config.
   * @param string $id
   *   The `id` of the school.
   *
   * @return int
   *   Return the count of seat in each school.
   */
  protected function eachSchoolSeatCount(array $languages, string $id) {
    $totalEachSchool = 0;
    $school_details = $this->entityTypeManager->getStorage('mini_node')->load($id);
    // Check for both single and dual entry.
    foreach ($school_details->get('field_entry_class')->referencedEntities() as $entry_class) {
      foreach ($languages as $key => $language) {
        $rte_seats[$entry_class->get('field_entry_class')->getString()]['rte_seat'][$key] = $entry_class->get('field_rte_student_for_' . $key)->getString();
        $totalEachSchool += $rte_seats[$entry_class->get('field_entry_class')->getString()]['rte_seat'][$key];
      }
    }
    return $totalEachSchool;
  }

  /**
   * Get the list of applied students.
   *
   * @param string $current_role
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
  public function studentDetails(string $current_role, string $id = NULL, $status = NULL): int {
    if (in_array($current_role, ['state_admin', 'district_admin'])) {
      // Load all the locations under current location id.
      $location = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $id, NULL, FALSE);
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
    }
    elseif ($current_role == 'block_admin') {
      // List down the students which have a particular school with a id.
      $query = $this->entityTypeManager->getStorage('mini_node')
        ->getQuery()
        ->condition('type', 'student_details')
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
   * Function to check student allotment status.
   *
   * @param string $current_role
   *   The current user role.
   * @param string $id
   *   The location id to get the student details for state & district.
   *   And the mini node id for block admin.
   * @param string $status
   *   The current status of the allocation process.
   *
   * @return int
   *   The count of total student with requested status.
   */
  public function studentStatus($current_role, string $id = NULL, $status = NULL): int {
    if (in_array($current_role, ['state_admin', 'district_admin'])) {
      // For state, district admins get the location id via `$id`.
      $student_count = 0;
      $school_list = [];
      // Get the list of school for the user role and user location.
      $school_admin = $this->getSchoolList($id);
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

    }
    elseif ($current_role == 'block_admin') {
      // Get the school miniNode `$id` and check for entries in
      // allocation miniNode with requested status.
      $query = $this->entityTypeManager->getStorage('mini_node')
        ->getQuery()
        ->condition('type', 'allocation')
        ->condition('field_school', $id)
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
