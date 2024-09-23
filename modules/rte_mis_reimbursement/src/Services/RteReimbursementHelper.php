<?php

namespace Drupal\rte_mis_reimbursement\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\workflow\Entity\WorkflowTransitionInterface;

/**
 * Class RteReimbursementHelper.
 *
 * Provides helper functions for rte mis allocation module.
 */
class RteReimbursementHelper {

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
   * Constructs a new RteReimbursementHelper object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
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

}
