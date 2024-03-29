<?php

namespace Drupal\rte_mis_state\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\rte_mis_core\Helper\RteCoreHelper;

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
   * Core helper.
   *
   * @var \Drupal\rte_mis_core\Helper\RteCoreHelper
   */
  protected $rteCoreHelper;

  /**
   * Constructs an UserRegisterAccessCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\rte_mis_core\Helper\RteCoreHelper $rte_core_helper
   *   The rte core helper.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RteCoreHelper $rte_core_helper) {
    $this->entityTypeManager = $entity_type_manager;
    $this->rteCoreHelper = $rte_core_helper;
  }

  /**
   * Checks access to the user register page based on campaign.
   */
  public function access() {
    // Check the status of the school registration window.
    $campaign_status = $this->rteCoreHelper->isCampaignValid('school_registration');

    if ($campaign_status) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden('School registration window is either closed or not open.');
  }

}
