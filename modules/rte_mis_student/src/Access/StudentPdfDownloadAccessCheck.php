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
class StudentPdfDownloadAccessCheck implements AccessInterface {

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\rte_mis_core\Helper\RteCoreHelper $rte_core_helper
   *   The rte core helper.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RteCoreHelper $rte_core_helper, RequestStack $requestStack) {
    $this->entityTypeManager = $entity_type_manager;
    $this->rteCoreHelper = $rte_core_helper;
    $this->requestStack = $requestStack;
  }

  /**
   * Checks access to the user register page based on academic_session.
   */
  public function access(AccountInterface $account, RouteMatchInterface $routeMatch) {
    $miniNodeId = $routeMatch->getParameters('mini_node')->get('entity_id') ?? NULL;
    $roles = $account->getRoles();
    $miniNode = $this->entityTypeManager->getStorage('mini_node')->load($miniNodeId);
    // Below condition is applied for student_detail mini_node.
    // Currently this is only applicable for anonymous user.
    if ($miniNode instanceof EckEntityInterface && $miniNode->bundle() == 'student_details' && in_array('anonymous', $roles)) {
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
