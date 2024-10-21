<?php

namespace Drupal\rte_mis_report\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eck\EckEntityInterface;
use Drupal\taxonomy\TermInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Class RteReportHelper.
 *
 * Provides helper functions for rte mis report module.
 */
class RteReportHelper {

  use StringTranslationTrait;

  /**
   * The entity type manager.
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
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a RteAllocationHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user account.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $currentUser, Connection $database, RouteMatchInterface $route_match, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $currentUser;
    $this->database = $database;
    $this->routeMatch = $route_match;
    $this->configFactory = $config_factory;
  }

  /**
   * Function to create excel.
   *
   * @param string $heading_text
   *   The heading text to be used.
   * @param array $header
   *   The table headers.
   * @param array $rows
   *   The table rows.
   * @param string $filename
   *   The name of the file to be downloaded.
   * @param int $max_columns
   *   The maximum number of columns to be utilized.
   * @param array $context
   *   An associative array containing context information.
   *
   * @return void
   *   Returns nothing.
   */
  public static function excelDownload(string $heading_text, array $header, array $rows, string $filename, int $max_columns, array $context) {
    // Initialize the spreadsheet in the first batch run.
    if (!isset($context['results']['spreadsheet'])) {
      $context['results']['spreadsheet'] = new Spreadsheet();
      $context['results']['row'] = 1;
    }

    $spreadsheet = $context['results']['spreadsheet'];
    $sheet = $spreadsheet->getActiveSheet();

    // Add the heading to the first row and center it.
    $sheet->setCellValue('A1', $heading_text);

    // Leave the second row empty.
    $context['results']['row'] = 3;

    // Write header to the third row.
    if ($context['results']['row'] == 3) {
      $sheet->fromArray($header, NULL, 'A3');
      $context['results']['row']++;
    }

    // Style the header row (e.g., bold).
    $boldStyle = [
      'font' => [
        'bold' => TRUE,
      ],
    ];
    $sheet->getStyle('A3:' . $sheet->getHighestColumn() . '3')->applyFromArray($boldStyle);

    // Append data rows starting from the 4th row.
    foreach ($rows as $row_data) {
      $flat_row = [];

      foreach ($row_data as $value) {
        // If it's a scalar value, just add it.
        if (is_scalar($value) || is_numeric($value)) {
          $flat_row[] = $value;
        }
        elseif (is_array($value) && isset($value['data'])) {
          // Check if 'data' is an array and if it contains a link structure.
          if (isset($value['data']['#type']) && $value['data']['#type'] == 'link') {
            // For links, extract the title.
            $flat_row[] = $value['data']['#title'];
          }
          else {
            // If it's a simple value in 'data', add that.
            $flat_row[] = $value['data'];
          }
        }
      }

      $string_row_data = array_map('strval', $flat_row);
      $sheet->fromArray($string_row_data, NULL, 'A' . $context['results']['row']);
      $context['results']['row']++;
    }

    for ($colIndex = 1; $colIndex <= $max_columns; $colIndex++) {
      $sheet->getColumnDimensionByColumn($colIndex)->setWidth(25);
    }

    // Merge cells for the heading and center it.
    $sheet->mergeCells('A1:' . $sheet->getHighestColumn() . '1');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // After processing, export the file.
    if ($context['finished']) {
      $writer = new Xlsx($spreadsheet);
      $temp_file = tempnam(sys_get_temp_dir(), 'excel');
      $writer->save($temp_file);
      // Output the file to download.
      header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
      readfile($temp_file);
      unlink($temp_file);
      exit();
    }
  }

