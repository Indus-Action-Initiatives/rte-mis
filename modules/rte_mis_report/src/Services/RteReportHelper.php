<?php

namespace Drupal\rte_mis_report\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eck\EckEntityInterface;
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
   * Constructs a RteAllocationHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user account.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $currentUser) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $currentUser;
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
   * Gets the school_admins based on the user's role and location.
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
      $query = $this->entityTypeManager->getStorage('taxonomy_term')
        ->getQuery()
        ->condition('status', 1)
        ->condition('vid', 'school')
        ->condition('field_location', $locations, 'IN')
        ->condition('field_workflow', 'school_workflow_approved')
        ->accessCheck(FALSE);

      $schools = $query->execute();

      // Return an array of all the schools under a location id.
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
  public function getSchoolStatus(string $current_role = NULL, string $status_key = NULL): int {
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
  public function checkSchoolStatus(string $school_id = NULL): array {
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

}
