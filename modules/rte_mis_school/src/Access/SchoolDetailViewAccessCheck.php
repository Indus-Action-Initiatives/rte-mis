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
 * Determines view access to the school details mini node.
 */
class SchoolDetailViewAccessCheck implements AccessInterface {

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
    $miniNode = $routeMatch->getParameter('mini_node') ?? NULL;
    if ($miniNode instanceof EckEntityInterface && $miniNode->bundle() == 'school_details') {
      if (!$account->hasPermission('view any mini_node entities of bundle school_details')) {
        return AccessResult::forbidden()->cachePerPermissions();
      }
      $uid = $account->id();
      $roles = $account->getRoles();
      $userEntity = $this->entityTypeManager->getStorage('user')->load($uid);
      $schoolDetailsLocation = $miniNode->get('field_location')->getString() ?? '';
      if ($userEntity instanceof UserInterface) {
        // Get the school details from user.
        $schoolUdiseTermId = $userEntity->get('field_school_details')->getString() ?? '';
        // Get the current status of verification workflow.
        $currentWorkflowStatus = $miniNode->get('field_school_verification')->getString() ?? '';
        // Applicable for school and school_admin roles.
        // Deny the view access for the following cases.
        // 1. If user's scgool details reference entity doesn't matches the mini
        // node id.
        // 2. If application status is pending state.
        if (array_intersect($roles, ['school_admin', 'school']) &&
        ($schoolUdiseTermId != $miniNode->id() || $currentWorkflowStatus == 'school_registration_verification_pending')) {
          // Set cache max age to 0 for operation link in view to change.
          return AccessResult::forbidden()->setCacheMaxAge(0);
        }
        // Applicable for block and district_admin roles.
        elseif (array_intersect($roles, ['block_admin', 'district_admin'])) {
          // Populate locationId with user location.
          $locationId = $userEntity->get('field_location_details')->getString() ?? '';
          $locationIds = [0];
          if (!empty($locationId)) {
            $locationIds[] = $locationId;
            $locationTree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $locationId);
            foreach ($locationTree as $value) {
              $locationIds[] = $value->tid;
            }
          }
          // Deny the view access for the following cases.
          // 1. If application status is pending state.
          // 2. If user location do not matches with that of school mini_node.
          if ($currentWorkflowStatus == 'school_registration_verification_pending' || !in_array($schoolDetailsLocation, $locationIds)) {
            // Set cache max age to 0 for operation link in view to change.
            return AccessResult::forbidden()->setCacheMaxAge(0);
          }
        }
      }
      return AccessResult::allowed()->setCacheMaxAge(0);
    }
    // Return allow for other mini_node bundle and for default condition.
    return AccessResult::allowed();
  }

}
