<?php

namespace Drupal\rte_mis_school\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\rte_mis_core\Helper\RteCoreHelper;

/**
 * Determines access to the school verification.
 */
class SchoolVerificationAccessCheck implements AccessInterface {

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
   * Constructs an SchoolVerificationAccessCheck object.
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
   * Check access to school verification page on school_verification event.
   */
  public function access(AccountInterface $account, RouteMatchInterface $routeMatch) {
    // Check the status of event `school_verification`.
    $eventStatus = $this->rteCoreHelper->isCampaignValid('school_verification');
    if ($eventStatus) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden('School verification window is either closed or not open.');
  }

}
