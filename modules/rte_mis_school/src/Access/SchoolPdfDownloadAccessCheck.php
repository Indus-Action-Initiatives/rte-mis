<?php

namespace Drupal\rte_mis_school\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\eck\EckEntityInterface;

/**
 * Determines the PDF download conditions for different user roles.
 */
class SchoolPdfDownloadAccessCheck implements AccessInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a SchoolPdfDownloadAccessCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function access(RouteMatchInterface $routeMatch) {
    // Load the current user.
    /** @var \Drupal\user\userInterface $curr_user */
    $curr_user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $curr_user_roles = $curr_user->getRoles();
    // Get the mini node from the route.
    $miniNodeId = $routeMatch->getParameters('mini_node')->get('entity_id');
    $miniNode = $this->entityTypeManager->getStorage('mini_node')->load($miniNodeId);

    // Below condition is applied for student_detail mini_node.
    if ($miniNode instanceof EckEntityInterface && $miniNode->bundle() == 'school_details') {
      // Check if the current user has the required roles.
      if (array_intersect(['school_admin', 'school'], $curr_user_roles)) {
        // For school admin/school user roles
        // Check if the current user's school detail is equal
        // to current route's mini node.
        $currUserLinkedMiniNode = $curr_user->get('field_school_details')->getString();
        if ($currUserLinkedMiniNode == $miniNodeId) {
          return AccessResult::allowed();
        }
        return AccessResult::forbidden();
      }
      elseif (array_intersect(['block_admin', 'district_admin'], $curr_user_roles)) {
        // Logic for district and block admins.
        // Load the mini node based on miniNodeId.
        $currUserLinkedLocation = $curr_user->get('field_location_details')->getString();
        $miniNode = $this->entityTypeManager->getStorage('mini_node')->loadByProperties([
          'type' => 'school_Details',
          'id' => $miniNodeId,
          'status' => 1,
        ]);
        $miniNode = reset($miniNode);

        if ($miniNode instanceof EckEntityInterface) {
          // Get the location details of mini node.
          $mini_node_location = $miniNode->get('field_location')->getString();
          // Load all the parents of the mini node's location id.
          $locationIds = [];
          if (!empty($mini_node_location)) {
            $locationTree = $this->entityTypeManager->getStorage('taxonomy_term')->loadAllParents($mini_node_location);
            foreach ($locationTree as $value) {
              $locationIds[] = $value->get('tid')->value;
            }
          }
          if (in_array('block_admin', $curr_user_roles)) {
            // For Block admin user role,
            // Check if the mini node's location detail has
            // the 3rd parent(block) equal to current users location.
            if (in_array($currUserLinkedLocation, $locationIds)) {
              return AccessResult::allowed();
            }
            return AccessResult::forbidden();
          }
          else {
            // For District admin user role,
            // Check if the mini node's location detail has
            // the 4rd parent(block) equal to current users location.
            if (in_array($currUserLinkedLocation, $locationIds)) {
              return AccessResult::allowed();
            }
            return AccessResult::forbidden();
          }
        }
        else {
          return AccessResult::forbidden();
        }
      }
    }

    return AccessResult::allowed();
  }

}