  /**
   * Function to check if location exists.
   *
   * @param string $id
   *   Location Id.
   */
  public function checkLocation(?string $id) {
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
      'vid' => 'location',
      'tid' => $id,
      'status' => 1,
    ]);
    return (bool) $term;
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
  public function getBlocksCount(?string $locationId = NULL): int {
    if ($locationId) {
      // Get the blocks list of taxonomy term based
      // on the district id provided.
      $location_tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $locationId, 1, FALSE) ?? NULL;
      return $location_tree ? count($location_tree) : 0;
    }
    return 0;
  }

  /**
   * Gets the school_admins based on the location.
   *
   * @param string $locationId
   *   The location id to get the student details.
   */
  public function getSchoolListCount(?string $locationId = NULL) {

    $location_tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $locationId, NULL, FALSE) ?? NULL;
    $locations = [];

    if ($location_tree) {
      $locations[] = $locationId;
      foreach ($location_tree as $value) {
        $locations[] = $value->tid;
      }
    }

    // Query to count active user with same location id &
    // `school_admin` user role.
    if (!empty($locations)) {

      $query = $this->entityTypeManager->getStorage('taxonomy_term')
        ->getQuery()
        ->condition('status', 1)
        ->condition('vid', 'school')
        ->condition('field_location', $locations, 'IN')
        ->condition('field_workflow', 'school_workflow_approved')
        ->accessCheck(FALSE);

      $schools = $query->execute();

      // Return an array of all the schools under a location id.
      return count($schools);

    }

    return 0;
  }

  /**
   * Gets the school based on the location.
   *
   * @param string $locationId
   *   The location id to get the student details.
   */
  public function getSchoolList(?string $locationId = NULL) {

    $location_tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $locationId, NULL, FALSE) ?? NULL;
    $locations = [];

    if ($location_tree) {
      $locations[] = $locationId;
      foreach ($location_tree as $value) {
        $locations[] = $value->tid;
      }
    }

    // Query to count active user with same location id &
    // `school_admin` user role.
    if (!empty($locations)) {
      $route_name = $this->routeMatch->getRouteName();
      if ($route_name == 'rte_mis_report.controller.school_registration_report_school_details') {
        // Return an array of all the schools under a location id.
        $query = $this->database->select('taxonomy_term_field_data', 't')
          ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
          ->limit(10);
      }
      else {
        $query = $this->database->select('taxonomy_term_field_data', 't');
      }

      $query->fields('t', ['tid', 'name'])
        ->condition('t.vid', 'school')
        ->orderBy('t.name', 'ASC')
        ->condition('t.status', 1);

      // Join the field_location table to filter by location.
      $query->leftJoin('taxonomy_term__field_location', 'fl', 't.tid = fl.entity_id');
      $query->condition('fl.field_location_target_id', $locations, 'IN');
      // Join the field_workflow table to filter by workflow status.
      $query->leftJoin('taxonomy_term__field_workflow', 'fw', 't.tid = fw.entity_id');
      $query->condition('fw.field_workflow_value', 'school_workflow_approved');
      $query->leftJoin('taxonomy_term__field_school_name', 'fsn', 't.tid = fsn.entity_id');
      $query->addField('fsn', 'field_school_name_value', 'school_name');
      // Execute the query and fetch results.
      $schools = $query->execute()->fetchAll();
      return $schools;
    }

    return [];
  }

  /**
   * Gets the school_admins based on the user's role and location.
   *
   * @param string $locationId
   *   The location id to get the student details.
   * @param string $key
   *   The key for which to check the status.
   *
   * @return array
   *   list of schools.
   */
  public function getRegisteredSchoolList(?string $locationId = NULL, ?string $key = NULL) {

    $location_tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $locationId, NULL, FALSE) ?? NULL;
    $locations = [];

    if ($location_tree) {
      foreach ($location_tree as $value) {
        $locations[] = $value->tid;
      }
    }

    // If user has any state then they can be considered as
    // registered except pending,
    // If they have state as approved by deo and role as school_admin
    // then they are cosndiered as approved school.
    // Query to count the schools who have registered for the campaign.
    if (!empty($locations)) {
      $query = $this->entityTypeManager->getStorage('user')
        ->getQuery()
        ->condition('status', 1)
        ->condition('field_school_details.entity:mini_node.field_location', $locations, 'IN')
        ->accessCheck(FALSE);

      if ($key) {
        $query->condition('roles', 'school_admin');
        $query->condition('field_school_details.entity:mini_node.field_school_verification', 'school_registration_verification_approved_by_deo');
      }
      $schools = $query->execute();

      // Return an array of all the schools under a location id.
      return $schools;
    }

    return [];
  }

  /**
   * Gets the count of school based on status.
   *
   * @param string $current_role
   *   The current user role ('state_admin', 'district_admin', 'block_admin').
   * @param string $status_key
   *   The pending role ('submitted', 'rejected', 'approved_by_beo',
   *   'approved_by_beo').
   *
   * @return int
   *   The count of pending schools.
   */
  public function getSchoolStatus(string $current_role, string $status_key): int {
    // Initialize variables.
    $pending_count = 0;

    // Determine the query conditions based on the current role.
    switch ($current_role) {
      case 'state_admin':
        // Query all users.
        $query = $this->entityTypeManager->getStorage('user')
          ->getQuery()
          ->condition('status', 1)
          ->accessCheck(FALSE);

        if ($status_key == 'approved_by_deo') {
          $query->condition('roles', 'school_admin');
        }
        else {
          $query->condition('roles', 'school');
        }

        // Execute the query to get ids.
        $school_ids = $query->execute();

        // Return 0 if no schools found.
        if (empty($school_ids)) {
          return 0;
        }

        if (!empty($school_ids)) {
          // Filter schools based on status key.
          $verification_status = 'school_registration_verification_' . $status_key;
          $pending_count = $this->countPendingSchools($school_ids, $verification_status);
        }
        break;

      case 'district_admin':
      case 'block_admin':
        // Get matching school term IDs based on the admin's location.
        $matchingSchoolIds = $this->gettingMatchingSchoolTerms() ?? NULL;
        $roles = ['school', 'school_admin'];

        $termName = [];
        if ($matchingSchoolIds) {
          foreach ($matchingSchoolIds as $value) {
            $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($value);
            $termName[] = $term->label();
          }
        }
        if (!empty($termName)) {
          $query = $this->entityTypeManager->getStorage('user')
            ->getQuery()
            ->condition('roles', $roles, 'IN')
            ->condition('name', $termName, 'IN')
            ->condition('status', 1)
            ->accessCheck(FALSE);
          $school_ids = $query->execute();
        }
        if (!empty($school_ids)) {
          // Filter schools based on status key.
          $verification_status = 'school_registration_verification_' . $status_key;
          $pending_count = $this->countPendingSchools($school_ids, $verification_status);
        }

        break;

      default:
        // Default return if $current_role doesn't match any condition.
        $pending_count = 0;
        break;
    }

    return $pending_count;
  }

  /**
   * Counts the number of pending schools based on given conditions.
   *
   * @param array $school_ids
   *   Array of school user IDs to filter.
   * @param string $verification_status
   *   Verification status to filter by ('school_registration_verification_appro
   *   ved_by_beo' or 'school_registration_verification_submitted').
   *
   * @return int
   *   The count of pending schools.
   */
  public function countPendingSchools(array $school_ids, string $verification_status): int {
    // Query to count pending schools.
    $query = $this->entityTypeManager->getStorage('user')
      ->getQuery()
      ->condition('uid', $school_ids, 'IN')
      ->accessCheck(FALSE)
      ->condition('field_school_details.entity:mini_node.field_school_verification', $verification_status);

    // Execute the query to get pending school user IDs.
    $pending_nids = $query->execute();

    // Count the pending schools.
    return count($pending_nids);
  }

  /**
   * Gets the IDs of schools matching the current user's location.
   *
   * This function retrieves the IDs of taxonomy terms (schools) that match
   * the location of the current user based on their 'field_location_details'
   * field.
   *
   * @return array
   *   An array of school IDs matching the current user's location.
   */
  public function gettingMatchingSchoolTerms() {
    // Get the current user's ID and load the user entity.
    $currentUserId = $this->currentUser->id();

    /** @var \Drupal\user\Entity\User */
    $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);
    $currentUserRole = $this->currentUser->getRoles(TRUE);

    /** @var \Drupal\taxonomy\TermStorage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    // Extract the location ID from the user's field.
    $locationId = (int) $currentUser->get('field_location_details')->getString();

    $locationIds = [];
    if (in_array('district_admin', $currentUserRole)) {
      // For district get the child location.
      $childLocation = $term_storage->loadTree('location', $locationId, 1, FALSE);
      if ($childLocation) {
        foreach ($childLocation as $value) {
          $locationIds[] = $value->tid;
        }
      }
      if ($locationIds) {
        $query = $term_storage->getQuery()
        // Filter by the 'school' vocabulary.
          ->condition('vid', 'school')
          ->condition('field_location', $locationIds, 'IN')
          ->accessCheck(FALSE);

        // Execute the query to get taxonomy term IDs (tids).
        $tids = $query->execute();
        return $tids;
      }
    }

    $query = $term_storage->getQuery()
      // Filter by the 'school' vocabulary.
      ->condition('vid', 'school')
      ->condition('field_location', $locationId)
      ->accessCheck(FALSE);

    // Execute the query to get taxonomy term IDs (tids).
    $tids = $query->execute();

    // Return the array of matching school IDs.
    return $tids;
  }

  /**
   * Check if the mapping is done.
   *
   * @param string $locationId
   *   Location id.
   * @param bool $status
   *   True/False.
   *
   * @return array
   *   Returns an array of status.
   */
  public function mappingStatus(?string $locationId = NULL, bool $status = FALSE) {

    $location_tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $locationId, NULL, FALSE) ?? NULL;
    $locations = [];

    if ($location_tree) {
      foreach ($location_tree as $value) {
        $locations[] = $value->tid;
      }
    }

    if (!empty($locations)) {
      // Get all the schools whose mapping has been done.
      $query = $this->entityTypeManager->getStorage('mini_node')
        ->getQuery()
        ->condition('type', 'school_details')
        ->condition('status', 1)
        ->condition('field_location', $locations, 'IN')
        ->accessCheck(FALSE);

      if ($status == FALSE) {
        // If False then check for the mini_nodes with no habitations defined.
        $query->notExists('field_habitations');
      }
      else {
        $query->exists('field_habitations');
      }
      // Execute the query to get ids.
      $school_ids = $query->execute();
      return $school_ids;
    }

    return [];
  }

  /**
   * Function to check the school Status.
   *
   * @param string $school_id
   *   School id.
   *
   * @return array
   *   Returns an array of status.
   */
  public function checkSchoolStatus(?string $school_id = NULL): array {
    $status_list = [
      'pending_beo_approval' => 'No',
      'pending_deo_approval' => 'No',
      'approved' => 'No',
      'mapping_completed' => 'No',
      'mapping_pending' => 'No',
    ];
    if ($school_id) {
      $mini_node = $this->entityTypeManager->getStorage('mini_node')->load($school_id);
      if ($mini_node instanceof EckEntityInterface) {
        $habitation_value = $mini_node->get('field_habitations')->getString();
        $workflow_status = $mini_node->get('field_school_verification')->getString();
        switch ($workflow_status) {
          case 'school_registration_verification_submitted':
            $status_list['pending_beo_approval'] = 'Yes';
            break;

          case 'school_registration_verification_approved_by_beo':
            $status_list['pending_deo_approval'] = 'Yes';
            break;

          case 'school_registration_verification_approved_by_deo':
            $status_list['approved'] = 'Yes';
            break;

          default:
            $status_list['pending_beo_approval'] = 'No';
            break;
        }
        if ($habitation_value) {
          $status_list['mapping_completed'] = 'Yes';
        }
        else {
          $status_list['mapping_pending'] = 'Yes';
        }
      }

    }
    return $status_list;
  }

  /**
   * Check if the school is registered.
   *
   * @param string $udise_id
   *   School udise code id.
   * @param string $school_name
   *   School name.
   *
   * @return mixed
   *   The school ID if the school is registered, FALSE otherwise.
   */
  public function checkRegistration(string $udise_id, string $school_name) {
    // Query the 'mini_node' storage to check for a matching school.
    $query = $this->entityTypeManager->getStorage('mini_node')
      ->getQuery()
      ->condition('type', 'school_details')
      ->condition('field_academic_year', _rte_mis_core_get_current_academic_year())
      ->condition('field_udise_code', $udise_id)
      ->condition('field_school_name', $school_name)
      ->condition('status', 1)
      ->accessCheck(FALSE);

    // Execute the query.
    $matching_school = $query->execute();

    // If the query returns results, return the school ID (first match).
    if (!empty($matching_school)) {
      // Return the first matching ID.
      return reset($matching_school);
    }

    // Return FALSE if no match is found.
    return FALSE;
  }

  /**
   * Gets the school based on the user's role and location.
   *
   * @param string $locationId
   *   The location id to get the student details.
   * @param string $status
   *   The status of the application.
   */
  public function getRegisteredSchoolStatus(?string $locationId = NULL, ?string $status = NULL) {

    $location_tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $locationId, NULL, FALSE) ?? NULL;
    $locations = [];

    if ($location_tree) {
      $locations[] = $locationId;
      foreach ($location_tree as $value) {
        $locations[] = $value->tid;
      }
    }

    $route_name = $this->routeMatch->getRouteName();
    if ($route_name == 'rte_mis_report.controller.school_registration_report_school_details') {
      $query = $this->database->select('mini_node_field_data', 'nfd')
        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
        ->limit(10);
    }
    else {
      $query = $this->database->select('mini_node_field_data', 'nfd');
    }

    $query->distinct()
      ->fields('nfd', ['id'])
      ->condition('nfd.type', 'school_details')
      ->condition('nfd.status', 1);

    // Join with the field_udise_code table.
    $query->leftJoin('mini_node__field_udise_code', 'fuc', 'nfd.id = fuc.entity_id');
    $query->leftJoin('taxonomy_term_field_data', 'ttfd', 'fuc.field_udise_code_value = ttfd.tid');
    $query->addField('ttfd', 'name', 'name');

    // Join with the field_school_name table.
    $query->leftJoin('mini_node__field_school_name', 'fsn', 'nfd.id = fsn.entity_id');
    $query->addField('fsn', 'field_school_name_value', 'school_name');

    // Join with the field_academic_year table.
    $query->leftJoin('mini_node__field_academic_year', 'fay', 'nfd.id = fay.entity_id');
    $query->condition('fay.field_academic_year_value', _rte_mis_core_get_current_academic_year());

    // Join with the field_location table.
    $query->leftJoin('mini_node__field_location', 'fl', 'nfd.id = fl.entity_id');
    $query->condition('fl.field_location_target_id', $locations, 'IN');

    // Apply conditions based on the status.
    if ($status && $status == 'pending_beo_approval') {
      $query->leftJoin('mini_node__field_school_verification', 'fsv', 'nfd.id = fsv.entity_id');
      $query->condition('fsv.field_school_verification_value', 'school_registration_verification_submitted');
    }
    elseif ($status && $status == 'pending_deo_approval') {
      $query->leftJoin('mini_node__field_school_verification', 'fsv', 'nfd.id = fsv.entity_id');
      $query->condition('fsv.field_school_verification_value', 'school_registration_verification_approved_by_beo');
    }
    elseif ($status && $status == 'approved') {
      $query->leftJoin('mini_node__field_school_verification', 'fsv', 'nfd.id = fsv.entity_id');
      $query->condition('fsv.field_school_verification_value', 'school_registration_verification_approved_by_deo');
    }
    elseif ($status && $status == 'mapping_completed') {
      $query->leftJoin('mini_node__field_habitations', 'fh', 'nfd.id = fh.entity_id');
      $query->condition('fh.field_habitations_target_id', NULL, 'IS NOT NULL');
    }
    elseif ($status && $status == 'mapping_pending') {
      $query->leftJoin('mini_node__field_habitations', 'fh', 'nfd.id = fh.entity_id');
      $query->condition('fh.field_habitations_target_id', NULL, 'IS NULL');
    }
    elseif (($status && $status == 'registered')) {
      $query->leftJoin('mini_node__field_habitations', 'fsv', 'nfd.id = fsv.entity_id');
    }

    // Execute the query and fetch results.
    $matching_schools = $query->execute()->fetchAll();

    if ($matching_schools) {
      return $matching_schools;
    }

    // Return [] if no match is found.
    return [];
  }

  /**
   * Function to calculate the list of locations.
   *
   * @param int $parent
   *   The parent location id.
   *
   * @return array
   *   Locations array.
   */
  public function locationList(?string $parent = NULL) {
    $route_name = $this->routeMatch->getRouteName();
    if (in_array($route_name, [
      'rte_mis_report.controller.school_registration_report',
      'rte_mis_report.school_information_report',
    ])) {
      $query = $this->database->select('taxonomy_term_field_data', 't')
        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
        ->limit(10);
    }
    else {
      $query = $this->database->select('taxonomy_term_field_data', 't');
    }

    $query->fields('t', ['tid', 'name'])
      ->condition('t.vid', 'location')
      ->orderBy('t.name', 'ASC');
    $query->leftJoin('taxonomy_term__parent', 'tp', 't.tid = tp.entity_id');
    $query->condition('tp.parent_target_id', $parent);

    $locations = $query->execute()->fetchAll();
    return $locations;
  }

  /**
   * Get the locations for a parent.
   *
   * @param string $current_role
   *   The current user role.
   * @param string $id
   *   The location id to get the school details for state & district.
   *   And the mini node id for block admin.
   *
   * @return array
   *   The count of total seats, rte seats and mediums in a particular
   *   location.
   */
  public function getLocationsForParent(string $current_role, ?string $id = NULL): array {
    $location_ids = [];
    if (in_array($current_role, ['state_admin', 'district_admin'])) {
      $location = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $id, NULL, FALSE) ?? NULL;
      if ($location) {
        foreach ($location as $value) {
          $location_ids[] = $value->tid;
        }
      }
    }

    return $location_ids;
  }

  /**
   * Get the seats count.
   *
   * @param array $location_ids
   *   The list of location ids.
   *
   * @return array
   *   The count of total seats, rte seats in a particular location.
   */
  public function getSeatsCount(array $location_ids = []): array {
    // Get all the registered schools
    // with location as $district_id.
    if ($location_ids) {
      // Get the language from default option config.
      $school_config = $this->configFactory->get('rte_mis_school.settings');
      $languages = $school_config->get('field_default_options.field_medium') ?? [];
      $total_seats = $total_rte_seats = 0;
      $query = $this->entityTypeManager->getStorage('mini_node')
        ->getQuery()
        ->condition('type', 'school_details')
        ->condition('field_school_verification', 'school_registration_verification_approved_by_deo')
        ->condition('field_location', $location_ids, 'IN')
        ->accessCheck(FALSE);

      $schools = $query->execute();
      foreach ($schools as $value) {
        // Get the seat information of each school
        // and add it to total seats.
        [$seats, $rte_seats] = $this->eachSchoolSeatCount($languages, $value);
        $total_seats += $seats;
        $total_rte_seats += $rte_seats;
      }
      return [$total_seats, $total_rte_seats];
    }

    return [0, 0];

  }

  /**
   * Query for the mapped habitation.
   *
   * @param string $udise_code_key
   *   The udise code id.
   * @param array $locations
   *   The list of locations.
   * @param string $additional
   *   Type of area.
   *
   * @return array
   *   School data.
   */
  public function mappedHabitationQuery(?string $udise_code_key = NULL, array $locations = [], ?string $additional = NULL) {
    $route_name = $this->routeMatch->getRouteName();
    // For view load data with a limit of 10.
    if ($route_name == 'rte_mis_report.controller.habitation_mapping') {
      $query = $this->database->select('mini_node_field_data', 'mnfd')
        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
        ->limit(10);
    }
    else {
      $query = $this->database->select('mini_node_field_data', 'mnfd');
    }

    $query->distinct()
      ->fields('mnfd', ['id'])
      ->condition('mnfd.type', 'school_details')
      ->condition('mnfd.status', 1);

    // Handle location.
    if ($locations) {
      // If locations are provided, filter by them.
      $query->leftJoin('mini_node__field_location', 'fl', 'mnfd.id = fl.entity_id');
      $query->condition('fl.field_location_target_id', $locations, 'IN');

      // Add joins for udise_code and
      // the taxonomy term if locations are provided.
      $query->leftJoin('mini_node__field_udise_code', 'fuc', 'mnfd.id = fuc.entity_id');
      $query->leftJoin('taxonomy_term_field_data', 'ttfd', 'fuc.field_udise_code_value = ttfd.tid');
      $query->addField('ttfd', 'name', 'udise_code');

      // Add additional fields as needed.
      $query->leftJoin('users_field_data', 'u', 'u.name = ttfd.name');
      $query->leftJoin('user__field_phone_number', 'ufp', 'ufp.entity_id = u.uid');
      $query->addField('ufp', 'field_phone_number_value', 'mobile_number');

      // Check condition for 'type_of_area'.
      if ($additional) {
        // Join with the field containing 'type_of_area' in the taxonomy term.
        $query->leftJoin('taxonomy_term__field_type_of_area', 'ftoa', 'fuc.field_udise_code_value = ftoa.entity_id');

        // Add condition to check if 'type_of_area' matches
        // the $additional parameter.
        $query->condition('ftoa.field_type_of_area_value', $additional);
      }

    }
    else {
      // If no locations are provided, check the udise_code_key if it exists.
      if ($udise_code_key) {
        $query->leftJoin('mini_node__field_udise_code', 'fuc', 'mnfd.id = fuc.entity_id');
        $query->condition('fuc.field_udise_code_value', $udise_code_key);

        // If udise_code_key is provided, get the UDISE code name.
        $query->leftJoin('taxonomy_term_field_data', 'ttfd', 'fuc.field_udise_code_value = ttfd.tid');
        $query->addField('ttfd', 'name', 'udise_code');

        // Add additional fields as needed.
        $query->leftJoin('users_field_data', 'u', 'u.name = ttfd.name');
        $query->leftJoin('user__field_phone_number', 'ufp', 'ufp.entity_id = u.uid');
        $query->addField('ufp', 'field_phone_number_value', 'mobile_number');
      }
    }

    // Join with the field_academic_year table.
    $query->leftJoin('mini_node__field_academic_year', 'fay', 'mnfd.id = fay.entity_id');
    $query->condition('fay.field_academic_year_value', _rte_mis_core_get_current_academic_year());

    $query->leftJoin('mini_node__field_school_verification', 'fsv', 'mnfd.id = fsv.entity_id');
    $query->condition('fsv.field_school_verification_value', 'school_registration_verification_approved_by_deo');

    // Join with mini_node__field_school_name to get the school name.
    $query->leftJoin('mini_node__field_school_name', 'msn', 'mnfd.id = msn.entity_id');
    $query->addField('msn', 'field_school_name_value', 'school_name');

    $query->leftJoin('mini_node__field_habitations', 'fh', 'mnfd.id = fh.entity_id');

    $query->leftJoin('taxonomy_term_field_data', 'ltd', 'FIND_IN_SET(ltd.tid, fh.field_habitations_target_id)');
    $query->addExpression("GROUP_CONCAT(DISTINCT ltd.name SEPARATOR ', ')", 'mapped_habitation');

    $query->groupBy('mnfd.id');
    $query->groupBy('ttfd.name');
    $query->groupBy('u.uid');
    $query->groupBy('ufp.field_phone_number_value');
    $query->groupBy('msn.field_school_name_value');

    // Execute the query and fetch results.
    $results = $query->execute()->fetchAll();
    return $results;
  }

  /**
   * Function to count the seats in each school.
   *
   * @param array $languages
   *   The languages from the config.
   * @param string $id
   *   The `id` of the school.
   *
   * @return array
   *   Return the count of seat in each school.
   */
  protected function eachSchoolSeatCount(array $languages, string $id): array {
    $total_seats = $total_rte_seats = 0;
    $school_details = $this->entityTypeManager->getStorage('mini_node')->load($id);
    // Check for both single and dual entry.
    foreach ($school_details->get('field_entry_class')->referencedEntities() as $entry_class) {
      foreach ($languages as $key => $language) {
        $seats[$entry_class->get('field_entry_class')->getString()]['seat'][$key] = $entry_class->get('field_total_student_for_' . $key)->getString();
        $rte_seats[$entry_class->get('field_entry_class')->getString()]['rte_seat'][$key] = $entry_class->get('field_rte_student_for_' . $key)->getString();
        $total_rte_seats += $rte_seats[$entry_class->get('field_entry_class')->getString()]['rte_seat'][$key];
        $total_seats += $seats[$entry_class->get('field_entry_class')->getString()]['seat'][$key];
      }
    }
    return [$total_seats, $total_rte_seats];
  }

  /**
   * Get the school boards count.
   *
   * @param array $location_ids
   *   The list of location ids.
   *
   * @return array
   *   The count of schools for each board.
   */
  public function getSchoolBoardsCount(array $location_ids = []): array {
    // Get all the registered schools
    // with location as $district_id.
    if ($location_ids) {
      $schools = $this->entityTypeManager->getStorage('mini_node')
        ->getAggregateQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'school_details')
        ->condition('field_school_verification', 'school_registration_verification_approved_by_deo')
        ->condition('field_location', $location_ids, 'IN')
        ->aggregate('field_board_type', 'COUNT')
        ->groupBy('field_board_type')
        ->execute();
    }

    $boards_count = [];
    // Build board type count array.
    foreach ($schools as $school) {
      $boards_count[$school['field_board_type']] = $school['field_board_type_count'];
    }

    return $boards_count;
  }

  /**
   * Get the school mediums count.
   *
   * @param array $location_ids
   *   The list of location ids.
   *
   * @return array
   *   The count of schools for each medium.
   */
  public function getSchoolMediumsCount(array $location_ids = []): array {
    // Get all the registered schools
    // with location as $district_id.
    if ($location_ids) {
      $schools = $this->entityTypeManager->getStorage('mini_node')
        ->getAggregateQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'school_details')
        ->condition('field_school_verification', 'school_registration_verification_approved_by_deo')
        ->condition('field_location', $location_ids, 'IN')
        ->aggregate('field_education_details.entity.field_medium', 'COUNT')
        ->groupBy('field_education_details.entity.field_medium')
        ->execute();
    }

    $mediums_count = [];
    // Build mediums count array.
    foreach ($schools as $school) {
      $mediums_count[$school['field_medium']] = $school['field_education_detailsentityfield_medium_count'];
    }

    return $mediums_count;
  }

  /**
   * Get the school education levels count.
   *
   * @param array $location_ids
   *   The list of location ids.
   *
   * @return array
   *   The count of schools for each board.
   */
  public function getSchoolEducationLevelsCount(array $location_ids = []): array {

    // Get all the registered schools
    // with location as $district_id.
    if ($location_ids) {
      $schools = $this->entityTypeManager->getStorage('mini_node')
        ->getAggregateQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'school_details')
        ->condition('field_school_verification', 'school_registration_verification_approved_by_deo')
        ->condition('field_location', $location_ids, 'IN')
        ->aggregate('field_education_details.entity.field_education_level', 'COUNT')
        ->groupBy('field_education_details.entity.field_education_level')
        ->execute();
    }

    $education_levels_count = [];
    // Build education levels count array.
    foreach ($schools as $school) {
      $education_levels_count[$school['field_education_level']] = $school['field_education_detailsentityfield_education_level_count'];
    }

    return $education_levels_count;
  }

  /**
   * Get the reimbursement claims for given status and location.
   *
   * @param array $location_ids
   *   The list of location ids.
   * @param string $status
   *   The reimbursement claim status.
   * @param string $id
   *   The school id.
   *
   * @return array
   *   The ids of school claim mini nodes.
   */
  public function getReimbursementClaims(array $location_ids = [], ?string $status = NULL, ?string $id = NULL): array {
    $claims = [];
    $query = $this->entityTypeManager->getStorage('mini_node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'school_claim')
      ->condition('field_school.entity.field_school_verification', 'school_registration_verification_approved_by_deo');

    // Apply condition for location ids.
    if (!empty($location_ids)) {
      $query->condition('field_school.entity.field_location', $location_ids, 'IN');
    }
    // Apply claim status condition if status is passed to the function.
    if ($status) {
      $query->condition('field_reimbursement_claim_status', $status);
    }
    // Apply condition for school id.
    if ($id) {
      $query->condition('field_school', $id);
    }
    // Execute the query.
    $claims = $query->execute();

    return $claims;
  }

  /**
   * Gets the school_admins based on the user's role and location.
   *
   * @param string $location_id
   *   The location id to get the student details.
   * @param string $query_params
   *   The key for which to check the status.
   *
   * @return array
   *   list of schools.
   */
  public function filterSchools(?string $location_id = NULL, array $query_params = []) {
    $location_tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $location_id, NULL, FALSE) ?? NULL;
    $locations = [];

    if ($location_tree) {
      foreach ($location_tree as $value) {
        $locations[] = $value->tid;
      }
    }

    $schools = [];
    // Query to filter out schools based on the query parameters and
    // location ids.
    if (!empty($locations)) {
      $query = $this->entityTypeManager->getStorage('mini_node')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'school_details')
        ->condition('field_location', $locations, 'IN');

      // Get current route name.
      $route_name = $this->routeMatch->getRouteName();
      // Use pager if current route is not export route.
      if ($route_name == 'rte_mis_report.school_information_report_school_list') {
        $query->pager(10);
      }

      // Add conditions based on filter, there can be multiple
      // filters as some can be added from main report page and
      // can also be filtered out manually from the form.
      // Board filter.
      if (isset($query_params['board'])) {
        $query->condition('field_board_type', $query_params['board']);
      }
      // Education level filter.
      if (isset($query_params['education_level'])) {
        $query->condition('field_education_details.entity.field_education_level', $query_params['education_level']);
      }
      // Medium filter.
      if (isset($query_params['medium'])) {
        $query->condition('field_education_details.entity.field_medium', $query_params['medium']);
      }
      $schools = $query->execute();
    }

    return $schools;
  }

  /**
   * Prepares data for schools.
   *
   * @param string $school_id
   *   The location id to get the student details.
   *
   * @return array
   *   School data.
   */
  public function prepareSchoolListData($school_id) {
    $data = [];
    $school = $this->entityTypeManager->getStorage('mini_node')->load($school_id);
    if ($school instanceof EckEntityInterface && $school->bundle() == 'school_details') {
      // Get the language from default option config.
      $school_config = $this->configFactory->get('rte_mis_school.settings');
      $languages = $school_config->get('field_default_options.field_medium') ?? [];
      $udise_id = $school->get('field_udise_code')->getString();
      $school_udise = $this->entityTypeManager->getStorage('taxonomy_term')->load($udise_id);
      if ($school_udise instanceof TermInterface) {
        $school_udise_code = $school_udise->getName();
      }
      $data['udise_code'] = $school_udise_code ?? '';
      $data['name'] = $school->get('field_school_name')->getString();
      // Get seats, rte seats and mediums count.
      [$seats, $rte_seats] = $this->eachSchoolSeatCount($languages, $school->id());
      $data['seats'] = $seats;
      $data['rte_seats'] = $rte_seats;
      // Check the board type.
      $data['board'] = $school->get('field_board_type')->getString() ?? '';
      foreach ($school->get('field_education_details')->referencedEntities() as $education_details) {
        $data['educational_levels'][$education_details->get('field_education_level')->getString()] = $education_details->get('field_education_level')->getString();
        $data['mediums'][$education_details->get('field_medium')->getString()] = $education_details->get('field_medium')->getString();
      }
    }

    return $data;
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
  public function eachSchoolSeatCountLanguage(array $languages, string $id) {
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

}
