<?php

declare(strict_types=1);

namespace Drupal\rte_mis_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a role based details block block.
 *
 * @Block(
 *   id = "rte_mis_core_role_based_details_block",
 *   admin_label = @Translation("Role Based Details Block"),
 *   category = @Translation("Custom"),
 * )
 */
final class RoleBasedDetailsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Check the roles of the current user.
    $roles = $this->currentUser->getRoles();

    // Initialize content variables.
    $heading = $this->t('Dashboard');
    $content = [];

    // Check roles and set content accordingly.
    if (in_array('state_admin', $roles)) {
      $heading = $this->t('State Admin Dashboard');
      $content = $this->getStateAdminContent();
    }
    elseif (in_array('district_admin', $roles)) {
      $heading = $this->t('District Admin Dashboard');
      $content = $this->getDistrictAdminContent();
    }
    elseif (in_array('block_admin', $roles)) {
      $heading = $this->t('Block Admin Dashboard');
      $content = $this->getBlockAdminContent();
    }

    // Build renderable array.
    $build = [
      '#theme' => 'role_based_details_block',
      '#heading' => $heading,
      '#content' => $content,
    ];

    return $build;
  }

  /**
   * Get content for state admin.
   */
  protected function getStateAdminContent() {
    // Implement content calculation for state admin.
    $content = [
      'district_count' => $this->getDistrictCount(),
      'block_count' => $this->getBlocksCount('state_admin'),
      'school_count' => $this->getSchoolCount('state_admin'),
      'registered_schools' => $this->getRegisteredSchoolCount('state_admin'),
      'school_approve_beo' => $this->getSchoolApproveCount('state_admin', 'BEO'),
      'school_approve_deo' => $this->getSchoolApproveCount('state_admin', 'DEO'),
      'school_reject' => $this->getSchoolRejectCount('state_admin'),
      'school_pending_beo' => $this->getSchoolPendingCount('state_admin', 'BEO'),
      'school_pending_deo' => $this->getSchoolPendingCount('state_admin', 'DEO'),
    ];

    return $content;
  }

  /**
   * Get content for district admin.
   */
  protected function getDistrictAdminContent() {
    // Implement content calculation for state admin.
    $content = [
      'block_count' => $this->getBlocksCount('district_admin'),
      'school_count' => $this->getSchoolCount('district_admin'),
      'registered_schools' => $this->getRegisteredSchoolCount('district_admin'),
      'school_approve_beo' => $this->getSchoolApproveCount('district_admin', 'BEO'),
      'school_approve_deo' => $this->getSchoolApproveCount('district_admin', 'DEO'),
      'school_reject' => $this->getSchoolRejectCount('district_admin'),
      'school_pending_beo' => $this->getSchoolPendingCount('district_admin', 'BEO'),
      'school_pending_deo' => $this->getSchoolPendingCount('district_admin', 'DEO'),
    ];

    return $content;
  }

  /**
   * Get content for block admin.
   */
  protected function getBlockAdminContent() {
    // Implement content calculation for state admin.
    $content = [
      'school_count' => $this->getSchoolCount('block_admin'),
      'registered_schools' => $this->getRegisteredSchoolCount('block_admin'),
      'school_approve' => $this->getSchoolApproveCount('block_admin', 'BEO'),
      'school_reject' => $this->getSchoolRejectCount('block_admin'),
      'school_pending' => $this->getSchoolPendingCount('block_admin', 'DEO'),
    ];

    return $content;
  }

  /**
   * Gets the count of active users with the "district_admin" role.
   *
   * @return int
   *   The count of active "district_admin" users.
   */
  public function getDistrictCount() {
    $query = $this->entityTypeManager->getStorage('user')
      ->getQuery()
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->condition('roles', 'district_admin');
    $uids = $query->execute();
    return count($uids);
  }

  /**
   * Wrapper function to get block admin count when state admin is provided.
   *
   * @param string $current_role
   *   The role to check for (should be 'state_admin' or 'district_admin').
   *
   * @return int
   *   Count of block admin users or user location details based on role.
   */
  public function getBlocksCount(string $current_role): int {
    if ($current_role === 'state_admin') {
      // Query for count of users with role 'block_admin'.
      $query = $this->entityTypeManager->getStorage('user')
        ->getQuery()
        ->condition('status', 1)
        ->accessCheck(FALSE)
        ->condition('roles', 'block_admin');
      return $query->count()->execute();
    }

    if ($current_role === 'district_admin') {
      $currentUserId = $this->currentUser->id();
      $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);

      // Get location ID from user field.
      $locationId = (int) $currentUser->get('field_location_details')->getString();

      if (empty($locationId)) {
        // No location ID found, return 0.
        return 0;
      }

      // Load children taxonomy terms and count them.
      $locationTree = $this->entityTypeManager->getStorage('taxonomy_term')->loadChildren($locationId);
      return count($locationTree);
    }

    return 0;
  }

  /**
   * Gets the count of schools based on the user's role and location.
   *
   * @param string $current_role
   *   The role of the user ('state_admin', 'district_admin', 'block_admin').
   * @param int|null $locationId
   *   (Optional) The location ID to filter schools. If not provided, uses
   *    current user's location.
   *
   * @return int
   *   The count of schools based on the user's role and location.
   */
  public function getSchoolCount($current_role, ?int $locationId = NULL): int {
    $matchingSchools = [];

    // If $locationId is not provided, use the current user's location ID.
    if ($locationId === NULL) {
      $currentUserId = $this->currentUser->id();
      $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);
      $locationId = (int) $currentUser->get('field_location_details')->getString();
    }

    // Query all taxonomy terms in the 'school' vocabulary.
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $query = $term_storage->getQuery()
      ->condition('vid', 'school')
      ->accessCheck(FALSE);

    $tids = $query->execute();

    if (!empty($tids)) {
      // Load all school terms.
      $schools = $term_storage->loadMultiple($tids);

      foreach ($schools as $school) {
        // Get the field_location value.
        $field_location = $school->get('field_location')->target_id;

        // Load all parent terms of the school's location.
        $locationTree = $term_storage->loadAllParents($field_location);
        $locationIds = array_keys($locationTree);

        // Check if the school's location matches the provided or current user's
        // location.
        if (in_array($locationId, $locationIds)) {
          // Check user role to determine if we count this school.
          if ($current_role == 'state_admin' ||
                ($current_role == 'district_admin' && in_array($locationId, $locationIds)) ||
                ($current_role == 'block_admin' && in_array($locationId, $locationIds))) {
            $matchingSchools[] = $school;
          }
        }
      }
    }

    return count($matchingSchools);
  }

  /**
   * Gets the count of registered schools based on the user's role.
   *
   * @param string $current_role
   *   The role of the user ('state_admin', 'district_admin', 'block_admin').
   *
   * @return int
   *   The count of registered schools based on the user's role.
   */
  public function getRegisteredSchoolCount($current_role): int {
    if ($current_role === 'state_admin') {
      // Query for school admins.
      $querySchoolAdmins = $this->entityTypeManager->getStorage('user')
        ->getQuery()
        ->condition('roles', 'school_admin')
        ->condition('status', 1)
        ->accessCheck(FALSE);
      $school_admins_count = $querySchoolAdmins->count()->execute();

      // Query for schools.
      $querySchools = $this->entityTypeManager->getStorage('user')
        ->getQuery()
        ->condition('roles', 'school')
        ->condition('status', 1)
        ->accessCheck(FALSE);
      $schools_count = $querySchools->count()->execute();

      return $school_admins_count + $schools_count;
    }

    if ($current_role === 'district_admin' || $current_role === 'block_admin') {
      return count($this->gettingMatchingSchools());
    }

    // Default return if $current_role doesn't match any condition.
    return 0;
  }

  /**
   * Gets the count of schools approved by a specific role.
   *
   * @param string $current_role
   *   The role of the current user ('state_admin', 'district_admin',
   *   'block_admin').
   * @param string $role
   *   The role for which approval status is checked ('District', 'Block', etc.)
   *
   * @return int
   *   The count of schools approved by the specified role.
   */
  public function getSchoolApproveCount($current_role, $role): int {
    $verification_status = 'school_registration_verification_approved_by_' . strtolower($role);

    // Initialize variables.
    $approved_count = 0;
    $matchingSchoolIds = [];

    if ($current_role === 'state_admin') {
      // Step 1: Query all users with role 'school'.
      $query = $this->entityTypeManager->getStorage('user')
        ->getQuery()
        ->condition('roles', 'school')
        ->condition('status', 1)
        ->accessCheck(FALSE);
      $school_ids = $query->execute();

      // Return 0 if no schools found.
      if (empty($school_ids)) {
        return 0;
      }

      // Step 2: Filter schools by approval status.
      $query = $this->entityTypeManager->getStorage('user')
        ->getQuery()
        ->condition('uid', $school_ids, 'IN')
        ->accessCheck(FALSE)
        ->condition('field_school_details.entity:mini_node.field_school_verification', $verification_status);
      $approved_nids = $query->execute();

      // Count the approved schools.
      if (!empty($approved_nids)) {
        $approved_count = count($approved_nids);
      }

      return $approved_count;
    }

    if ($current_role === 'district_admin' || $current_role === 'block_admin') {
      $matchingSchoolIds = $this->gettingMatchingSchools();

      // Step 2: Filter schools by approval status.
      if (!empty($matchingSchoolIds)) {
        $query = $this->entityTypeManager->getStorage('user')
          ->getQuery()
          ->condition('uid', $matchingSchoolIds, 'IN')
          ->accessCheck(FALSE)
          ->condition('field_school_details.entity:mini_node.field_school_verification', $verification_status);
        $approved_nids = $query->execute();

        // Count the approved schools.
        if (!empty($approved_nids)) {
          $approved_count = count($approved_nids);
        }
      }

      return $approved_count;
    }

    // Return 0 if $current_role doesn't match any condition.
    return 0;
  }

  /**
   * Gets the count of rejected schools based on the user's role.
   *
   * @param string $current_role
   *   The role of the user ('state_admin', 'district_admin', 'block_admin').
   *
   * @return int
   *   The count of rejected schools based on the user's role.
   */
  public function getSchoolRejectCount($current_role): int {
    $verification_status = 'school_registration_verification_rejected';
    $rejected_count = 0;
    $matchingSchoolIds = [];

    if ($current_role === 'state_admin') {
      // Step 1: Query all users with role 'school'.
      $query = $this->entityTypeManager->getStorage('user')
        ->getQuery()
        ->condition('roles', 'school')
        ->condition('status', 1)
        ->accessCheck(FALSE);
      $school_ids = $query->execute();

      // Return 0 if no schools found.
      if (empty($school_ids)) {
        return 0;
      }

      // Step 2: Filter schools by rejection status.
      $query = $this->entityTypeManager->getStorage('user')
        ->getQuery()
        ->condition('uid', $school_ids, 'IN')
        ->accessCheck(FALSE)
        ->condition('field_school_details.entity:mini_node.field_school_verification', $verification_status);
      $rejected_nids = $query->execute();

      // Count the rejected schools.
      if (!empty($rejected_nids)) {
        $rejected_count = count($rejected_nids);
      }

      return $rejected_count;
    }

    if ($current_role === 'district_admin' || $current_role === 'block_admin') {
      $matchingSchoolIds = $this->gettingMatchingSchools();

      // Step 2: Filter schools by rejection status.
      if (!empty($matchingSchoolIds)) {
        $query = $this->entityTypeManager->getStorage('user')
          ->getQuery()
          ->condition('uid', $matchingSchoolIds, 'IN')
          ->accessCheck(FALSE)
          ->condition('field_school_details.entity:mini_node.field_school_verification', $verification_status);
        $rejected_nids = $query->execute();

        // Count the rejected schools.
        if (!empty($rejected_nids)) {
          $rejected_count = count($rejected_nids);
        }
      }

      return $rejected_count;
    }

    // Default return if $current_role doesn't match any condition.
    return 0;
  }

  /**
   * Gets the count of pending schools based on user role and pending role.
   *
   * @param string $current_role
   *   The current user role ('state_admin', 'district_admin', 'block_admin').
   * @param string $role
   *   The pending role ('BEO', 'DEO').
   *
   * @return int
   *   The count of pending schools.
   */
  public function getSchoolPendingCount(string $current_role, string $role): int {
    // Initialize variables.
    $pending_count = 0;

    // Determine the query conditions based on the current role.
    switch ($current_role) {
      case 'state_admin':
        // Step 1: Query all users with role 'school'.
        $query = $this->entityTypeManager->getStorage('user')
          ->getQuery()
          ->condition('roles', 'school')
          ->condition('status', 1)
          ->accessCheck(FALSE);

        // Execute the query to get school user IDs.
        $school_ids = $query->execute();

        // Return 0 if no schools found.
        if (empty($school_ids)) {
          return 0;
        }

        if (!empty($school_ids)) {
          // Step 2: Filter schools by pending status and role.
          $verification_status = ($role === 'DEO') ? 'school_registration_verification_approved_by_beo' : 'school_registration_verification_submitted';
          $pending_count = $this->countPendingSchools($school_ids, $verification_status);
        }
        break;

      case 'district_admin':
      case 'block_admin':
        // Get matching school IDs based on the admin's location.
        $matchingSchoolIds = $this->gettingMatchingSchools();

        if (!empty($matchingSchoolIds)) {
          // Step 2: Filter schools by pending status and role.
          $verification_status = ($role === 'DEO') ? 'school_registration_verification_approved_by_beo' : 'school_registration_verification_submitted';
          $pending_count = $this->countPendingSchools($matchingSchoolIds, $verification_status);
        }

        break;

      default:
        // Default return if $current_role doesn't match any condition.
        $pending_count = 0;
        break;
    }

    return $pending_count;
  }

  /**
   * Counts the number of pending schools based on given conditions.
   *
   * @param array $school_ids
   *   Array of school user IDs to filter.
   * @param string $verification_status
   *   Verification status to filter by ('school_registration_verification_appro
   *   ved_by_beo' or 'school_registration_verification_submitted').
   *
   * @return int
   *   The count of pending schools.
   */
  private function countPendingSchools(array $school_ids, string $verification_status): int {
    // Query to count pending schools.
    $query = $this->entityTypeManager->getStorage('user')
      ->getQuery()
      ->condition('uid', $school_ids, 'IN')
      ->accessCheck(FALSE)
      ->condition('field_school_details.entity:mini_node.field_school_verification', $verification_status);

    // Execute the query to get pending school user IDs.
    $pending_nids = $query->execute();

    // Count the pending schools.
    return count($pending_nids);
  }

  /**
   * Gets the IDs of schools matching the current user's location.
   *
   * This function retrieves the IDs of taxonomy terms (schools) that match
   * the location of the current user based on their 'field_location_details'
   * field.
   *
   * @return array
   *   An array of school IDs matching the current user's location.
   */
  public function gettingMatchingSchools() {
    // Step 1: Get the current user's ID and load the user entity.
    $currentUserId = $this->currentUser->id();
    $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);

    // Extract the location ID from the user's field.
    $locationId = (int) $currentUser->get('field_location_details')->getString();

    // Initialize an empty array to store matching school IDs.
    $matchingSchoolIds = [];

    // Step 2: Query all taxonomy terms (schools) in the 'school' vocabulary.
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $query = $term_storage->getQuery()
    // Filter by the 'school' vocabulary.
      ->condition('vid', 'school')
    // Bypass access checks for querying.
      ->accessCheck(FALSE);

    // Execute the query to get taxonomy term IDs (tids).
    $tids = $query->execute();

    // Step 3: If there are matching taxonomy term IDs (tids), load the terms.
    if (!empty($tids)) {
      // Load multiple taxonomy terms using their IDs (tids).
      $schools = $term_storage->loadMultiple($tids);

      // Step 4: Iterate through each school term.
      foreach ($schools as $school) {
        // Get the target ID of the 'field_location' field of the school.
        $field_location = $school->get('field_location')->target_id;

        // Load all parent terms (ancestors) of the school's location term.
        $locationTree = $term_storage->loadAllParents($field_location);
        $locationIds = array_keys($locationTree);

        // Step 5: Check if the school's location matches the admin's location.
        if (in_array($locationId, $locationIds)) {
          // If the location matches, add the school's ID to the matching IDs
          // array.
          $matchingSchoolIds[] = $school->id();
        }
      }
    }

    // Return the array of matching school IDs.
    return $matchingSchoolIds;
  }

}
