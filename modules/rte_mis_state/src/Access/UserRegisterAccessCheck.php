<?php

namespace Drupal\rte_mis_state\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\rte_mis_state\Helper\RteStateHelper;

/**
 * Determines access to the user register page.
 */
class UserRegisterAccessCheck implements AccessInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * State helper.
   *
   * @var \Drupal\rte_mis_state\Helper\RteStateHelper
   */
  protected $rteStateHelper;

  /**
   * Constructs an UserRegisterAccessCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\rte_mis_state\Helper\RteStateHelper $rte_state_helper
   *   The rte state helper.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RteStateHelper $rte_state_helper) {
    $this->entityTypeManager = $entity_type_manager;
    $this->rteStateHelper = $rte_state_helper;
  }

  /**
   * Checks access to the user register page based on campaign.
   */
  public function access() {
    // Check the status of the school registration window.
    $campaign_status = $this->rteStateHelper->isCampaignValid('school_registration');

    if ($campaign_status) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden('School registration window is either closed or not open.');
  }

}
