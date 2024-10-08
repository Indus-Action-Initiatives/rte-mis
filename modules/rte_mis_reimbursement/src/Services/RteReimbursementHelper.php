<?php

namespace Drupal\rte_mis_reimbursement\Services;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eck\EckEntityInterface;
use Drupal\workflow\Entity\WorkflowTransition;
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
      'reimbursement_claim_workflow_reset',
    ],
    // From BEO approved state.
    'reimbursement_claim_workflow_approved_by_beo' => [
      'reimbursement_claim_workflow_approved_by_deo',
      'reimbursement_claim_workflow_rejected',
      'reimbursement_claim_workflow_submitted',
    ],
  ];

  /**
   * Array of states transitions associated with payment approver.
   *
   * @var array
   */
  const PAYMENT_APPROVAL_TRANSITIONS = [
    'reimbursement_claim_workflow_approved_by_deo_payment_completed',
    'reimbursement_claim_workflow_approved_by_deo_payment_pending',
    'reimbursement_claim_workflow_payment_pending_payment_completed',
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
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new RteReimbursementHelper object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, AccountInterface $account, LoggerChannelFactoryInterface $logger_factory, TimeInterface $time) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $account;
    $this->loggerFactory = $logger_factory;
    $this->time = $time;
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
    $to_sids = $possible_transition[$from_sid] ?? [];
    if (in_array($to_sid, $to_sids)) {
      // Execute the transition, mark this as force as we are overriding
      // workflow.
      $transition->execute(TRUE);
    }
  }

  /**
   * Disables the states transitions associated with payment approver.
   *
   * This function unsets available transitions from 'approved by deo' state.
   *
   * @param array $transitions
   *   Array of transitions.
   */
  public function disablePaymentApprovalTransitions(array &$transitions): void {
    // This array contains values of transitions to disable.
    $disabled_transitions = self::PAYMENT_APPROVAL_TRANSITIONS;
    // Unset the transitions.
    foreach ($disabled_transitions as $transition) {
      unset($transitions[$transition]);
    }
  }

  /**
   * Checks if user should be allowed to update school claim mini node or not.
   *
   * @param \Drupal\eck\EckEntityInterface $entity
   *   School claim mini node.
   *
   * @return bool
   *   TRUE if user can update school claim mini node, FALSE otherwise.
   */
  public function canUpdateReimbursementClaim(EckEntityInterface $entity): bool {
    $access = FALSE;
    $reimbursement_status = $entity->get('field_reimbursement_claim_status')->getString();
    $roles = $this->currentUser->getRoles();
    // Get payment approver from reimbursement configurations.
    $payment_approver = $this->configFactory->get('rte_mis_reimbursement.settings')->get('payment_approver') ?? 'state';
    // Dyanmically creating role as per the configured payment approver
    // so that we can match approver with the user roles.
    $approver_role = "{$payment_approver}_admin";
    // Get approval level.
    $is_single_level_approval = $this->isSingleLevelApprovalEnabled();
    // For district admin.
    if (in_array('district_admin', $roles)) {
      // For single approval, district can update the status
      // if current status is either submitted or approved by beo.
      if ($is_single_level_approval) {
        if (in_array($reimbursement_status, [
          'reimbursement_claim_workflow_submitted',
          'reimbursement_claim_workflow_approved_by_beo',
        ])) {
          return TRUE;
        }
      }
      // For dual approval, district can update the status
      // if current status is approved by beo.
      else {
        if ($reimbursement_status == 'reimbursement_claim_workflow_approved_by_beo') {
          return TRUE;
        }
      }
    }

    // For block admin.
    if (in_array('block_admin', $roles)) {
      // Block can only update the status if dual level approval is
      // configured and current status is submitted.
      if (!$is_single_level_approval && $reimbursement_status == 'reimbursement_claim_workflow_submitted') {
        return TRUE;
      }
    }

    // If approver role matches the current user role than we check
    // that the admin can update the reimbursement status if current status
    // is either 'approved by deo' or 'paymennt pending'.
    if (in_array($approver_role, $roles) && in_array($reimbursement_status, [
      'reimbursement_claim_workflow_approved_by_deo',
      'reimbursement_claim_workflow_payment_pending',
    ])) {
      return TRUE;
    }

    // In all other cases the update access should be denied.
    return $access;
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
      $udise_code = $current_user_entity->getDisplayName() ?? NULL;
      // Check for the details of school in the requested academic year.
      $user_linked_school = $this->getSchoolDetails($udise_code, $academic_year);
      if (!$user_linked_school) {
        $this->loggerFactory->get('rte_mis_reimbursement')
          ->notice('There is no school found for the school with UDISE code: @udise and academic year: @academic_year', [
            '@udise' => $udise_code,
            '@academic_year' => str_replace('_', '-', $academic_year),
          ]);
        return [];
      }
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
      $government_fee = $this->stateDefinedFees($academic_year, $approval_authority, $user_linked_school);
    }
    $slno = 1;
    foreach ($node_chunks as $chunk) {
      $rows = [];
      $rows['slno'] = $slno;
      $student_performance_entities = $this->entityTypeManager->getStorage('mini_node')->loadMultiple($chunk);
      foreach ($student_performance_entities as $student_performance_entity) {
        $rows = [];
        $row_keys = [
          'field_student_name', 'field_parent_name', 'field_current_class', 'field_medium',
          'field_gender', 'field_entry_class_for_allocation',
        ];
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
        $rows['type'] = $this->t('New');
        if ($row_values['field_entry_class_for_allocation'] != $row_values['field_current_class']) {
          $rows['type'] = $this->t('Old');
        }
        // Medium.
        $rows['field_medium'] = $student_medium;
        // School Tution Fee.
        $rows['school_tution_fee'] = $this->schoolTutionDetails($current_user_fee_details, $student_gender, $student_medium, $class_value) ?? 0;
        // Set default value.
        $government_tution_fees = 0;
        $education_level = NULL;

        if (isset($current_user_fee_details)) {
          // Get the genders from the config.
          $genders = array_keys($school_config->get('field_default_options.field_education_type'));

          // 'boy' => ['boys', 'co-ed'].
          // 'girl' => ['girls', 'co-ed'].
          // 'transgender' => ['co-ed', 'boys', 'girls'].
          $gender_priorities = [
            'boy' => array_diff($genders, ['girls']),
            'girl' => array_diff($genders, ['boys']),
            'transgender' => array_reverse($genders),
          ];
          // Get the gender categories to check for the given gender.
          $genders_to_check = $gender_priorities[$student_gender] ?? [];
          $found = FALSE;
          // Iterate over the possible gender keys.
          foreach ($genders_to_check as $gender_key) {
            // Loop through the array keys of $current_user_fee_details.
            foreach (array_keys($current_user_fee_details) as $key) {
              // Split the key into its parts education_type,
              // education_level, and medium.
              if (strpos($key, $gender_key) === 0) {
                // Split the key into its parts:
                // education_type, medium, and education_level.
                $parts = explode('|', $key);
                // Get the education level.
                if (count($parts) === 3) {
                  $education_level = $parts[2];
                  $found = TRUE;
                  break;
                }
              }
            }
            // Break the outer loop if a match was found.
            if ($found) {
              break;
            }
          }

        }

        foreach ($government_fee as $value) {
          if ($education_level && $value['education_level'] == $education_level) {
            $government_tution_fees = $value['tution_fee'] ?? 0;
          }
        }

        // Total return 0 if anything goes wrong.
        $total = min($rows['school_tution_fee'], $government_tution_fees) ?? 0;

        // Additional fees processing.
        if ($additional_fees = array_filter($additional_fees)) {
          foreach ($additional_fees as $key => $value) {
            if (is_numeric($key)) {
              if ($government_fee) {
                foreach ($government_fee as $gov_fee) {
                  if ($gov_fee['education_level'] == $education_level) {
                    $rows[$value['value']] = $gov_fee[$value['value']] ?? 0;
                    $total += $rows[$value['value']];
                  }
                }
              }
              else {
                $rows[$value['value']] = 0;
                $total += $rows[$value['value']];
              }
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
          $education_level = $value->get('field_education_level')->getString() ?? NULL;
          $medium = $value->get('field_medium')->getString() ?? NULL;
          // Concatenate and generate a unique key.
          $key = $education_type . '|' . $medium . '|' . $education_level;
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
    // Get the list of all the classes from config.
    $school_config = $this->configFactory->get('rte_mis_school.settings');
    $genders = array_keys($school_config->get('field_default_options.field_education_type'));

    // Define the gender categories to check in order of priority.
    // 'boy' => ['boys', 'co-ed'].
    // 'girl' => ['girls', 'co-ed'].
    // 'transgender' => ['co-ed', 'boys', 'girls'].
    $gender_priorities = [
      'boy' => array_diff($genders, ['girls']),
      'girl' => array_diff($genders, ['boys']),
      'transgender' => array_reverse($genders),
    ];
    // Get the gender categories to check for the given gender.
    $genders_to_check = $gender_priorities[$gender] ?? [];
    // Initialize variables to store the latest fee found.
    $latest_fee = NULL;

    // Iterate over the possible gender keys.
    foreach ($genders_to_check as $gender_key) {
      // Create the key for the current gender and medium combination.
      $combination = $gender_key . '|' . $medium;
      // Iterate over each entry for this gender and medium combination.
      foreach ($school_fees as $key => $entry) {
        if (strpos($key, $combination) === 0) {
          foreach ($school_fees[$key] as $given_class => $fee) {
            // Check if the class matches.
            if ($given_class == $class) {
              $latest_fee = $fee;
            }
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
   * @param string $school_id
   *   School MiniNode id.
   *
   * @return array
   *   Return State Defined Fees.
   */
  public function stateDefinedFees(?string $academic_year = NULL, ?string $approval_authority = NULL, ?string $school_id = NULL): array {
    $school_total_fee_values = [];
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
        if ($school_id) {
          $user_linked_school = $this->entityTypeManager->getStorage('mini_node')->load($school_id);
          if ($user_linked_school instanceof EckEntityInterface) {
            if ($board_type == $user_linked_school->get('field_board_type')->getString()) {
              $school_fee_values = [];
              $school_fee_values['education_level'] = $value->get('field_education_level')->getString() ?? 0;
              $school_fee_values['tution_fee'] = $value->get('field_fees_amount')->getString() ?? 0;
              $additional_fee = $value->get('field_reimbursement_fees_type')->referencedEntities() ?? NULL;
              foreach ($additional_fee as $value) {
                $school_fee_values[$value->get('field_fees_type')->getString()] = $value->get('field_amount')->getString() ?? 0;
              }
              $school_total_fee_values[] = $school_fee_values;
            }
          }
        }
      }
    }
    return $school_total_fee_values;
  }

  /**
   * This will be an entity query to get the school details.
   *
   * @param string $udise_code
   *   School Udise Code.
   * @param string $academic_year
   *   Academic year.
   *
   * @return string|null
   *   Returns the fee if found, or NULL if school details found.
   */
  public function getSchoolDetails(?string $udise_code = NULL, ?string $academic_year = NULL): ?string {
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
      'vid' => 'school',
      'name' => $udise_code,
    ]);
    $term = reset($term);

    // Entity Query to get the school with matching udise code
    // school name and academic year and return the school entity.
    $query = $this->entityTypeManager->getStorage('mini_node')
      ->getQuery()
      ->condition('type', 'school_details')
      ->accessCheck(FALSE);

    if ($udise_code) {
      $query->condition('field_udise_code', $term->id());
    }
    if ($academic_year) {
      $query->condition('field_academic_year', $academic_year);
    }
    $miniNodes = $query->execute();

    return !empty($miniNodes) ? reset($miniNodes) : NULL;
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
      'class' => $this->t('Pre-session Class'),
      'application_type' => $this->t('New/Old'),
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
    // Perform an entity query to check for existing published mini nodes.
    $query = $this->entityTypeManager->getStorage('mini_node')->getQuery()
      ->condition('type', $bundle)
      ->condition('field_academic_session_claim', $academic_year)
      ->condition('field_payment_head', $approval_authority)
      ->condition('status', TRUE)
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
   * Checks if claim reset limit has crossed or not.
   *
   * @param string $academic_year
   *   Academic year value.
   * @param string $school_id
   *   Current School Id.
   * @param string $approval_authority
   *   Payment head value.
   *
   * @return bool
   *   TRUE if an reset limit is hit, FALSE otherwise.
   */
  public function hasHitResetLimit($academic_year, $school_id, $approval_authority): bool {
    $limit_hit = FALSE;

    if (empty($academic_year) || empty($approval_authority) || empty($school_id)) {
      return $limit_hit;
    }
    // Perform an entity query to check for existing published mini nodes.
    $mini_node = $this->entityTypeManager->getStorage('mini_node')->loadByProperties([
      'type' => 'school_claim',
      'field_academic_session_claim' => $academic_year,
      'field_payment_head' => $approval_authority,
      'field_school' => $school_id,
    ]);
    // Get the mini node object as we know there should be only
    // one mini node available.
    $mini_node = reset($mini_node);
    if ($mini_node instanceof EckEntityInterface) {
      $workflow_transitions = WorkflowTransition::loadMultipleByProperties(
        'mini_node',
        [$mini_node->id()],
        [],
        'field_reimbursement_claim_status',
        NULL,
        NULL,
        'DESC'
      );
      // Get the configured number of reset limit.
      $reset_limit = $this->configFactory->get('rte_mis_reimbursement.settings')->get('reset_limit') ?? 2;
      foreach ($workflow_transitions as $transition) {
        // Exit if reset_limit reaches 0.
        if ($reset_limit <= 0) {
          $limit_hit = TRUE;
          break;
        }
        $to_sid = $transition->getToSid();
        $from_sid = $transition->getFromSid();
        $comment = $transition->getComment();
        if ($to_sid == 'reimbursement_claim_workflow_submitted'
          && $from_sid == 'reimbursement_claim_workflow_reset'
          && !str_contains($comment, 'Auto Reset')) {
          $reset_limit--;
        }
      }
    }

    // Return mini node ids.
    return $limit_hit;
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

  /**
   * Resets the reimbursement claim for the given id.
   *
   * @param int|string $id
   *   Entity id for the reimbursement claim mini node to reset.
   * @param mixed $message
   *   Message to add when status is updated to reset.
   * @param int|string $uid
   *   User id to use as author of the reset transition.
   */
  public function resetReimbursementClaim(int|string $id, mixed $message, int|string $uid = 0): void {
    $mini_node = $this->entityTypeManager->getStorage('mini_node')->load($id);
    if ($mini_node instanceof EckEntityInterface && $mini_node->bundle() == 'school_claim') {
      // Get current state.
      $current_state = workflow_node_current_state($mini_node, 'field_reimbursement_claim_status');
      // Don't reset if it is already in reset state.
      if ($current_state == 'reimbursement_claim_workflow_reset') {
        return;
      }
      // Update new state to education completed, indicates that
      // state has been updated and set to education completed.
      $transition = WorkflowTransition::create([
        0 => $current_state,
        'field_name' => 'field_reimbursement_claim_status',
      ]);
      // Set the target entity.
      $transition->setTargetEntity($mini_node);
      // Set the target state to 'Education completed'.
      $transition->setValues(
        'reimbursement_claim_workflow_reset',
        $uid,
        $this->time->getRequestTime(),
        $message,
        TRUE,
      );
      // Force execute the transition as this is not permitted to
      // change from all states to reset.
      $transition->execute(TRUE);
      // Manually update the entity so that updated transition reflects.
      // This is done because force execute and update entity method
      // is not working as expected and not updating the entity field
      // value so setting the value here explicitly.
      $mini_node->field_reimbursement_claim_status->workflow_transition = $transition;
      $mini_node->field_reimbursement_claim_status->value = 'reimbursement_claim_workflow_reset';
      $mini_node->changed = $this->time->getRequestTime();
      $mini_node->save();

      $this->loggerFactory->get('rte_mis_reimbursement')->notice($this->t('Reimbursement claim for the school claim id: @id has been reset by user with uid: @uid. Reason: @message', [
        '@uid' => $uid,
        '@message' => $message,
        '@id' => $uid,
      ]));
    }
  }

  /**
   * Function to count the fee details of a particular school.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return array
   *   Returns an array of school fees details.
   */
  public function getSchoolFeeDetailsByForm(FormStateInterface $form_state): array {
    $school_fees = [];
    $education_details = [];
    $education_details = $form_state->getValue('field_education_details') ?? [];
    // Get the subform columns as only those are needed for calculations.
    $education_details = array_column($education_details, 'subform');
    // For each entry of education detail, check
    // And store value in an nested array.
    foreach ($education_details as $value) {
      $education_type = $value['field_education_type'][0]['value'] ?? NULL;
      $education_level = $value['field_education_level'][0]['value'] ?? NULL;
      $medium = $value['field_medium'][0]['value'] ?? NULL;
      // Concatenate and generate a unique key.
      $key = $education_type . '|' . $medium . '|' . $education_level;
      // Fee Details for each education detail.
      $fee_details = $value['field_fee_details'];
      // Get the subform columns as only those are needed for calculations.
      $fee_details = array_column($fee_details, 'subform');
      // For each fee detail get class value and fee amount.
      foreach ($fee_details as $fee_paragraph) {
        $school_fees[$key][$fee_paragraph['field_class_list'][0]['value']] = $fee_paragraph['field_total_fees'][0]['value'] ?? NULL;
      }
    }
    return $school_fees;
  }

  /**
   * Function to get the fee defined by state/central from entity object.
   *
   * @param int|string $entity_id
   *   School fee details mini node id.
   *
   * @return array
   *   Returns an array of State Defined Fees.
   */
  public function getStateFeeDetailsFromEntity(int|string $entity_id): array {
    $state_fees = [];
    if ($entity_id) {
      $entity = $this->entityTypeManager->getStorage('mini_node')->load($entity_id);
      if ($entity instanceof EckEntityInterface) {
        $state_fee_details = $entity->get('field_state_fees') ?? NULL;
        $state_fee_entities = $state_fee_details ? $state_fee_details->referencedEntities() : NULL;
        // For each entry of state fees details.
        foreach ($state_fee_entities as $state_fee_entity) {
          $board_type = $state_fee_entity->get('field_board_type')->getString() ?? NULL;
          $education_level = $state_fee_entity->get('field_education_level')->getString() ?? NULL;
          // Concatenate and generate a unique key.
          $key = $board_type . '|' . $education_level;
          // Fee Details for each state fees detail.
          $tution_fees = $state_fee_entity->get('field_fees_amount')->getString();
          $state_fees[$key]['tution_fees'] = $tution_fees;
          $reimbursement_fees_type = $state_fee_entity->get('field_reimbursement_fees_type')->referencedEntities();
          // For each fee type get fee type and fee amount.
          foreach ($reimbursement_fees_type as $fee_paragraph) {
            $state_fees[$key][$fee_paragraph->get('field_fees_type')->getString()] = $fee_paragraph->get('field_amount')->getString() ?? NULL;
          }
        }
      }
    }
    return $state_fees;
  }

  /**
   * Function to get the fee defined by state/central by entity form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return array
   *   Returns an array of State Defined Fees.
   */
  public function getStateFeeDetailsByForm(FormStateInterface $form_state): array {
    $state_fees = [];
    $state_fees_details = $form_state->getValue('field_state_fees') ?? [];
    // Get the subform columns as only those are needed for calculations.
    $state_fees_details = array_column($state_fees_details, 'subform');
    // For each entry of education detail, check
    // And store value in an nested array.
    foreach ($state_fees_details as $value) {
      $board_type = $value['field_board_type'][0]['value'] ?? NULL;
      $education_level = $value['field_education_level'][0]['value'] ?? NULL;
      // Concatenate and generate a unique key.
      $key = $board_type . '|' . $education_level;
      // Fee Details for each state fees detail.
      $tution_fees = $value['field_fees_amount'][0]['value'];
      $state_fees[$key]['tution_fees'] = $tution_fees;
      $reimbursement_fees_type = $value['field_reimbursement_fees_type'];
      // Get the subform columns as only those are needed for calculations.
      $reimbursement_fees_type = array_column($reimbursement_fees_type, 'subform');
      // For each fee type get fee type and fee amount.
      foreach ($reimbursement_fees_type as $fee_paragraph) {
        $state_fees[$key][$fee_paragraph['field_fees_type'][0]['value']] = $fee_paragraph['field_amount'][0]['value'] ?? NULL;
      }
    }
    return $state_fees;
  }

  /**
   * Function to find the differences between two nested associative arrays.
   *
   * @param array $array1
   *   The original array.
   * @param array $array2
   *   The modified array.
   *
   * @return array
   *   An array with the differences including the key, old value, new value,
   *   and action (added, removed, or changed).
   */
  public function findArrayDifferences($array1, $array2) {
    $differences = [];

    // Iterate over the first array.
    foreach ($array1 as $key => $sub_array1) {
      if (isset($array2[$key])) {
        $sub_array2 = $array2[$key];

        // Compare values in the sub-arrays.
        foreach ($sub_array1 as $sub_key => $value1) {
          if (isset($sub_array2[$sub_key])) {
            if ($sub_array2[$sub_key] != $value1) {
              // If the value is different, mark as changed.
              $differences[$key][$sub_key] = [
                'old_value' => $value1,
                'new_value' => $sub_array2[$sub_key],
                'action' => 'changed',
              ];
            }
          }
          else {
            // This item was removed in the new array.
            $differences[$key][$sub_key] = [
              'old_value' => $value1,
              'new_value' => NULL,
              'action' => 'removed',
            ];
          }
        }

        // Check for new items in $array2 that don't exist in $array1.
        foreach ($sub_array2 as $sub_key => $value2) {
          if (!isset($sub_array1[$sub_key])) {
            // This item was added in the new array.
            $differences[$key][$sub_key] = [
              'old_value' => NULL,
              'new_value' => $value2,
              'action' => 'added',
            ];
          }
        }
      }
      else {
        // If the entire key is missing in $array2, mark all items as removed.
        foreach ($sub_array1 as $sub_key => $value1) {
          $differences[$key][$sub_key] = [
            'old_value' => $value1,
            'new_value' => NULL,
            'action' => 'removed',
          ];
        }
      }
    }

    // Check for new top-level keys in $array2 that don't exist in $array1.
    foreach ($array2 as $key => $sub_array2) {
      if (!isset($array1[$key])) {
        foreach ($sub_array2 as $sub_key => $value2) {
          // This entire key is new in array2.
          $differences[$key][$sub_key] = [
            'old_value' => NULL,
            'new_value' => $value2,
            'action' => 'added',
          ];
        }
      }
    }

    return $differences;
  }

}
