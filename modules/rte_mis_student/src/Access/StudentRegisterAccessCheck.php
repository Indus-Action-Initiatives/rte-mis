<?php

namespace Drupal\rte_mis_student\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\rte_mis_core\Helper\RteCoreHelper;

/**
 * Determines access to the student register page.
 */
class StudentRegisterAccessCheck implements AccessInterface {

  /**
   * Core helper.
   *
   * @var \Drupal\rte_mis_Core\Helper\RteCoreHelper
   */
  protected $rteCoreHelper;

  /**
   * Constructs an StudentRegisterAccessCheck object.
   *
   * @param \Drupal\rte_mis_core\Helper\RteCoreHelper $rte_core_helper
   *   The rte core helper.
   */
  public function __construct(RteCoreHelper $rte_core_helper) {
    $this->rteCoreHelper = $rte_core_helper;
  }

  /**
   * Checks access to the user register page based on academic_session.
   */
  public function access(AccountInterface $account, RouteMatchInterface $routeMatch) {
    // Check the status of the student registration window.
    $academic_session_status = $this->rteCoreHelper->isAcademicSessionValid('student_application');
    if ($academic_session_status) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden('Student registration window is either closed or not open.');
  }

}
