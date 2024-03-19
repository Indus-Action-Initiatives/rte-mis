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
 * Determines access to the school details mini node.
 */
class SchoolDetailEditAccessCheck implements AccessInterface {

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
    $roles = $account->getRoles();
    $userEntity = $this->entityTypeManager->getStorage('user')->load($uid);
    $miniNode = $routeMatch->getParameter('mini_node') ?? NULL;
    if ($miniNode instanceof EckEntityInterface && $userEntity instanceof UserInterface && $miniNode->bundle() == 'school_details' && (count($roles) == 1 && $roles[0] == 'authenticated')) {
      $schoolUdiseTermId = $userEntity->get('field_school_details')->getString() ?? '';
      if ($schoolUdiseTermId == $miniNode->id()) {
        return AccessResult::allowed();
      }
      return AccessResult::forbidden("You don't have access to edit school detail.");
    }
    return AccessResult::allowed();
  }

}
