<?php

namespace Drupal\rte_mis_demo_content;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\eck\EckEntityInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Drupal\user\Entity\User;

/**
 * Service that creates and deletes demo content.
 */
class TestData {

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
   * Demo Content Module Contructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $config_factory;
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
    // Register for campaign.
    $this->schoolCampaignRegister();
    // Mapping of schools.
    $this->schoolMapping();
  }

  /**
   * Function to check if a term already exists in a vocabulary.
   */
  public function termExists($term_name, $vocabulary) {
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(
      [
        'vid' => $vocabulary,
        'name' => $term_name,
      ]);
    return !empty($terms);
  }

  /**
   * Create Locations.
   */
  public function createLocations() {
    // Vocabulary ID (vid) of the taxonomy vocabulary.
    $vocabulary = 'location';

    // Create 2 districts.
    for ($district_id = 1; $district_id <= 2; $district_id++) {
      // Create district term if it doesn't exist.
      $district_name = 'District-' . $district_id;
      if (!$this->termExists($district_name, $vocabulary)) {
        $district_term = Term::create([
          'name' => $district_name,
          'vid' => $vocabulary,
        ]);
        $district_term->save();
      }

      // Create at least 2 blocks for each district.
      for ($block_id = 1; $block_id <= 2; $block_id++) {
        // Create block term if it doesn't exist.
        $block_name = 'Block-' . $district_id . '-' . $block_id;
        if (!$this->termExists($block_name, $vocabulary)) {
          $block_term = Term::create([
            'name' => $block_name,
            'vid' => $vocabulary,
            'parent' => [$district_term->id()],
          ]);
          $block_term->save();
        }

        // Create Nagriya Nikhaye for each block.
        for ($nagriya_id = 1; $nagriya_id < 2; $nagriya_id++) {
          // Create Nagriya Nikhaye term if it doesn't exist.
          $nagriya_name = 'Nagriya-Nikhaye-' . $district_id . '-' . $block_id . '-' . $nagriya_id;
          if (!$this->termExists($nagriya_name, $vocabulary)) {
            $nagriya_term = Term::create([
              'name' => $nagriya_name,
              'vid' => $vocabulary,
              'parent' => [$block_term->id()],
              'field_type_of_area' => ['value' => 'urban'],
            ]);
            $nagriya_term->save();
          }

          // Create 2 wards for each Nagriya Nikhaye.
          for ($ward_id = 1; $ward_id <= 2; $ward_id++) {
            // Create Ward term if it doesn't exist.
            $ward_name = 'Ward-' . $district_id . '-' . $block_id . '-' . $nagriya_id . '-' . $ward_id;
            if (!$this->termExists($ward_name, $vocabulary)) {
              $ward_term = Term::create([
                'name' => $ward_name,
                'vid' => $vocabulary,
                'parent' => [$nagriya_term->id()],
              ]);
              $ward_term->save();
            }

            // Create 3 habitations for each ward.
            for ($habitation_id = 1; $habitation_id < 3; $habitation_id++) {
              // Create Habitation term if it doesn't exist.
              $habitation_name = 'Habitation-' . $district_id . '-' . $block_id . '-' . $nagriya_id . '-' . $ward_id . '-' . $habitation_id;
              if (!$this->termExists($habitation_name, $vocabulary)) {
                $habitation_term = Term::create([
                  'name' => $habitation_name,
                  'vid' => $vocabulary,
                  'parent' => [$ward_term->id()],
                ]);
                $habitation_term->save();
              }
            }
          }
        }

        // Create Gram Panchayat for each block.
        $gram_panchayat_name = 'Gram-Panchayat-' . $district_id . '-' . $block_id;
        if (!$this->termExists($gram_panchayat_name, $vocabulary)) {
          $gram_panchayat_term = Term::create([
            'name' => $gram_panchayat_name,
            'vid' => $vocabulary,
            'parent' => [$block_term->id()],
            'field_type_of_area' => ['value' => 'rural'],
          ]);
          $gram_panchayat_term->save();
        }

        // Create 3 habitations for each Gram Panchayat.
        for ($habitation_id = 1; $habitation_id < 3; $habitation_id++) {
          // Create Habitation term if it doesn't exist.
          $habitation_gp_name = 'Habitation-GP-' . $district_id . '-' . $block_id . '-' . $habitation_id;
          if (!$this->termExists($habitation_gp_name, $vocabulary)) {
            $habitation_gp_term = Term::create([
              'name' => $habitation_gp_name,
              'vid' => $vocabulary,
              'parent' => [$gram_panchayat_term->id()],
            ]);
            $habitation_gp_term->save();
          }
        }
      }
    }
  }

  /**
   * Function to check if a school already exists with the given UDISE code.
   */
  public function schoolExists($udise_code, $vocabulary) {
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(
      ['vid' => $vocabulary, 'name' => $udise_code]);
    return !empty($terms);
  }

  /**
   * Create Udise Single Code.
   */
  public function createSingleUdise($iterator, $blockTid, $workflow_status, $type_of_area) {
    // Generate a random 11-digit UDISE code.
    $udise_code = str_repeat((string) $iterator, 11);

    // Generate school name.
    $school_name = 'School-' . $iterator;

    // Check if the school already exists with the same UDISE code.
    if (!$this->schoolExists($udise_code, 'school')) {
      // Create a new school term.
      $school_term = Term::create([
        'name' => $udise_code,
        'field_school_name' => $school_name,
        'vid' => 'school',
        'field_workflow' => 'school_workflow_approved',
        'field_upload_type' => 'bulk_upload',
        'field_minority_status' => 'non_minority',
        'field_type_of_area' => $type_of_area,
        'field_aid_status' => 'unaided',
        'field_location' => $blockTid,
      ]);
      $school_term->save();
    }
  }

  /**
   * Create Udise Codes.
   */
  public function createUdiseCodes() {
    $query = $this->entityTypeManager->getStorage('taxonomy_term')
      ->getQuery()
      ->condition('vid', 'location')
      ->condition('name', 'Block-1-1', 'LIKE')
      ->accessCheck(FALSE);
    $blockTid = $query->execute();

    $udise_data = [
      ['status' => 'school_workflow_approved', 'area' => 'urban'],
      ['status' => 'school_workflow_approved', 'area' => 'rural'],
      ['status' => 'school_workflow_approved', 'area' => 'urban'],
      ['status' => 'school_workflow_approved', 'area' => 'urban'],
      ['status' => 'school_workflow_approved', 'area' => 'rural'],
      ['status' => 'school_workflow_pending', 'area' => 'urban'],
      ['status' => 'school_workflow_pending', 'area' => 'rural'],
    ];

    $i = 1;
    // Create single udise codes with different conditions.
    foreach ($udise_data as $data) {
      $this->createSingleUdise($i, $blockTid, $data['status'], $data['area']);
      $i++;
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
        "type" => "academic_session",
        "field_academic_year" => '2024_25',
      ]);
      $paragraphs = $this->getTimelineParagraphs();
      $node->set("field_session_details", $paragraphs)->save();

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
    // Create State Admin.
    $this->adminCreateUser('State-1', 'state_admin');

    // Create District admins.
    $this->adminCreateUser('District-1', 'district_admin');
    $this->adminCreateUser('District-2', 'district_admin');

    // Create Block Admins.
    $this->adminCreateUser('Block-1-1', 'block_admin');
    $this->adminCreateUser('Block-1-2', 'block_admin');
    $this->adminCreateUser('Block-2-1', 'block_admin');
    $this->adminCreateUser('Block-2-2', 'block_admin');

    // Create School Users.
    $this->schoolUserCreate();
  }

  /**
   * Create Single Admin User.
   */
  public function adminCreateUser($location = NULL, $role = NULL) {
    // Fetch the taxonomy term ID for `$location`
    // under the 'location' vocabulary.
    $query = $this->entityTypeManager->getStorage('taxonomy_term')
      ->getQuery()
      ->condition('vid', 'location')
      ->condition('name', $location, 'LIKE')
      ->accessCheck(FALSE);
    $blockTid = $query->execute();

    // If there's only one term returned
    // get its ID, otherwise handle accordingly.
    $blockTid = !empty($blockTid) ? reset($blockTid) : NULL;

    // Generate the email.
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
   * Get Location for school.
   */
  public function getLocationDetails($term_id) {
    // Varaiable to store the value.
    $mini_node_location = [];
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    if ($term_id) {
      $school_udise = $term_storage->load($term_id);
      if ($school_udise instanceof TermInterface) {
        $type_of_area = $school_udise->get('field_type_of_area')->getString();
        // If urban set the location as 'Habitation-1-1-1-1-1'.
        if ($type_of_area == 'urban') {
          $location_query = $term_storage
            ->getQuery()
            ->condition('name', 'Habitation-1-1-1-1-1')
            ->accessCheck(FALSE);
          $location_tids = $location_query->execute();
        }
        // If rural then the location is set to `'Habitation-GP-1-1-1'.
        else {
          $location_query = $term_storage
            ->getQuery()
            ->condition('name', 'Habitation-GP-1-1-1')
            ->accessCheck(FALSE);
          $location_tids = $location_query->execute();
        }
        $mini_node_location[] = [
          'target_id' => array_key_first($location_tids),
        ];
      }
    }
    return $mini_node_location;
  }

  /**
   * Create Entry Class Details.
   */
  public function getEntryClass() {
    $school_config = $this->configFactory->get('rte_mis_school.settings');
    $entry_class = [];
    // Create entry class paragraph for campaign registration.
    $paragraph = Paragraph::create([
      'type' => 'entry_class',
      'field_entry_class' => [
        'value' => 3,
      ],
      'field_education_type' => [
        'value' => 'co-ed',
      ],

    ]);
    // Set dynamic parameters.
    $languages = $school_config->get('field_default_options.field_medium') ?? [];
    foreach ($languages as $key => $value) {
      $paragraph->set('field_total_student_for_' . $key, ['value' => 40]);
      $paragraph->set('field_rte_student_for_' . $key, ['value' => 10]);
    }

    $paragraph->save();
    $entry_class[] = [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->id(),
    ];
    return $entry_class;
  }

  /**
   * Create School Mini Node.
   */
  public function createSchoolMiniNode($term_id, $term_name) {
    // Variable to store value.
    $school_mini_node = [];
    $storage = $this->entityTypeManager->getStorage('mini_node');
    $mini_node = $storage->create([
      'type' => 'school_details',
      'field_udise_code' => $term_id,
      'field_school_name' => $term_name,
    ]);
    $mini_node->save();
    $school_mini_node[] = [
      'target_id' => $mini_node->id(),
    ];

    return $school_mini_node;
  }

  /**
   * School User create.
   */
  public function schoolUserCreate() {
    // Fetch the first four schools.
    $query = $this->entityTypeManager->getStorage('taxonomy_term')
      ->getQuery()
      ->condition('vid', 'school')
      ->condition('field_workflow', 'school_workflow_approved')
      ->range(0, 5)
      ->accessCheck(FALSE);
    $schoolTids = $query->execute();

    foreach ($schoolTids as $schoolTid) {
      $schoolTerm = $this->entityTypeManager->getStorage('taxonomy_term')->load($schoolTid);
      if ($schoolTerm instanceof TermInterface) {

        $schoolName = $schoolTerm->get('field_school_name')->getString();
        $email = strtolower($schoolName) . '@example.com';

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
          $school_details = $this->createSchoolMiniNode($schoolTid, $schoolName);
          $user = User::create();
          // Set the user data.
          $user->setUsername($schoolName);
          $user->setEmail($email);
          $user->setPassword('Inno@1234');

          // Set additional fields from the $data array.
          $user->set('field_phone_number', $phoneNumber);
          $user->set('field_school_details', $school_details);

          $user->set('status', 1);

          $user->save();
        }
      }
    }
  }

  /**
   * Register for campaign & verification .
   */
  public function schoolCampaignRegister() {
    // Get the users.
    $query = $this->entityTypeManager->getStorage('user')
      ->getQuery()
      ->condition('roles', 'school')
      ->condition('mail', '%@example.com', 'LIKE')
      ->range(0, 4)
      ->accessCheck(FALSE);
    $active_schools = $query->execute();
    $active_schools = array_keys($active_schools);

    // Register for campaign with different parameters.
    $this->campaignRegisterData($active_schools[0], 'school_registration_verification_approved_by_deo', 'school_admin');
    $this->campaignRegisterData($active_schools[1], 'school_registration_verification_approved_by_deo', 'school_admin');
    $this->campaignRegisterData($active_schools[2], 'school_registration_verification_approved_by_beo', 'school');
    $this->campaignRegisterData($active_schools[3], 'school_registration_verification_submitted', 'school');

  }

  /**
   * Set the Campaign Data.
   */
  public function campaignRegisterData($school_user, $verification_status, $user_role) {
    $value = $this->entityTypeManager->getStorage('user')->load($school_user);
    $mini_node = $value->get('field_school_details')->referencedEntities();
    $mini_node = reset($mini_node);
    if ($mini_node instanceof EckEntityInterface) {
      $school_id = $mini_node->get('field_udise_code')->getString();
      $school_term_id = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
        'vid' => 'school',
        'tid' => $school_id,
      ]);
      // Registration entry details.
      $campaign_data = [
        'type' => 'school_details',
        'field_school_verification' => $verification_status,
        'field_default_entry_class' => '1st',
        'field_academic_year' => _rte_mis_core_get_current_academic_year(),
        'field_pincode' => '876543',
        'field_school_recognition_number' => rand(10000000000, 99999999999),
        'field_recognition_year' => '2020',
        'field_school_administrator_name' => 'Test User',
        'field_school_administrator_desig' => 'Principal',
        'field_full_address' => '20/C, MG Road',
        'field_geolocation' => [
          'lat' => '23',
          'lng' => '34',
          'lat_sin' => '0.39073112848927',
          'lat_cos' => '0.92050485345244',
          'lng_rad' => '0.59341194567807',
          'data' => NULL,
        ],
        'field_location' => $this->getLocationDetails(array_key_first($school_term_id)),
        'field_entry_class' => $this->getEntryClass(),
      ];
      foreach ($campaign_data as $key => $values) {
        $mini_node->set($key, $values);
      }
      $mini_node->save();
    }
    // Remove role school.
    $value->removeRole('school');
    $value->addRole($user_role);
    $value->save();
  }

  /**
   * Set the habitation details.
   */
  public function setHabitation($type_of_area) {
    $mapped_habitation = [];
    $habitation_tids = [];
    // Fetch habitation ids.
    $query = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()
      ->condition('vid', 'location')
      ->condition('name', [
        'Habitation-1-1-1-1-2',
        'Habitation-1-1-1-2-2',
        'Habitation-1-2-1-1-1',
        'Habitation-GP-1-1-2',
        'Habitation-GP-1-2-1',
        'Habitation-GP-1-2-2',
      ], 'IN')
      ->accessCheck(FALSE);

    $habitation_tids = $query->execute();

    foreach ($habitation_tids as $tid) {
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
      if ($term instanceof TermInterface) {
        $term_name = $term->getName();
        // For urban set to Nagriyae Nikae Habitation
        // else for rural set to Gram Panchayat Habitation.
        if (($type_of_area == 'urban' && in_array($term_name,
        [
          'Habitation-1-1-1-1-2',
          'Habitation-1-1-1-2-2',
          'Habitation-1-2-1-1-1',
        ])) ||
        ($type_of_area == 'rural' && in_array($term_name,
        [
          'Habitation-GP-1-1-2',
          'Habitation-GP-1-2-1',
          'Habitation-GP-1-2-2',
        ]))) {
          $mapped_habitation[] = ['target_id' => $tid];
        }
      }
    }

    return $mapped_habitation;
  }

  /**
   * Mapping of schools.
   */
  public function schoolMapping() {
    // Load users.
    $query = $this->entityTypeManager->getStorage('user')
      ->getQuery()
      ->condition('roles', 'school_admin')
      ->condition('mail', '%@example.com', 'LIKE')
      ->range(0, 4)
      ->accessCheck(FALSE);
    $verified_schools = $query->execute();

    // From user get mini node details.
    foreach ($verified_schools as $school) {
      $value = $this->entityTypeManager->getStorage('user')->load($school);
      $mini_node = $value->get('field_school_details')->referencedEntities();
      $mini_node = reset($mini_node);

      if ($mini_node instanceof EckEntityInterface) {
        // From mini node get the taxonomy term details.
        $mini_node_udise_code = $mini_node->get('field_udise_code')->getString();
        $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

        $school_udise = $term_storage->load($mini_node_udise_code);
        if ($school_udise instanceof TermInterface) {
          $type_of_area = $school_udise->get('field_type_of_area')->getString();
          $mini_node->set('field_habitations', $this->setHabitation($type_of_area));
          $mini_node->save();
        }
      }
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
    $this->deleteNodes('mini_node', 'school_details');
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

    $this->singleUserDelete('State-1@example.com');
    $this->singleUserDelete('District-1@example.com');
    $this->singleUserDelete('District-2@example.com');
    $this->singleUserDelete('Block-1-1@example.com');
    $this->singleUserDelete('Block-1-2@example.com');
    $this->singleUserDelete('Block-2-1@example.com');
    $this->singleUserDelete('Block-2-2@example.com');

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

}
