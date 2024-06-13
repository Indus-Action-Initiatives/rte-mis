<?php

namespace Drupal\rte_mis_demo_content;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\rte_mis_core\Batch\LocationTermBatch;
use Drupal\rte_mis_school\Batch\SchoolBatch;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Drupal\user\Entity\User;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Service that creates and deletes demo content.
 */
class TestData {
  use StringTranslationTrait;

  /**
   * The entity type manager.
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
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $moduleHandler, AccountInterface $current_user) {
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $config_factory;
    $this->moduleHandler = $moduleHandler;
    $this->currentUser = $current_user;
  }

  /**
   * Create Test Data.
   */
  public function createData() {
    // Create Locations.
    $this->createLocations();
    // Create Udise Codes.
    $this->createUdiseCodes();
    // Create Academic Session.
    $this->createAcademicSession();
    // Create Users.
    $this->createUsers();
  }

  /**
   * Create Locations.
   */
  public function createLocations() {
    // Create a new Reader of the type that has been identified.
    $reader = IOFactory::createReader('Xlsx');
    // Load $inputFileName to a Spreadsheet Object.
    $reader->setReadDataOnly(TRUE);
    $reader->setReadEmptyCells(FALSE);
    $modulePath = $this->moduleHandler->getModule('rte_mis_demo_content')->getPath();
    $samplePath = $modulePath . '/asset/location_demo.xlsx';
    $spreadsheet = $reader->load($samplePath);
    $sheetData = $spreadsheet->getActiveSheet();
    // Get the maximum number of row with data.
    $maxRow = $sheetData->getHighestDataRow();
    // Initialize batch process if already not in-progress.
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = ($maxRow - 1);
      $context['sandbox']['objects'] = $maxRow == 1 ? [] : range(2, $maxRow);
    }
    // Process 50 or item remaining.
    $count = min(50, count($context['sandbox']['objects']));
    // Initialize with 0s for all levels.
    $lastNonEmptyParents = array_fill(0, 7, 0);
    for ($i = 1; $i <= $count; $i++) {
      $rowNumber = array_shift($context['sandbox']['objects']);
      // Iterate 7 level and import the location from the file.
      for ($j = 1; $j < 7; $j++) {
        // If value exist at index 6 and categorization is rural, break loop.
        if ($j == 6 && $lastNonEmptyParents[$j - 3] == 'rural') {
          break;
        }
        // Get the value from sheet.
        $value = $sheetData->getCell([$j, $rowNumber])->getValue() ?? '';
        if (!empty($value)) {
          $value = trim($value);
        }
        // Check if location exist or not.
        $existingTerms = LocationTermBatch::checkIfLocationExist($value);
        if (!empty($existingTerms) && $j > 4) {
          // If location exists and the value from 5th and further column is
          // being imported then load the parent of existing term and store in
          // array.
          $parentTermId = [];
          foreach ($existingTerms as $existingTerm) {
            $parentsTerms = $this->entityTypeManager->getStorage('taxonomy_term')->loadParents($existingTerm);
            $parentsTerm = reset($parentsTerms);
            if ($parentsTerm instanceof TermInterface) {
              $parentTermId[] = $parentsTerm->id();
            }
          }
        }
        // This condition is used to created term based on several conditions.
        // 1. Existing term is empty `or` there exists term `and` current term
        // is 5th and greater `and` parents from existing term is not in the
        // list of previously added parent.
        // 2. Value is not empty.
        // 3. Current index is not 3. It will contain categorization element.
        // 4. Last added element is not FALSE `and` current index is not 4
        // `or`  Current index is 4 `and` Last added element is not FALSE
        // `and` Last second added element is not FALSE(verify category and
        // last element).
        if ((empty($existingTerms) || (!empty($existingTerms) && $j > 4 && !in_array($lastNonEmptyParents[$j - 1], $parentTermId))) && !empty($value) && $j != 3 && (($lastNonEmptyParents[$j - 1] !== FALSE && $j != 4) || ($j == 4 && $lastNonEmptyParents[$j - 2] !== FALSE && $lastNonEmptyParents[$j - 1]))) {
          $data = [
            'name' => $value,
            'vid' => 'location',
            'parent' => [$lastNonEmptyParents[$j - 1]],
          ];

          if ($j == 4) {
            $data += [
              'field_type_of_area' => $lastNonEmptyParents[$j - 1],
            ];
            $data['parent'] = [$lastNonEmptyParents[$j - 2]];
          }
          $term = Term::create($data);
          $term->save();
        }
        else {
          $existingTermId = reset($existingTerms);
          $parentsTerm = $this->entityTypeManager->getStorage('taxonomy_term')->loadParents($existingTermId);
          if (!empty($parentsTerm)) {
            $parentsTerm = reset($parentsTerm);
            if ($parentsTerm instanceof TermInterface) {
              if (($j == 4 && $parentsTerm->id() != $lastNonEmptyParents[$j - 2]) || ($j != 4 && $parentsTerm->id() != $lastNonEmptyParents[$j - 1])) {
                $term = $existingTerms  = NULL;
                $lastNonEmptyParents[$j] = FALSE;
              }
            }
          }
          // Create error for duplicate term.
          elseif ($j > 1 && $j != 3 && !empty($value)) {
            $lastNonEmptyParents[$j] = FALSE;
            $term = $existingTerms  = NULL;
          }
        }
        // Update lastNonEmptyParents based on current and previous level.
        if (!empty($value) && (!empty($existingTerms) || $term instanceof TermInterface || $j == 3)) {
          $lastNonEmptyParents[$j] = isset($term) ? $term->id() : (!empty($existingTerms) ? reset($existingTerms) : ($j == 3 && in_array(strtolower($value), [
            'rural',
            'urban',
          ]) ? strtolower($value) : FALSE));
          $term = NULL;
        }
      }

      // Update our progress information.
      $context['sandbox']['progress']++;
      $context['message'] = $this->t(
        'Completed @current out of @max',
        [
          '@current' => $context['sandbox']['progress'],
          '@max' => $context['sandbox']['max'],
        ]
      );
    }
    // Inform the batch engine that we are not finished,
    // and provide an estimation of the completion level we reached.
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Create Udise Codes.
   */
  public function createUdiseCodes() {
    $reader = IOFactory::createReader('Xlsx');
    // Load $inputFileName to a Spreadsheet Object.
    $reader->setReadDataOnly(TRUE);
    $reader->setReadEmptyCells(FALSE);
    $modulePath = $this->moduleHandler->getModule('rte_mis_demo_content')->getPath();
    $samplePath = $modulePath . '/asset/upload-bulk-school-udise.xlsx';
    $spreadsheet = $reader->load($samplePath);
    $sheetData = $spreadsheet->getActiveSheet();
    // Get the maximum number of row with data.
    $maxRow = $sheetData->getHighestDataRow();
    // Initialize batch process if already not in-progress.
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = ($maxRow - 1);
      $context['sandbox']['objects'] = $maxRow == 1 ? [] : range(2, $maxRow);
    }
    // Process 50 or item remaining.
    $count = min(50, count($context['sandbox']['objects']));
    for ($i = 1; $i <= $count; $i++) {
      $rowNumber = array_shift($context['sandbox']['objects']);
      // Get the UDISE code from first column.
      $udiseCode = $sheetData->getCell([1, $rowNumber])->getValue();
      // Get the school name from second column.
      $schoolName = $sheetData->getCell([2, $rowNumber])->getValue();
      // Get the aid status from third column.
      $aidStatus = $sheetData->getCell([3, $rowNumber])->getValue();
      // Get the minority status name from fourth column.
      $minorityStatus = $sheetData->getCell([4, $rowNumber])->getValue();
      // Get the type of area from fifth column.
      $typeOfArea = $sheetData->getCell([5, $rowNumber])->getValue();
      // Get the type of area from sixth column.
      $district = $sheetData->getCell([6, $rowNumber])->getValue();
      // Get the type of area from seventh column.
      $block = $sheetData->getCell([7, $rowNumber])->getValue();
      // Validate parameter before creating school udise code.
      $validAidStatus = SchoolBatch::getValidListValue($aidStatus, 'field_aid_status');
      $validMinorityStatus = SchoolBatch::getValidListValue($minorityStatus, 'field_minority_status');
      $validTypeOfArea = SchoolBatch::getValidListValue($typeOfArea, 'field_type_of_area');
      $blockTid = SchoolBatch::getBlockIdLocation($district, $block);
      $userId = $this->currentUser->id();
      if (!empty(trim($udiseCode)) && !empty(trim($schoolName)) && is_numeric($udiseCode) && strlen($udiseCode) === 11
      && $validAidStatus && $validTypeOfArea && $validMinorityStatus && $blockTid) {
        $existingTerm = NULL;
        if (empty($existingTerm)) {
          // Create new UDISE code if it does not exist.
          // Also store user ip, mark upload_type as `bulk_upload` and
          // set workflow status to `approved`.
          $term = Term::create([
            'name' => trim($udiseCode),
            'field_school_name' => trim($schoolName),
            'vid' => 'school',
            'field_workflow' => 'school_workflow_approved',
            'field_upload_type' => 'bulk_upload',
            'field_minority_status' => $validMinorityStatus,
            'field_type_of_area' => $validTypeOfArea,
            'field_aid_status' => $validAidStatus,
            'field_location' => $blockTid,
          ]);
          $term->setRevisionUser($this->entityTypeManager->getStorage('user')->load($userId));
          $term->save();
        }
      }
      // Update our progress information.
      $context['sandbox']['progress']++;
      $context['message'] = $this->t(
        'Completed @current out of @max',
        [
          '@current' => $context['sandbox']['progress'],
          '@max' => $context['sandbox']['max'],
        ]
      );
    }
    // Inform the batch engine that we are not finished,
    // and provide an estimation of the completion level we reached.
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Create Academic Sessions.
   */
  public function createAcademicSession() {
    $storage = $this->entityTypeManager->getStorage('mini_node');
    // Check for existing active campaign before creating one.
    $existing_active_campaign = $storage->loadByProperties([
      'type' => 'academic_session',
      'field_academic_year' => '2024_25',
      'status' => 1,
    ]);
    if (empty($existing_active_campaign)) {
      $node = $storage->create([
        'type' => 'academic_session',
        'field_academic_year' => '2024_25',
      ]);
      $paragraphs = $this->getTimelineParagraphs();
      $node->set('field_session_details', $paragraphs)->save();

    }
  }

