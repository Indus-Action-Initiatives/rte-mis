<?php

namespace Drupal\rte_mis_allocation\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eck\EckEntityInterface;
use Drupal\user\UserInterface;

/**
 * Determines edit access to the allocation mini node.
 */
class AllocationDetailEditAccessCheck implements AccessInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an UserRegisterAccessCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Checks access to the allocation mini node.
   */
  public function access(AccountInterface $account, RouteMatchInterface $routeMatch) {
    $miniNode = $routeMatch->getParameter('mini_node') ?? NULL;
    if ($miniNode instanceof EckEntityInterface && $miniNode->bundle() == 'allocation' && $account->hasPermission('edit any mini_node entities of bundle allocation')) {
      $uid = $account->id();
      $userEntity = $this->entityTypeManager->getStorage('user')->load($uid);
      if ($userEntity instanceof UserInterface) {
        $schoolId = $userEntity->get('field_school_details')->getString() ?? NULL;
        if ($schoolId != $miniNode->id()) {
          // Get the school details from user.
          // return AccessResult::forbidden()->setCacheMaxAge(0);
        }
        // Set cache max age to 0 for operation link in view to change.
        return AccessResult::allowed()->setCacheMaxAge(0);
      }
      // Set cache max age to 0 for operation link in view to change.
      return AccessResult::allowed()->setCacheMaxAge(0);
    }
    // Return allow for other mini_node bundle and for default condition.
    return AccessResult::allowed();
  }

}
