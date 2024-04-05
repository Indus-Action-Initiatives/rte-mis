<?php

namespace Drupal\rte_mis_state\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\rte_mis_core\Helper\RteCoreHelper;

/**
 * Determines access to the user register page.
 */
class UserRegisterAccessCheck implements AccessInterface {

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
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs an UserRegisterAccessCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\rte_mis_core\Helper\RteCoreHelper $rte_core_helper
   *   The rte core helper.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RteCoreHelper $rte_core_helper, AccountInterface $current_user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->rteCoreHelper = $rte_core_helper;
    $this->currentUser = $current_user;
  }

  /**
   * Checks access to the user register page based on academic_session.
   */
  public function access() {
    // Check the status of the school registration window.
    $academic_session_status = $this->rteCoreHelper->isAcademicSessionValid('school_registration');

    if ($academic_session_status && $this->currentUser->isAnonymous()) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden('School registration window is either closed or not open.');
  }

}