  /**
   * Create the paragraph of type timeline.
   */
  public function getTimelineParagraphs() {
    $campaign_details = [];
    // Calculate the end date (20 days from the current date).
    $current_date = new \DateTime();
    $end_date = clone $current_date;
    $end_date->modify('+20 days');

    $current_date_string = $current_date->format('Y-m-d');
    $end_date_string = $end_date->format('Y-m-d');

    $events = [
      'school_registration',
      'school_verification',
      'school_mapping',
      'student_application',
    ];
    for ($i = 0; $i < 4; $i++) {
      $paragraph = Paragraph::create([
        'type' => 'timeline',
        'field_date' => [
          'value' => $current_date_string,
          'end_value' => $end_date_string,
        ],
        'field_event_type' => [
          'value' => $events[$i],
        ],
      ]);
      $paragraph->save();
      $campaign_details[$i] = [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->id(),
      ];
    }

    return $campaign_details;
  }

  /**
   * Create Admin Users.
   */
  public function createUsers() {
    $user_query = $this->entityTypeManager->getStorage('user')
      ->getQuery()
      ->condition('roles', ['state_admin'], 'IN')
      ->accessCheck(FALSE);
    $user_exists = $user_query->execute();
    if (!$user_exists) {
      // Create State Admin if user with similar role doesnot exists.
      $this->adminCreateUser('State 1', 'state_admin');
    }

    // Create District admins.
    $this->adminCreateUser('Baloda Bazaar-Bhatapara', 'district_admin');
    $this->adminCreateUser('Balrampur', 'district_admin');

    // Create Block Admins.
    $this->adminCreateUser('Palari', 'block_admin');
    $this->adminCreateUser('Rajapur', 'block_admin');
    $this->adminCreateUser('Ramachandrapur (Ramanujaganj)', 'block_admin');
    $this->adminCreateUser('Shankaragadha', 'block_admin');

  }

