<?php

namespace Drupal\rte_mis_reimbursement\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eck\EckEntityInterface;
use Drupal\rte_mis_core\Helper\RteCoreHelper;
use Drupal\user\UserInterface;

/**
 * Determines edit access to the school claim mini node.
 */
class SchoolClaimEditAccessCheck implements AccessInterface {

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
   * Constructs an SchoolClaimEditAccessCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\rte_mis_core\Helper\RteCoreHelper $rte_core_helper
   *   The rte core helper.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RteCoreHelper $rte_core_helper, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->rteCoreHelper = $rte_core_helper;
    $this->configFactory = $config_factory;
  }

  /**
   * Checks access to school claim edit based on academic_session & location.
   */
  public function access(AccountInterface $account, RouteMatchInterface $routeMatch) {
    $mini_node = $routeMatch->getParameter('mini_node') ?? NULL;
    if ($mini_node instanceof EckEntityInterface && $mini_node->bundle() == 'school_claim') {
      if (!$account->hasPermission('edit any mini_node entities of bundle school_claim')) {
        return AccessResult::forbidden()->cachePerPermissions();
      }
      $uid = $account->id();
      $roles = $account->getRoles();
      // Load user entity.
      $user_entity = $this->entityTypeManager->getStorage('user')->load($uid);
      $school_claim_linked_school_id = $mini_node->get('field_school')->getString() ?? NULL;
      // Get the school id and the location of the school from there.
      $school_claim_linked_school = $mini_node->get('field_school')->referencedEntities() ?? '';
      $school_claim_linked_school = reset($school_claim_linked_school);
      $school_detail_location = $school_claim_linked_school->get('field_location')->getString() ?? '';
      if ($user_entity instanceof UserInterface) {
        // Check the status of the school reimbursement claim window.
        $academic_session_status = $this->rteCoreHelper->isAcademicSessionValid('reimbursement_claim');
        // Get the school details from user.
        $school_udise_term_id = $user_entity->get('field_school_details')->getString() ?? '';
        // Applicable for school_admin roles.
        // If current use role is school admin then
        // they can only see their application.
        if (in_array('school_admin', $roles) &&
        (!$academic_session_status || $school_claim_linked_school_id != $school_udise_term_id)) {
          // Set cache max age to 0 for operation link in view to change.
          return AccessResult::forbidden()->setCacheMaxAge(0);
        }
        // Applicable for block_admin role & district_admin.
        elseif (array_intersect(['block_admin', 'district_admin'], $roles)) {
          // Populate location_id with user location.
          $location_id = $user_entity->get('field_location_details')->getString() ?? '';
          $location_ids = [0];
          if (!empty($location_id)) {
            $location_ids[] = $location_id;
            $location_tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $location_id);
            foreach ($location_tree as $value) {
              $location_ids[] = $value->tid;
            }
          }
          if (!in_array($school_detail_location, $location_ids)) {
            // Set cache max age to 0 for operation link in view to change.
            return AccessResult::forbidden()->setCacheMaxAge(0);
          }
        }
      }
      // Set cache max age to 0 for operation link in view to change.
      return AccessResult::allowed()->setCacheMaxAge(0);
    }
    // Return allow for other mini_node bundle and for default condition.
    return AccessResult::allowed();
  }

}
