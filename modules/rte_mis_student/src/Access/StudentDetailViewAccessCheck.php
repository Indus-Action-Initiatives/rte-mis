<?php

namespace Drupal\rte_mis_student\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eck\EckEntityInterface;
use Drupal\rte_mis_core\Helper\RteCoreHelper;
use Drupal\user\UserInterface;

/**
 * Determines access to the student_details view.
 */
class StudentDetailViewAccessCheck implements AccessInterface {

  /**
   * Core helper.
   *
   * @var \Drupal\rte_mis_Core\Helper\RteCoreHelper
   */
  protected $rteCoreHelper;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an StudentDetailViewAccessCheck object.
   *
   * @param \Drupal\rte_mis_core\Helper\RteCoreHelper $rte_core_helper
   *   The rte core helper.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(RteCoreHelper $rte_core_helper, EntityTypeManagerInterface $entity_type_manager) {
    $this->rteCoreHelper = $rte_core_helper;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Checks access to the user register page based on academic_session.
   */
  public function access(AccountInterface $account, RouteMatchInterface $routeMatch) {
    $miniNode = $routeMatch->getParameter('mini_node') ?? NULL;
    if ($miniNode instanceof EckEntityInterface && $miniNode->bundle() == 'student_details') {
      if (!$account->hasPermission('view any mini_node entities of bundle student_details')) {
        return AccessResult::forbidden()->cachePerPermissions();
      }
      $uid = $account->id();
      $roles = $account->getRoles();
      $userEntity = $this->entityTypeManager->getStorage('user')->load($uid);
      $studentDetailsLocation = $miniNode->get('field_location')->getString() ?? '';
      if ($userEntity instanceof UserInterface) {
        // Applicable for block admin.
        if (array_intersect($roles, ['block_admin', 'district_admin'])) {
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
          // 1. If user location do not matches with that of student mini_node.
          if (!in_array($studentDetailsLocation, $locationIds)) {
            // Set cache max age to 0 for operation link in view to change.
            return AccessResult::forbidden()->setCacheMaxAge(0);
          }
        }
        elseif (in_array('school_admin', $roles)) {
          $userEntity = $this->entityTypeManager->getStorage('user')->load($uid);
          if ($userEntity instanceof UserInterface) {
            $schoolMiniNodeId = $userEntity->get('field_school_details')->getString();
            if (!empty($schoolMiniNodeId)) {
              $result = $this->entityTypeManager->getStorage('mini_node')->getQuery()
                ->condition('type', 'allocation')
                ->condition('field_academic_year_allocation', _rte_mis_core_get_current_academic_year())
                ->condition('field_school', $schoolMiniNodeId)
                ->condition('field_student', $miniNode->id())
                ->condition('status', 1)
                ->accessCheck(TRUE)
                ->execute();
              if (!empty($result)) {
                return AccessResult::allowed()->setCacheMaxAge(0);
              }
            }
          }
          return AccessResult::forbidden()->setCacheMaxAge(0);
        }
      }
    }
    // Return allow for other mini_node bundle and for default condition.
    return AccessResult::allowed();
  }

}
