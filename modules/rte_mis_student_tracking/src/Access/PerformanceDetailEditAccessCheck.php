<?php

namespace Drupal\rte_mis_student_tracking\Access;

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
class PerformanceDetailEditAccessCheck implements AccessInterface {

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
    $mini_node = $routeMatch->getParameter('mini_node') ?? NULL;
    if ($mini_node instanceof EckEntityInterface && $mini_node->bundle() == 'student_performance' && $account->hasPermission('edit any mini_node entities of bundle student_performance')) {
      $uid = $account->id();
      $user_entity = $this->entityTypeManager->getStorage('user')->load($uid);
      if ($user_entity instanceof UserInterface) {
        $school_id = $user_entity->get('field_school_details')->getString() ?? NULL;
        $performance_entity = $this->entityTypeManager->getStorage('mini_node')->load($mini_node->id());
        $school = $performance_entity->get('field_school')->getString();
        $academic_year = $performance_entity->get('field_academic_session')->getString();
        $current_academic_year = _rte_mis_core_get_current_academic_year();
        if (($school_id != NULL && $school_id != $school) || ($academic_year != $current_academic_year)) {
          // Get the school details from user.
          return AccessResult::forbidden()->setCacheMaxAge(0);
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
