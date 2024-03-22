<?php

namespace Drupal\rte_mis_school\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eck\EckEntityInterface;
use Drupal\rte_mis_core\Helper\RteCoreHelper;
use Drupal\user\UserInterface;

/**
 * Determines access to the school register page.
 */
class SchoolRegisterAccessCheck implements AccessInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Core helper.
   *
   * @var \Drupal\rte_mis_Core\Helper\RteCoreHelper
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
  public function access(AccountInterface $account, RouteMatchInterface $routeMatch) {
    $uid = $account->id();
    $userEntity = $this->entityTypeManager->getStorage('user')->load($uid);
    $miniNode = $routeMatch->getParameter('mini_node') ?? NULL;
    // Check the status of the school registration window.
    $campaign_status = $this->rteCoreHelper->isCampaignValid('school_registration');
    if ($campaign_status && ($account->hasPermission('edit any mini_node entities of bundle school_details'))) {
      if ($miniNode instanceof EckEntityInterface && $miniNode->bundle() == 'school_details' && $userEntity instanceof UserInterface) {
        // Get the school details from user.
        $schoolUdiseTermId = $userEntity->get('field_school_details')->getString() ?? '';
        // Get the current status of verification workflow.
        $currentWorkflowStatus = $miniNode->get("field_school_verification")->getString() ?? '';
        // Allow only if below condition satisfy.
        // 1. If mini node matches the entity in field_school_details field
        // 2. Current workflow status is submitted pending and back to school.
        if ($schoolUdiseTermId == $miniNode->id() &&
         in_array($currentWorkflowStatus, [
           'school_registration_verification_submitted',
           'school_registration_verification_pending',
           'school_registration_verification_send_back_to_school',
         ])) {
          return AccessResult::allowed();
        }
      }
    }
    return AccessResult::forbidden('School registration window is either closed or not open.');
  }

}