  /**
   * Create Single Admin User.
   */
  public function adminCreateUser($location = NULL, $role = NULL) {
    if ($location) {
      // Fetch the taxonomy term ID for `$location`
      // under the 'location' vocabulary.
      $query = $this->entityTypeManager->getStorage('taxonomy_term')
        ->getQuery()
        ->condition('vid', 'location')
        ->condition('name', $location, 'LIKE')
        ->accessCheck(FALSE);
      $blockTid = $query->execute();
    }

    // If there's only one term returned
    // get its ID, otherwise handle accordingly.
    $blockTid = !empty($blockTid) ? reset($blockTid) : NULL;

    // Generate the email.
    $search = [' ', '(', ')', '-'];
    $location = strtolower(str_replace($search, '_', $location));
    $email = $location . '@example.com';

    // Check if a user with the same email already exists.
    $user_query = $this->entityTypeManager->getStorage('user')
      ->getQuery()
      ->condition('mail', $email)
      ->range(0, 1)
      ->accessCheck(FALSE);
    $user_exists = $user_query->execute();

    if (empty($user_exists)) {
      // Generate a random phone number.
      $phoneNumber = rand(1000000000, 9999999999);
      $user = User::create();
      // Set the user data.
      $user->setUsername($location . '-officer');
      $user->setEmail($email);
      $user->setPassword('Inno@1234');
      $user->addRole($role);
      // Set additional fields value.
      $user->set('field_location_details', $blockTid ?? NULL);
      $user->set('field_phone_number', $phoneNumber);
      $user->set('status', 1);
      // Save the user.
      $user->save();
    }
  }

