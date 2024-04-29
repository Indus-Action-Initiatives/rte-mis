<?php

namespace Drupal\rte_mis_student\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eck\EckEntityInterface;
use Drupal\rte_mis_core\Helper\RteCoreHelper;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Determines access to the edit operation for student_details mini_node.
 */
class StudentDetailEditAccessCheck implements AccessInterface {

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
   * The request stacks service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs an StudentDetailEditAccessCheck object.
   *
   * @param \Drupal\rte_mis_core\Helper\RteCoreHelper $rte_core_helper
   *   The rte core helper.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack service.
   */
  public function __construct(RteCoreHelper $rte_core_helper, RequestStack $requestStack) {
    $this->rteCoreHelper = $rte_core_helper;
    $this->requestStack = $requestStack;
  }

  /**
   * Checks access to the user register page based on academic_session.
   */
  public function access(AccountInterface $account, RouteMatchInterface $routeMatch) {
    $miniNode = $routeMatch->getParameter('mini_node') ?? NULL;
    $roles = $account->getRoles();
    // Below condition is applied for student_detail mini_node.
    // Currently this is only applicable for anonymous user.
    if ($miniNode instanceof EckEntityInterface && $miniNode->bundle() == 'student_details' && in_array('anonymous', $roles)) {
      // Check the status of the student registration window.
      $academic_session_status = $this->rteCoreHelper->isAcademicSessionValid('student_application');
      if (!$academic_session_status) {
        return AccessResult::forbidden('Student registration window is either closed or not open.');
      }
      // Check if `field_mobile_number` exist. if `YES then validate if number
      // in the entity matches the `student-phone` cookie.
      if ($miniNode->hasField('field_mobile_number')) {
        $phoneCookie = $this->requestStack->getCurrentRequest()->cookies->get('student-phone', NULL);
        $mobileData = $miniNode->get('field_mobile_number')->getValue() ?? [];
        $mobile = $mobileData[0]['value'] ?? NULL;
        if ($phoneCookie == $mobile) {
          return AccessResult::allowed()->setCacheMaxAge(0);
        }
        return AccessResult::forbidden()->setCacheMaxAge(0);
      }
    }
    return AccessResult::allowed();
  }

}
