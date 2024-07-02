<?php

declare(strict_types=1);

namespace Drupal\rte_mis_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserInterface;
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
      '#cache' => [
        'contexts' => ['user.roles'],
        'tags' => [
          'user_list',
          'taxonomy_term_list',
          'mini_node_list',
        ],
      ],
    ];

    return $build;
  }

  /**
   * Get content for state admin.
   */
  protected function getStateAdminContent() {
    // Implement content calculation for state admin.
    $content = [
      'Districts' => $this->getDistrictCount(),
      'Blocks' => $this->getBlocksCount('state_admin'),
      'School' => $this->getSchoolCount('state_admin'),
      'Register' => $this->getRegisteredSchoolCount('state_admin'),
      'Approved (BEO)' => $this->getSchoolStatus('state_admin', 'approved_by_beo'),
      'Approved (DEO)' => $this->getSchoolStatus('state_admin', 'approved_by_deo'),
      'Reject' => $this->getSchoolStatus('state_admin', 'rejected'),
      'Pending (BEO)' => $this->getSchoolStatus('state_admin', 'submitted'),
      'Pending (DEO)' => $this->getSchoolStatus('state_admin', 'approved_by_beo'),
    ];

    return $content;
  }

  /**
   * Get content for district admin.
   */
  protected function getDistrictAdminContent() {
    // Implement content calculation for state admin.
    $content = [
      'Blocks' => $this->getBlocksCount('district_admin'),
      'Total School' => $this->getSchoolCount('district_admin'),
      'Registered' => $this->getRegisteredSchoolCount('district_admin'),
      'Approve (BEO)' => $this->getSchoolStatus('district_admin', 'approved_by_beo'),
      'Approve (DEO)' => $this->getSchoolStatus('district_admin', 'approved_by_deo'),
      'Reject' => $this->getSchoolStatus('district_admin', 'rejected'),
      'Pending (BEO)' => $this->getSchoolStatus('district_admin', 'submitted'),
      'Pending (DEO)' => $this->getSchoolStatus('district_admin', 'approved_by_beo'),
    ];

    return $content;
  }

  /**
   * Get content for block admin.
   */
  protected function getBlockAdminContent() {
    // Implement content calculation for state admin.
    $content = [
      'Schools' => $this->getSchoolCount('block_admin'),
      'Registered' => $this->getRegisteredSchoolCount('block_admin'),
      'Approve' => $this->getSchoolStatus('block_admin', 'approved_by_beo'),
      'Reject' => $this->getSchoolStatus('block_admin', 'rejected'),
      'Pending' => $this->getSchoolStatus('block_admin', 'submitted'),
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

      /** @var \Drupal\user\Entity\User */
      $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);

      // Get location ID from user field.
      $locationId = (int) $currentUser->get('field_location_details')->getString();

      if (empty($locationId)) {
        // No location ID found, return 0.
        return 0;
      }

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
   *
   * @return int
   *   The count of schools based on the user's role and location.
   */
  public function getSchoolCount($current_role): int {
    // Initialize an array to store matching schools.
    $matchingSchools = [];

    // If $locationId is not provided and the role is not state_admin,
    // use the current user's location ID.
    if ($current_role != 'state_admin') {
      $currentUserId = $this->currentUser->id();
      $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);
      if ($currentUser instanceof UserInterface) {
        $locationId = (int) $currentUser->get('field_location_details')->getString() ?? NULL;
      }
    }

    /** @var \Drupal\taxonomy\TermStorage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $query = $term_storage->getQuery()
      ->condition('vid', 'school')
      ->accessCheck(FALSE);

    $tids = $query->execute();

    if (!empty($tids)) {
      foreach ($tids as $tid) {
        /** @var \Drupal\taxonomy\Entity\Term */
        $school = $term_storage->load($tid);
        // If the role is state_admin,
        // add all schools without filtering by location.
        if ($current_role == 'state_admin') {
          $matchingSchools[] = $school;
        }
        else {
          // Get the field_location value.
          $field_location = $school->get('field_location')->target_id;

          if (!empty($field_location)) {
            // Load all parent terms of the school's location.
            $locationTree = $term_storage->loadAllParents($field_location);
            $locationIds = array_keys($locationTree) ?? [];

            // Check if the school's location matches the provided
            // or current user's location.
            if (in_array($locationId, $locationIds)) {
              // Check user role to determine if we count this school.
              if (($current_role == 'district_admin' && in_array($locationId, $locationIds)) ||
                  ($current_role == 'block_admin' && in_array($locationId, $locationIds))) {
                $matchingSchools[] = $school;
              }
            }
          }

        }
      }
    }

    // Return the count of matching schools.
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
      // Query for school admins & school user role.
      $queryAccounts = $this->entityTypeManager->getStorage('user')
        ->getQuery()
        ->condition('roles', ['school_admin', 'school'], 'IN')
        ->condition('status', 1)
        ->accessCheck(FALSE);
      $accounts = $queryAccounts->count()->execute();

      return $accounts;
    }

    if ($current_role === 'district_admin' || $current_role === 'block_admin') {
      return count($this->gettingMatchingSchoolTerms());
    }

    // Default return if $current_role doesn't match any condition.
    return 0;
  }

  /**
   * Gets the count of school based on status.
   *
   * @param string $current_role
   *   The current user role ('state_admin', 'district_admin', 'block_admin').
   * @param string $status_key
   *   The pending role ('submitted', 'rejected', 'approved_by_beo',
   *   'approved_by_beo').
   *
   * @return int
   *   The count of pending schools.
   */
  public function getSchoolStatus(string $current_role, string $status_key): int {
    // Initialize variables.
    $pending_count = 0;

    // Determine the query conditions based on the current role.
    switch ($current_role) {
      case 'state_admin':
        // Query all users.
        $query = $this->entityTypeManager->getStorage('user')
          ->getQuery()
          ->condition('status', 1)
          ->accessCheck(FALSE);

        if ($status_key == 'approved_by_deo') {
          $query->condition('roles', 'school_admin');
        }
        else {
          $query->condition('roles', 'school');
        }

        // Execute the query to get ids.
        $school_ids = $query->execute();

        // Return 0 if no schools found.
        if (empty($school_ids)) {
          return 0;
        }

        if (!empty($school_ids)) {
          // Filter schools based on status key.
          $verification_status = 'school_registration_verification_' . $status_key;
          $pending_count = $this->countPendingSchools($school_ids, $verification_status);
        }
        break;

      case 'district_admin':
      case 'block_admin':
        // Get matching school term IDs based on the admin's location.
        $matchingSchoolIds = $this->gettingMatchingSchoolTerms();
        $roles = ['school', 'school_admin'];

        $termName = [];
        foreach ($matchingSchoolIds as $value) {
          $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($value);
          $termName[] = $term->label();
        }
        if (!empty($termName)) {
          $query = $this->entityTypeManager->getStorage('user')
            ->getQuery()
            ->condition('roles', $roles, 'IN')
            ->condition('name', $termName, 'IN')
            ->condition('status', 1)
            ->accessCheck(FALSE);
          $school_ids = $query->execute();
        }
        if (!empty($school_ids)) {
          // Filter schools based on status key.
          $verification_status = 'school_registration_verification_' . $status_key;
          $pending_count = $this->countPendingSchools($school_ids, $verification_status);
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
  public function gettingMatchingSchoolTerms() {
    // Get the current user's ID and load the user entity.
    $currentUserId = $this->currentUser->id();

    /** @var \Drupal\user\Entity\User */
    $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);
    $currentUserRole = $this->currentUser->getRoles(TRUE);

    /** @var \Drupal\taxonomy\TermStorage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    // Extract the location ID from the user's field.
    $locationId = (int) $currentUser->get('field_location_details')->getString();

    $locationIds = [];
    if (in_array('district_admin', $currentUserRole)) {
      // For district get the child location.
      $childLocation = $term_storage->loadTree('location', $locationId, 1, FALSE);
      if ($childLocation) {
        foreach ($childLocation as $value) {
          $locationIds[] = $value->tid;
        }
      }
      if ($locationIds) {
        $query = $term_storage->getQuery()
        // Filter by the 'school' vocabulary.
          ->condition('vid', 'school')
          ->condition('field_location', $locationIds, 'IN')
          ->accessCheck(FALSE);

        // Execute the query to get taxonomy term IDs (tids).
        $tids = $query->execute();
      }
      return $tids;
    }

    $query = $term_storage->getQuery()
      // Filter by the 'school' vocabulary.
      ->condition('vid', 'school')
      ->condition('field_location', $locationId)
      ->accessCheck(FALSE);

    // Execute the query to get taxonomy term IDs (tids).
    $tids = $query->execute();

    // Return the array of matching school IDs.
    return $tids;
  }

}
