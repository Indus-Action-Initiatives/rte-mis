<?php

namespace Drupal\rte_mis_school\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eck\EckEntityInterface;
use Drupal\rte_mis_core\Helper\RteCoreHelper;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Determines edit access to the school details mini node.
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
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  public $configFactory;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs an UserRegisterAccessCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\rte_mis_core\Helper\RteCoreHelper $rte_core_helper
   *   The rte core helper.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RteCoreHelper $rte_core_helper, ConfigFactoryInterface $config_factory, RequestStack $request_stack) {
    $this->entityTypeManager = $entity_type_manager;
    $this->rteCoreHelper = $rte_core_helper;
    $this->configFactory = $config_factory;
    $this->requestStack = $request_stack;
  }

  /**
   * Checks access to the user register page based on academic_session.
   */
  public function access(AccountInterface $account, RouteMatchInterface $routeMatch) {
    $miniNode = $routeMatch->getParameter('mini_node') ?? NULL;
    $request = $this->requestStack->getCurrentRequest();
    $display = $request->query->get('display') ?? NULL;
    if ($miniNode instanceof EckEntityInterface && $miniNode->bundle() == 'school_details' && $account->hasPermission('edit any mini_node entities of bundle school_details')) {
      $uid = $account->id();
      $roles = $account->getRoles();
      $userEntity = $this->entityTypeManager->getStorage('user')->load($uid);
      $schoolDetailsLocation = $miniNode->get('field_location')->getString() ?? '';
      if ($userEntity instanceof UserInterface) {
        // Check the status of the school registration window.
        $academic_session_status = $this->rteCoreHelper->isAcademicSessionValid('school_registration');
        // Get the school details from user.
        $schoolUdiseTermId = $userEntity->get('field_school_details')->getString() ?? '';
        // Get the current status of verification workflow.
        $currentWorkflowStatus = $miniNode->get('field_school_verification')->getString() ?? '';
        $config = $this->configFactory->get('rte_mis_school.settings');
        // Applicable for school and school_admin roles.
        if (array_intersect($roles, ['school_admin', 'school']) &&
        (!$academic_session_status || $schoolUdiseTermId != $miniNode->id() || !in_array($currentWorkflowStatus, [
          'school_registration_verification_submitted', 'school_registration_verification_pending',
          'school_registration_verification_send_back_to_school',
        ]))) {
          // Set cache max age to 0 for operation link in view to change.
          return AccessResult::forbidden()->setCacheMaxAge(0);
        }
        // Applicable for block_admin role.
        elseif (in_array('block_admin', $roles) || ($config->get('school_verification.single_approval') && in_array($config->get('school_verification.single_approval_role'), $roles))) {
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
          // Check if there is an active window for reimbursment claim.
          // If yes, then allow block admin.
          $reimbursmentClaimCampaignStatus = $this->rteCoreHelper->isAcademicSessionValid('reimbursement_claim');
          if ($reimbursmentClaimCampaignStatus && $display == 'school_fee_modify') {
            return AccessResult::allowed();
          }
          $schoolVerificationCampaignStatus = $this->rteCoreHelper->isAcademicSessionValid('school_verification');
          // Deny the edit access for the following cases.
          // 1. If `school_verification` is not currently in progress.
          // 2. If `school_registration` is currently in progress.
          // 3. If application is not in submitted or send_back_to_school state.
          // 4. If user location do not matches with that of school mini_node.
          if (!$schoolVerificationCampaignStatus || $academic_session_status || !in_array($currentWorkflowStatus, [
            'school_registration_verification_submitted', 'school_registration_verification_send_back_to_school',
          ]) || !in_array($schoolDetailsLocation, $locationIds)) {
            // Set cache max age to 0 for operation link in view to change.
            return AccessResult::forbidden()->setCacheMaxAge(0);
          }
        }
        elseif (in_array('district_admin', $roles)) {
          return AccessResult::forbidden()->setCacheMaxAge(0);
        }
      }
      // Set cache max age to 0 for operation link in view to change.
      return AccessResult::allowed()->setCacheMaxAge(0);
    }
    // Return allow for other mini_node bundle and for default condition.
    return AccessResult::allowed();
  }

}
