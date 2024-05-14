<?php

namespace Drupal\rte_mis_student\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(RteCoreHelper $rte_core_helper, RequestStack $requestStack, EntityTypeManagerInterface $entity_type_manager) {
    $this->rteCoreHelper = $rte_core_helper;
    $this->requestStack = $requestStack;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Checks access to the user register page based on academic_session.
   */
  public function access(AccountInterface $account, RouteMatchInterface $routeMatch) {
    $miniNode = $routeMatch->getParameter('mini_node') ?? NULL;
    $roles = $account->getRoles();
    $uid = $account->id();
    // Below condition is applied for student_detail mini_node.
    if ($miniNode instanceof EckEntityInterface && $miniNode->bundle() == 'student_details') {
      // Check the status of the student registration window.
      $studentRegistrationSessionStatus = $this->rteCoreHelper->isAcademicSessionValid('student_application');
      // Applicable for anonymous user.
      if (in_array('anonymous', $roles)) {
        $currentWorkflowStatus = $miniNode->get('field_student_verification')->getString() ?? '';
        if (!$studentRegistrationSessionStatus || in_array($currentWorkflowStatus, [
          'student_workflow_rejected', 'student_workflow_approved', 'student_workflow_duplicate',
        ])) {
          return AccessResult::forbidden('Student registration window is either closed or not open.')->setCacheMaxAge(0);
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
      elseif (in_array('block_admin', $roles)) {
        $userEntity = $this->entityTypeManager->getStorage('user')->load($uid);
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
        $studentLocation = $miniNode->get('field_location')->getString() ?? '';
        // Check the status of the student verification window.
        $studentVerificationSessionStatus = $this->rteCoreHelper->isAcademicSessionValid('student_verification');
        $currentWorkflowStatus = $miniNode->get('field_student_verification')->getString() ?? '';
        // Deny the edit access for the following cases.
        // 1. If `student_verification` is not currently in progress.
        // 2. If `student_registration` is currently in progress.
        // 3. If application is already approved.
        // 4. If block admin location do not matches with student application.
        if (!$studentVerificationSessionStatus || $studentRegistrationSessionStatus || in_array($currentWorkflowStatus, ['student_workflow_approved']) || !in_array($studentLocation, $locationIds)) {
          return AccessResult::forbidden()->setCacheMaxAge(0);
        }
      }
    }
    return AccessResult::allowed();
  }

}
