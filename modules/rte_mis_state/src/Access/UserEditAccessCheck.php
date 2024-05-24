<?php

namespace Drupal\rte_mis_state\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Determines access to the user edit page.
 */
class UserEditAccessCheck implements AccessInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * Checks access to the user register page based on academic_session.
   */
  public function access(AccountInterface $account, RouteMatchInterface $routeMatch) {
    $requested_user = $routeMatch->getParameter('user') ?? NULL;
    $roles = $account->getRoles();
    $uid = $account->id();
    $current_user = $this->entityTypeManager->getStorage('user')->load($uid);
    if (in_array('block_admin', $requested_user->getRoles()) && in_array('district_admin', $roles)) {
      // Get the term storage.
      $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
      // Get the current user location id.
      $current_user_location = $current_user->get('field_location_details')->getString();
      // If the current user is district,
      // Get the blocks id under that district id.
      $taxonomy_term_tree = $termStorage->loadTree('location', $current_user_location, 1, TRUE);
      // Block's location id.
      $user_location = $requested_user->get('field_location_details')->getString();
      // Array to store the block's list,
      // under the current user district.
      $block_list = [];
      foreach ($taxonomy_term_tree as $element) {
        array_push($block_list, $element->id());
      }
      if (!in_array($user_location, $block_list)) {
        // Restrict Access If the current user does not,
        // Have the access.
        return AccessResult::forbidden();
      }
      // Else allow edit operation.
      return AccessResult::allowed();
    }
    elseif (array_intersect(['school', 'school_admin'], $requested_user->getRoles()) && in_array('state_admin', $roles)) {
      return AccessResult::forbidden();
    }
    elseif (in_array('app_admin', $requested_user->getRoles()) && in_array('state_admin', $roles)) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowed();
  }

}
