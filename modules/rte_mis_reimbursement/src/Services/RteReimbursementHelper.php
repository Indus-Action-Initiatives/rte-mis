<?php

namespace Drupal\rte_mis_reimbursement\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eck\EckEntityInterface;
use Drupal\workflow\Entity\WorkflowTransitionInterface;

/**
 * Class RteReimbursementHelper.
 *
 * Provides helper functions for rte mis allocation module.
 */
class RteReimbursementHelper {

  use StringTranslationTrait;

  /**
   * Array of possible states transitions for single level approval.
   *
   * @var array
   */
  const POSSIBLE_TRANSITIONS = [
    // From submitted state.
    'reimbursement_claim_workflow_submitted' => [
      'reimbursement_claim_workflow_approved_by_beo',
      'reimbursement_claim_workflow_rejected',
    ],
    // From BEO approved state.
    'reimbursement_claim_workflow_approved_by_beo' => [
      'reimbursement_claim_workflow_approved_by_deo',
      'reimbursement_claim_workflow_rejected',
      'reimbursement_claim_workflow_submitted',
    ],
  ];

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  public $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The user account service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new RteReimbursementHelper object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, AccountInterface $account) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $account;
  }

  /**
   * Determines whether single level approval is enabled or not.
   *
   * @return bool
   *   TRUE if single level approval is enabled, FALSE otherwise.
   */
  public function isSingleLevelApprovalEnabled(): bool {
    // Get approval level from reimbursement config if not set
    // we consider it as 'dual' level.
    $approval_level = $this->configFactory->get('rte_mis_reimbursement.settings')->get('approval_level') ?? '';

    return $approval_level == 'single';
  }

  /**
   * Act on workflow `reimbursement_claim_workflow`.
   *
   * This function makes transition from different states when single level
   * approval is enabled for reimbursement.
   *
   * @param \Drupal\workflow\Entity\WorkflowTransitionInterface $transition
   *   The transition object.
   */
  public function processSingleLevelApproval(WorkflowTransitionInterface $transition): void {
    // This array contains keys as from states and values is possible states
    // transition.
    $possible_transition = self::POSSIBLE_TRANSITIONS;
    // Get the from sid.
    $from_sid = $transition->getFromSid();
    // Get the to sid.
    $to_sid = $transition->getToSid();
    $to_sids = $possible_transition[$from_sid] ?? NULL;
    if (in_array($to_sid, $to_sids)) {
      // Execute the transition, mark this as force as we are overriding
      // workflow.
      $transition->execute(TRUE);
    }
  }

  /**
   * Function to return the table both heading and rows.
   *
   * @param string $school_id
   *   School Id.
   * @param string $academic_year
   *   Academic Year.
   * @param string $approval_authority
   *   Approval Authority.
   * @param array $additional_fees
   *   Additional Fees Information.
   *
   * @return array
   *   Returns the data for rows.
   */
  public function loadStudentData(?string $school_id = NULL, ?string $academic_year = NULL, ?string $approval_authority = NULL, array $additional_fees = []): array {
    $data = [];
    // Get the list of all the classes from config.
    $school_config = $this->configFactory->get('rte_mis_school.settings');
    $config_class_list = $school_config->get('field_default_options.class_level');
    $class_list = array_keys($config_class_list);
    $class_list_selected = $this->getClassList($approval_authority);
    $class_list = array_intersect($class_list, !empty($class_list_selected) ? $class_list_selected : $class_list);
    $current_user_entity = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $user_linked_school = $school_id;
    $current_user_roles = $this->currentUser->getRoles(TRUE);
    if ($school_id == NULL && in_array('school_admin', $current_user_roles)) {
      $user_linked_school = $current_user_entity->get('field_school_details')->getString();
    }
    $node_ids = $this->getStudentList($academic_year, $class_list, $user_linked_school);
    // Process nodes in chunks, for large data set.
    $node_chunks = array_chunk($node_ids, 100);

    // Current user fee details.
    $current_user_fee_details = $this->schoolFeeDetails($user_linked_school);
    // Academic Year and Approval Authority is set,
    // then calculate the government fee.
    if (isset($academic_year) && isset($approval_authority)) {
      // Government fees will be calculated by form_state authority,
      // current academic year.
      $government_fee = $this->stateDefinedFees($academic_year, $approval_authority);
    }
    $slno = 1;
    foreach ($node_chunks as $chunk) {
      $rows = [];
      $rows['slno'] = $slno;
      $student_performance_entities = $this->entityTypeManager->getStorage('mini_node')->loadMultiple($chunk);
      foreach ($student_performance_entities as $student_performance_entity) {
        $rows = [];
        $row_keys = ['field_student_name', 'field_parent_name', 'field_current_class', 'field_medium', 'field_gender'];
        $row_values = [];
        foreach ($row_keys as $value) {
          $row_values[$value] = $student_performance_entity->get($value)->getString();
        }
        $class_value = $row_values['field_current_class'];
        $student_medium = $row_values['field_medium'];
        $student_gender = $row_values['field_gender'];
        $rows['slno'] = $slno;
        // Student Name.
        $rows['student_name'] = $row_values['field_student_name'];
        // Parent Name.
        $rows['parent_name'] = $row_values['field_parent_name'];
        // Class.
        $rows['current_class'] = ucwords($config_class_list[$class_value]);
        // Medium.
        $rows['field_medium'] = $student_medium;
        // School Tution Fee.
        $rows['school_tution_fee'] = $this->schoolTutionDetails($current_user_fee_details, $student_gender, $student_medium, $class_value) ?? 0;
        $government_tution_fees = $government_fee['tution_fee'] ?? 0;

        // Total return 0 if anything goes wrong.
        $total = min($rows['school_tution_fee'], $government_tution_fees) ?? 0;

        // Additional fees processing.
        if ($additional_fees = array_filter($additional_fees)) {
          foreach ($additional_fees as $key => $value) {
            if (is_numeric($key)) {
              $rows[$value['value']] = $government_fee[$value['value']] ?? 0;
              $total += $rows[$value['value']];
            }
          }
        }
        // State defined tution fee for the matching approval authority.
        $rows['government_fee'] = $government_tution_fees;
        $rows['total'] = number_format($total, 2, '.', '');
        $slno++;
        $data[] = $rows;
      }
    }
    // Return the data array.
    return $data;
  }

  /**
   * Function to count the fee details of a particular school.
   *
   * @param string $school_id
   *   School MiniNode id.
   *
   * @return array
   *   Returns an array of school fees details.
   */
  public function schoolFeeDetails(?string $school_id = NULL): array {
    // Mapped array based on class.
    $school_fees = [];
    if ($school_id) {
      $school_mini_node = $this->entityTypeManager->getStorage('mini_node')->load($school_id);
      if ($school_mini_node instanceof EckEntityInterface) {
        // Get the education details.
        $education_details = $school_mini_node->get('field_education_details') ?? NULL;
        $education_details_entity = $education_details ? $education_details->referencedEntities() : NULL;
        // For each entry of education detail, check
        // And store value in an nested array.
        foreach ($education_details_entity as $value) {
          $education_type = $value->get('field_education_type')->getString() ?? NULL;
          $medium = $value->get('field_medium')->getString() ?? NULL;
          // Concatenate and generate a unique key.
          $key = $education_type . '_' . $medium;
          // Fee Details for each education detail.
          $fee_details = $value->get('field_fee_details')->referencedEntities();
          // For each fee detail get class value and fee amount.
          foreach ($fee_details as $fee_paragraph) {
            $school_fees[$key][$fee_paragraph->get('field_class_list')->getString()] =
              $fee_paragraph->get('field_total_fees')->getString() ?? NULL;
          }
        }
      }
    }
    return $school_fees;
  }

  /**
   * Gets the fee based on gender, medium, and class.
   *
   * @param array $school_fees
   *   The school fees array.
   * @param string $gender
   *   The gender of the student (boy, girl, transgender).
   * @param string $medium
   *   The medium of education.
   * @param int $class
   *   The class for which fee is required.
   *
   * @return string|null
   *   Returns the fee if found, or NULL if no matching entry is found.
   */
  public function schoolTutionDetails(array $school_fees, string $gender, string $medium, int $class): string|null {
    // Define the gender categories to check in order of priority.
    $gender_priorities = [
      'boy' => ['boys', 'co-ed'],
      'girl' => ['girls', 'co-ed'],
      'transgender' => ['co-ed', 'boys', 'girls'],
    ];
    // Get the gender categories to check for the given gender.
    $genders_to_check = $gender_priorities[$gender] ?? [];
    // Initialize variables to store the latest fee found.
    $latest_fee = NULL;

    // Iterate over the possible gender keys.
    foreach ($genders_to_check as $gender_key) {
      // Create the key for the current gender and medium combination.
      $key = $gender_key . '_' . $medium;
      // Check if this key exists in the school fees array.
      if (isset($school_fees[$key])) {
        // Iterate over each entry for this gender and medium combination.
        foreach ($school_fees[$key] as $key => $entry) {
          // Check if the class matches.
          if ($key == $class) {
            $latest_fee = $entry;
          }
        }
      }
    }

    // Return the latest fee found or NULL if no match was found.
    return $latest_fee;
  }

  /**
   * Function to get the fee defined by state/central.
   *
   * @param string $academic_year
   *   Academic Year.
   * @param string $approval_authority
   *   Approval Authority.
   *
   * @return array
   *   Return State Defined Fees.
   */
  public function stateDefinedFees(?string $academic_year = NULL, ?string $approval_authority = NULL): array {
    $school_fee_values = [];
    $school_fee_mininodes = $this->entityTypeManager->getStorage('mini_node')->loadByProperties([
      'type' => 'school_fee_details',
      'field_academic_year' => $academic_year,
      'field_payment_head' => $approval_authority,
    ]);

    $school_fee_mininodes = reset($school_fee_mininodes);
    if ($school_fee_mininodes instanceof EckEntityInterface) {
      $fee_details = $school_fee_mininodes->get('field_state_fees')->referencedEntities() ?? NULL;
      foreach ($fee_details as $value) {
        $board_type = $value->get('field_board_type')->getString() ?? NULL;
        $current_user_entity = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
        $user_linked_school = $current_user_entity->get('field_school_details')->referencedEntities();
        $user_linked_school = reset($user_linked_school);
        if ($user_linked_school instanceof EckEntityInterface) {
          if ($board_type == $user_linked_school->get('field_board_type')->getString()) {
            $school_fee_values['tution_fee'] = $value->get('field_fees_amount')->getString() ?? 0;
            $additional_fee = $value->get('field_reimbursement_fees_type')->referencedEntities() ?? NULL;
            foreach ($additional_fee as $value) {
              $school_fee_values[$value->get('field_fees_type')->getString()] = $value->get('field_amount')->getString() ?? 0;
            }
          }
        }
      }
    }
    return $school_fee_values;
  }

  /**
   * Callback to get the list of allowed values.
   *
   * @param array $additional_fees
   *   Additional Fees Information.
   *
   * @return array
   *   Return Table Headers.
   */
  public function tableHeading(array $additional_fees = []): array {
    $header = [
      'serial_number' => $this->t('SNO'),
      'student_name' => $this->t('Student Name'),
      'mobile_number' => $this->t('Gaurdian Name'),
      'application_number' => $this->t('Pre-session Class'),
      'parent_name' => $this->t('Medium'),
      'school_fees' => $this->t('School Tution Fees (₹)'),
    ];
    // Check if there are additional fees values.
    if (!empty($additional_fees)) {
      // Loop through the additional fees and append to the header dynamically.
      foreach ($additional_fees as $fee) {
        $value = $fee['value'] ?? NULL;
        if ($value) {
          $header[$value] = $this->t('@value Fees (₹)', ['@value' => ucfirst($fee['value'])]);
        }
      }
    }
    $header['goverment_fees'] = $this->t('Govt Fees (₹)');
    $header['Total'] = $this->t('Total (₹)');
    return $header;
  }

  /**
   * Check if a mini node with the same bundle and field values exists.
   *
   * @param string $bundle
   *   The bundle of the mini node to check.
   * @param string $academic_year
   *   Academic year value.
   * @param string $approval_authority
   *   Payment head value.
   * @param string $school_id
   *   Current School Id.
   *
   * @return bool
   *   TRUE if an entry exists, FALSE otherwise.
   */
  public function checkExistingClaimMiniNode($bundle, $academic_year, $approval_authority, $school_id): bool {
    // Perform an entity query to check for existing mini nodes.
    $query = $this->entityTypeManager->getStorage('mini_node')->getQuery()
      ->condition('type', $bundle)
      ->condition('field_academic_session_tracking', $academic_year)
      ->condition('field_payment_head', $approval_authority)
      ->accessCheck(FALSE);
    if ($school_id) {
      $query->condition('field_school', $school_id);
    }
    // Execute the query.
    $existing_node_ids = $query->execute();

    // If there are any results, an entry exists.
    return !empty($existing_node_ids);
  }

  /**
   * Check if there are students based on paramters.
   *
   * @param string $academic_year
   *   Academic Year.
   * @param array $class_list
   *   List of class.
   * @param string $school_id
   *   School Id.
   *
   * @return array
   *   Node ids.
   */
  public function getStudentList(?string $academic_year = NULL, array $class_list = [], ?string $school_id = NULL): array {
    $query = $this->entityTypeManager->getStorage('mini_node')->getQuery()
      ->condition('type', 'student_performance')
      ->accessCheck(FALSE);
    if (isset($academic_year)) {
      $query->condition('field_academic_session_tracking', $academic_year);
    }
    if (!empty($class_list)) {
      $query->condition('field_current_class', $class_list, 'IN');
    }
    if (isset($school_id)) {
      $query->condition('field_school', $school_id);
    }
    $node_ids = $query->execute();
    // Return the list of student node IDs.
    return $node_ids;

  }

  /**
   * Check if there are students based on paramters.
   *
   * @param string $approval_authority
   *   Payment Head.
   *
   * @return array
   *   Class list.
   */
  public function getClassList(?string $approval_authority = NULL): array {
    $class_list_selected = [];
    if ($approval_authority == 'central_head') {
      $school_config = $this->configFactory->get('rte_mis_school.settings');
      // Consider till class 8th.
      $class_levels = $school_config->get('field_default_options.class_level') ?? [];

      foreach ($class_levels as $key => $class_level) {
        // Consider only students from class 1st to 8th for the central.
        if ($key >= 3) {
          $class_list_selected[] = $key;
          // Search the key for the value till class 8th.
          if ($class_level == '8th') {
            break;
          }
        }
      }
    }
    elseif ($approval_authority == 'state_head') {
      // Check in config, if state payment head is allowed.
      $reimbursement_config = $this->configFactory->get('rte_mis_reimbursement.settings');
      $state_fee_status = $reimbursement_config->get('payment_heads.enable_state_head');
      if ($state_fee_status) {
        // Consider till class 8th.
        $class_levels = $reimbursement_config->get('payment_heads.state_class_list') ?? [];
        $class_list_selected = $class_levels;
      }
      else {
        $class_list_selected = [];
      }
    }
    // Return the list of student node IDs.
    return $class_list_selected;
  }

}
