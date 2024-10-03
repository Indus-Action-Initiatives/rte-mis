<?php

namespace Drupal\rte_mis_reimbursement\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eck\EckEntityInterface;
use Drupal\rte_mis_core\Helper\RteCoreHelper;
use Drupal\user\UserInterface;

/**
 * Determines view access to the school claim mini node.
 */
class SchoolClaimViewAccessCheck implements AccessInterface {

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
   * Constructs an SchoolClaimViewAccessCheck object.
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
   * Checks access to school claim view based on academic_session & location.
   */
  public function access(AccountInterface $account, RouteMatchInterface $routeMatch) {
    $mini_node = $routeMatch->getParameter('mini_node') ?? NULL;
    if ($mini_node instanceof EckEntityInterface && $mini_node->bundle() == 'school_claim') {
      if (!$account->hasPermission('view any mini_node entities of bundle school_claim')) {
        return AccessResult::forbidden()->cachePerPermissions();
      }
      $uid = $account->id();
      $roles = $account->getRoles();
      $user_entity = $this->entityTypeManager->getStorage('user')->load($uid);
      $school_claim_linked_school_id = $mini_node->get('field_school')->getString() ?? NULL;
      // Ge the school id and the location of the school from there.
      $school_claim_linked_school = $mini_node->get('field_school')->referencedEntities() ?? '';
      $school_claim_linked_school = reset($school_claim_linked_school);
      $school_details_location = $school_claim_linked_school->get('field_location')->getString() ?? '';
      if ($user_entity instanceof UserInterface) {
        // Get the school details from user.
        $school_udise_term_id = $user_entity->get('field_school_details')->getString() ?? '';
        // Applicable for and school_admin roles.
        if (in_array('school_admin', $roles) &&
        ($school_udise_term_id != $school_claim_linked_school_id)) {
          // Set cache max age to 0 for operation link in view to change.
          return AccessResult::forbidden()->setCacheMaxAge(0);
        }
        // Applicable for block and district_admin roles.
        elseif (array_intersect($roles, ['block_admin', 'district_admin'])) {
          // Populate location_id with user location.
          $location_id = $user_entity->get('field_location_details')->getString() ?? '';
          $location_ids = [0];
          if (!empty($location_id)) {
            $location_ids[] = $location_id;
            $location_tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $location_id);
            foreach ($location_tree as $value) {
              $location_ids[] = $value->tid;
            }
          }
          // Deny the view access
          // If user location do not matches with school claim mini_node.
          if (!in_array($school_details_location, $location_ids)) {
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