  /**
   * Delete Test Data.
   */
  public function deleteData() {
    $this->deleteUsers();
    $this->deleteTerms('location');
    $this->deleteTerms('school');
    $this->deleteParagraphs('timeline');
    $this->deleteNodes('mini_node', 'academic_session');
  }

  /**
   * Delete Terms.
   */
  public function deleteTerms($vocabulary) {
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vocabulary);
    foreach ($terms as $term) {
      try {
        $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
        $term_entity = $term_storage->load($term->tid);
        if ($term_entity) {
          $term_entity->delete();
        }
      }
      catch (Exception $e) {
        dump($e->getMessage());
      }
    }
  }

  /**
   * Delete Users.
   */
  public function deleteUsers() {
    $query = $this->entityTypeManager->getStorage('user')
      ->getQuery()
      ->condition('roles', ['school', 'school_admin'], 'IN')
      ->condition('mail', '%@example.com', 'LIKE')
      ->accessCheck(FALSE);
    $school_users = $query->execute();

    $this->singleUserDelete('state_admin@example.com');
    $this->singleUserDelete('balrampur@example.com');
    $this->singleUserDelete('baloda_bazaar_bhatapara@example.com');
    $this->singleUserDelete('shankaragadha@example.com');
    $this->singleUserDelete('ramachandrapur__ramanujaganj_@example.com');
    $this->singleUserDelete('rajapur@example.com');
    $this->singleUserDelete('palari@example.com');

    foreach ($school_users as $user) {
      $user_detail = $this->entityTypeManager->getStorage('user')->load($user);
      $email = $user_detail->getEmail();
      $this->singleUserDelete($email);
    }
  }

  /**
   * Single User delete.
   */
  protected function singleUserDelete($email) {
    $user_storage = $this->entityTypeManager->getStorage('user');
    $user_query = $user_storage->getQuery()
      ->condition('mail', $email)
      ->accessCheck(FALSE);
    $uid = $user_query->execute();
    if ($uid) {
      $user = $user_storage->load(reset($uid));
      $user->delete();
    }
  }

  /**
   * Delete Paragraphs.
   */
  protected function deleteParagraphs($type) {
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    $query = $paragraph_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $type);
    $paragraph_ids = $query->execute();
    if (!empty($paragraph_ids)) {
      foreach ($paragraph_ids as $paragraph_id) {
        $paragraph_storage->load($paragraph_id)->delete();
      }
    }
  }

  /**
   * Delete mini nodes.
   */
  public function deleteNodes($entityType, $bundle) {
    $node_storage = $this->entityTypeManager->getStorage($entityType);
    $query = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundle);
    $node_ids = $query->execute();
    if (!empty($node_ids)) {
      foreach ($node_ids as $node_id) {
        $node_storage->load($node_id)->delete();
      }
    }
  }

}
