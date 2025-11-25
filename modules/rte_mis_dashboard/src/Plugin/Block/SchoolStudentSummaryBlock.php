<?php

namespace Drupal\rte_mis_dashboard\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rte_mis_report\Services\RteReportHelper;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'RTE Dashboard Stats' Block.
 *
 * @Block(
 *   id = "rte_school_student_summery_block",
 *   admin_label = @Translation("RTE School Student summery Block"),
 *   category = @Translation("RTE MIS Dashboard")
 * )
 */
class SchoolStudentSummaryBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The rte mis helper service.
   *
   * @var \Drupal\rte_mis_report\Services\RteReportHelper
   */
  protected $rteReportHelper;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user,
    RteReportHelper $rte_report_helper,
    RequestStack $request_stack,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->rteReportHelper = $rte_report_helper;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('rte_mis_report.report_helper'),
      $container->get('request_stack'),
    );
  }

  /**
   * Convert taxonomy term objects into options array [tid => name].
   */
  protected function getLocationOptions(array $terms = []) : array {
    $options = [];
    foreach ($terms as $term) {
      $options[$term->id()] = $term->getName();
    }
    return $options;
  }

  /**
   * Load child locations from taxonomy.
   */
  protected function loadLocations($parent_id, $depth = 1) {
    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    return $term_storage->loadTree('location', $parent_id, $depth, TRUE);
  }

  /**
   * Get location term ID from logged-in user.
   */
  protected function getUserLocation() {
    $uid = $this->currentUser->id();
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if ($user instanceof UserInterface && !$user->get('field_location_details')->isEmpty()) {
      return $user->get('field_location_details')->getString();
    }
    return NULL;
  }

  /**
   * Build location filter form markup (GET, auto-submit on change).
   */
  protected function buildLocationFilters() {
    $roles = $this->currentUser->getRoles();

    $request = $this->requestStack->getCurrentRequest();
    $query   = $request->query;

    $selected_district = $query->get('district') ?: '';
    $selected_block    = $query->get('block') ?: '';
    $selected_ward     = $query->get('ward') ?: '';

    // Build raw HTML form.
    $html = '<form method="get" id="rte-filter-form" class="rte-filter-form js-form-item form-item js-form-type-select form-type--select">';

    if (in_array('state_admin', $roles)) {
      $html .= '<label class="form-item__label">District</label>';
      $html .= '<select class="form-select form-element form-element--type-select" name="district" onchange="this.form.submit();">';
      $html .= '<option value="">All Districts</option>';

      $districts = $this->loadLocations(0, 1);
      foreach ($districts as $term) {
        $sel = ($selected_district == $term->id()) ? 'selected' : '';
        $html .= '<option value="' . $term->id() . '" ' . $sel . '>' . $term->getName() . '</option>';
      }
      $html .= '</select>';

      if (!empty($selected_district)) {
        $html .= '<label class="form-item__label">Block</label>';
        $html .= '<select class="form-select form-element form-element--type-select" name="block" onchange="this.form.submit();">';
        $html .= '<option value="">All Blocks</option>';

        $blocks = $this->loadLocations($selected_district, 1);
        foreach ($blocks as $term) {
          $sel = ($selected_block == $term->id()) ? 'selected' : '';
          $html .= '<option value="' . $term->id() . '" ' . $sel . '>' . $term->getName() . '</option>';
        }
        $html .= '</select>';
      }
    }

    if (in_array('district_admin', $roles)) {
      $district_id = $this->getUserLocation();
      $blocks = $this->loadLocations($district_id, 1);

      $html .= '<label class="form-item__label">Block</label>';
      $html .= '<select class="form-select form-element form-element--type-select" name="block" onchange="this.form.submit();">';
      $html .= '<option value="">All Blocks</option>';
      foreach ($blocks as $term) {
        $sel = ($selected_block == $term->id()) ? 'selected' : '';
        $html .= '<option value="' . $term->id() . '" ' . $sel . '>' . $term->getName() . '</option>';
      }
      $html .= '</select>';

      if (!empty($selected_block)) {
        $html .= '<label class="form-item__label">Ward</label>';
        $html .= '<select class="form-select form-element form-element--type-select" name="ward" onchange="this.form.submit();">';
        $html .= '<option value="">All Wards</option>';

        $wards = $this->loadLocations($selected_block, 1);
        foreach ($wards as $term) {
          $sel = ($selected_ward == $term->id()) ? 'selected' : '';
          $html .= '<option value="' . $term->id() . '" ' . $sel . '>' . $term->getName() . '</option>';
        }
        $html .= '</select>';
      }
    }

    if (in_array('block_admin', $roles)) {
      $district_id = $this->getUserLocation();
      $blocks = $this->loadLocations($district_id, 1);

      $html .= '<label class="form-item__label">Ward</label>';
      $html .= '<select class="form-select form-element form-element--type-select" name="ward" onchange="this.form.submit();">';
      $html .= '<option value="">All Wards</option>';
      foreach ($blocks as $term) {
        $sel = ($selected_ward == $term->id()) ? 'selected' : '';
        $html .= '<option value="' . $term->id() . '" ' . $sel . '>' . $term->getName() . '</option>';
      }
      $html .= '</select>';
    }

    $html .= '</form>';

    return [
      '#type' => 'inline_template',
      '#template' => '{{ html|raw }}',
      '#context' => ['html' => $html],
    ];
  }

  /**
   * Get aggregated school stats for a single district id.
   *
   * @param int|string $district_id
   *   Taxonomy term id of district.
   *
   * @return array
   *   Same format as getStateAdminContent sub-arrays.
   */
  protected function getSchoolStatsForDistrict($district_id): array {
    // School Registration stats.
    $registered_schools = count($this->rteReportHelper->getRegisteredSchoolList($district_id));
    $pending_beo_approval = $this->rteReportHelper->getSchoolStatus($district_id, 'submitted');
    $pending_deo_approval = $this->rteReportHelper->getSchoolStatus($district_id, 'approved_by_beo');
    $approved_schools = count($this->rteReportHelper->getRegisteredSchoolList($district_id, 'approved'));

    // Location ids for claims.
    $location_ids = $this->rteReportHelper->getLocationsForParent('state_admin', $district_id);
    $claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids));
    $reimbursed_claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_payment_completed'));
    $pending_claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_payment_pending'));
    $rejected_claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_rejected'));

    return [
      [
        'type' => 'School Registration',
        'submissions' => $registered_schools,
        'approved' => $approved_schools,
        'rejected' => 0,
        'pending' => $pending_beo_approval + $pending_deo_approval,
      ],
      [
        'type' => 'Reimbursement Claims',
        'submissions' => $claims_count,
        'approved' => $reimbursed_claims_count,
        'rejected' => $rejected_claims_count,
        'pending' => $pending_claims_count,
      ],
    ];
  }

  /**
   * Get aggregated school stats for a single block id.
   *
   * @param int|string $block_id
   *   Taxonomy term id of block.
   *
   * @return array
   *   Same format as getBlockAdminContent sub-arrays.
   */
  protected function getSchoolStatsForBlock($block_id): array {
    $registered_schools = count($this->rteReportHelper->getRegisteredSchoolList($block_id));
    $pending_beo_approval = $this->rteReportHelper->getSchoolStatus($block_id, 'submitted');
    $pending_deo_approval = $this->rteReportHelper->getSchoolStatus($block_id, 'approved_by_beo');
    $approved_schools = count($this->rteReportHelper->getRegisteredSchoolList($block_id, 'approved'));

    $location_ids = $this->rteReportHelper->getLocationsForParent('block_admin', $block_id);
    $claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids));
    $reimbursed_claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_payment_completed'));
    $pending_claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_payment_pending'));
    $rejected_claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_submitted_rejected'));

    return [
      [
        'type' => 'School Registration',
        'submissions' => $registered_schools,
        'approved' => $approved_schools,
        'rejected' => 0,
        'pending' => $pending_beo_approval + $pending_deo_approval,
      ],
      [
        'type' => 'Reimbursement Claims',
        'submissions' => $claims_count,
        'approved' => $reimbursed_claims_count,
        'rejected' => $rejected_claims_count,
        'pending' => $pending_claims_count,
      ],
    ];
  }

  /**
   * Build filter form and tables, applying GET filters.
   */
  public function build() {
    $roles = $this->currentUser->getRoles();
    $request = $this->requestStack->getCurrentRequest();

    // Read GET parameters for filters.
    $selected_district = $request->query->get('district') ?: NULL;
    $selected_block    = $request->query->get('block') ?: NULL;
    $selected_ward     = $request->query->get('ward') ?: NULL;

    $schoolStats = [];
    $studentStats = [];

    // Determine stats based on roles and selected filters.
    if (array_intersect(['app_admin', 'state_admin'], $roles)) {
      // STATE level user: allow district/block filter.
      if (!empty($selected_block)) {
        // Block selected: show stats for that block.
        $schoolStats = $this->getSchoolStatsForBlock($selected_block);
        $studentStats = [
          [
            'type' => 'Student Application',
            'applications' => $this->studentDetails('state_admin', $selected_block),
            'seat_allotted' => $this->studentStatus('state_admin', $selected_block, 'allotted'),
            'admitted' => $this->studentStatus('state_admin', $selected_block, 'admitted'),
            'dropped' => $this->studentStatus('state_admin', $selected_block, 'dropout'),
          ],
        ];
      }
      elseif (!empty($selected_district)) {
        // District selected: show stats for that district.
        $schoolStats = $this->getSchoolStatsForDistrict($selected_district);
        $studentStats = [
          [
            'type' => 'Student Application',
            'applications' => $this->studentDetails('state_admin', $selected_district),
            'seat_allotted' => $this->studentStatus('state_admin', $selected_district, 'allotted'),
            'admitted' => $this->studentStatus('state_admin', $selected_district, 'admitted'),
            'dropped' => $this->studentStatus('state_admin', $selected_district, 'dropout'),
          ],
        ];
      }
      else {
        // No filter selected: default state-level aggregated view.
        $schoolStats = $this->getStateAdminContent();
        $studentStats = $this->getStateAdminStudentContent();
      }
    }
    elseif (in_array('district_admin', $roles)) {
      // District admin: can filter by block (their district's blocks)
      if (!empty($selected_ward)) {
        // Ward selected - show ward-level stats.
        $schoolStats = $this->getSchoolStatsForBlock($selected_ward);
        $studentStats = [
          [
            'type' => 'Student Application',
            'applications' => $this->studentDetails('district_admin', $selected_ward),
            'seat_allotted' => $this->studentStatus('district_admin', $selected_ward, 'allotted'),
            'admitted' => $this->studentStatus('district_admin', $selected_ward, 'admitted'),
            'dropped' => $this->studentStatus('district_admin', $selected_ward, 'dropout'),
          ],
        ];
      }
      elseif (!empty($selected_block)) {
        // Block selected - show block-level stats.
        $schoolStats = $this->getSchoolStatsForBlock($selected_block);
        $studentStats = [
          [
            'type' => 'Student Application',
            'applications' => $this->studentDetails('district_admin', $selected_block),
            'seat_allotted' => $this->studentStatus('district_admin', $selected_block, 'allotted'),
            'admitted' => $this->studentStatus('district_admin', $selected_block, 'admitted'),
            'dropped' => $this->studentStatus('district_admin', $selected_block, 'dropout'),
          ],
        ];
      }
      else {
        // No block filter: district aggregated view.
        $schoolStats = $this->getDistrictAdminContent();
        $studentStats = $this->getDistrictAdminStudentContent();
      }
    }
    elseif (in_array('block_admin', $roles)) {
      if (!empty($selected_ward)) {
        // Ward selected - show ward-level stats.
        $schoolStats = $this->getSchoolStatsForBlock($selected_ward);
        $studentStats = [
          [
            'type' => 'Student Application',
            'applications' => $this->studentDetails('block_admin', $selected_ward),
            'seat_allotted' => $this->studentStatus('block_admin', $selected_ward, 'allotted'),
            'admitted' => $this->studentStatus('block_admin', $selected_ward, 'admitted'),
            'dropped' => $this->studentStatus('block_admin', $selected_ward, 'dropout'),
          ],
        ];
      }
      else {
        // No ward filter: block aggregated view.
        $schoolStats = $this->getBlockAdminContent();
        $studentStats = $this->getBlockAdminStudentContent();
      }
    }
    else {
      // Fallback: show nothing or empty arrays.
      $schoolStats = [];
      $studentStats = [];
    }

    $build = [];

    // Build Filters UI. Wrap in container so it appears above tables.
    $filter_form = $this->buildLocationFilters();
    if (!empty($filter_form)) {
      $build['filters'] = $filter_form;
    }

    // Define school table header.
    $schoolHeader = [
      $this->t('School Information'),
      $this->t('Submissions'),
      $this->t('Approved'),
      $this->t('Rejected'),
      $this->t('Pending'),
    ];

    // Build school rows.
    $schoolRows = [];
    if (!empty($schoolStats)) {
      foreach ($schoolStats as $item) {
        $schoolRows[] = [
          'data' => [
            $item['type'] ?? '',
            $item['submissions'] ?? 0,
            $item['approved'] ?? 0,
            $item['rejected'] ?? 0,
            $item['pending'] ?? 0,
          ],
        ];
      }
    }

    $build['school_table'] = [
      '#type' => 'table',
      '#header' => $schoolHeader,
      '#rows' => $schoolRows,
      '#attributes' => [
        'class' => ['rte-task-status-table', 'table', 'table-striped', 'table-bordered'],
      ],
    ];

    // Students table header.
    $studentHeader = [
      $this->t('Students Information'),
      $this->t('Applications'),
      $this->t('Seat Allotted'),
      $this->t('Admitted'),
      $this->t('Dropped'),
    ];

    // Build student rows.
    $studentRows = [];
    if (!empty($studentStats)) {
      foreach ($studentStats as $student) {
        $studentRows[] = [
          'data' => [
            $student['type'] ?? '',
            $student['applications'] ?? 0,
            $student['seat_allotted'] ?? 0,
            $student['admitted'] ?? 0,
            $student['dropped'] ?? 0,
          ],
        ];
      }
    }

    $build['student_table'] = [
      '#type' => 'table',
      '#header' => $studentHeader,
      '#rows' => $studentRows,
      '#attributes' => [
        'class' => ['rte-task-status-table', 'table', 'table-striped', 'table-bordered'],
      ],
    ];

    // Optional: attach existing CSS library for styling.
    $build['#attached']['library'][] = 'rte_mis_gin/rte_mis_dashboard';
    // IMPORTANT: Make block rebuild when filters change.
    $build['#cache']['contexts'][] = 'url.query_args:district';
    $build['#cache']['contexts'][] = 'url.query_args:block';
    $build['#cache']['contexts'][] = 'url.query_args:ward';

    return $build;
  }

  /**
   * Gets the school_admins based on the user's role and location.
   */
  public function getSchoolList(?string $locationId = NULL) {
    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $location_tree = $term_storage->loadTree('location', $locationId, NULL, TRUE);
    $locations = [];
    $schools = [];

    if ($location_tree) {
      foreach ($location_tree as $value) {
        $locations[] = $value->id();
      }
    }

    if (!empty($locations)) {
      $query = $this->entityTypeManager->getStorage('user')
        ->getQuery()
        ->condition('roles', 'school_admin')
        ->condition('status', 1)
        ->condition('field_school_details.entity:mini_node.field_location', $locations, 'IN')
        ->condition('field_school_details.entity:mini_node.field_school_verification', 'school_registration_verification_approved_by_deo')
        ->accessCheck(FALSE);
      $schools = $query->execute();
      return $schools;
    }

    return [];
  }

  /**
   * Function to check student allotment status.
   */
  public function studentStatus($current_role, ?string $id = NULL, $status = NULL): int {
    $status_map = [
      'admitted'      => 'student_admission_workflow_admitted',
      'not_admitted'  => 'student_admission_workflow_not_admitted',
      'dropout'       => 'student_admission_workflow_dropout',
      'allotted'      => 'student_admission_workflow_allotted',
    ];

    $query = $this->entityTypeManager->getStorage('mini_node')->getQuery()
      ->condition('type', 'allocation')
      ->accessCheck(FALSE);

    $school_list = [];

    if (in_array($current_role, ['state_admin', 'district_admin'])) {
      $school_admins = $this->getSchoolList($id);
      foreach ($school_admins as $uid) {
        $user = $this->entityTypeManager->getStorage('user')->load($uid);
        if ($user instanceof UserInterface && !$user->get('field_school_details')->isEmpty()) {
          $school_list[] = $user->get('field_school_details')->getString();
        }
      }
    }
    elseif ($current_role === 'block_admin' && !empty($id)) {
      $school_list[] = $id;
    }

    if (empty($school_list)) {
      return 0;
    }

    $query->condition('field_school', $school_list, 'IN');
    if (!empty($status) && isset($status_map[$status])) {
      $query->condition('field_student_allocation_status', $status_map[$status]);
    }

    $student_ids = $query->execute();
    return is_array($student_ids) ? count($student_ids) : 0;
  }

  /**
   * Retrieves the count of students based on role, location, and status.
   */
  public function studentDetails($current_role, ?string $id = NULL, $status = NULL): int {
    $status_map = [
      'applied'    => 'student_workflow_submitted',
      'duplicate'  => 'student_workflow_duplicate',
      'incomplete' => 'student_workflow_incomplete',
      'rejected'   => 'student_workflow_rejected',
      'approved'   => 'student_workflow_approved',
    ];

    if (array_intersect(['app_admin', 'state_admin', 'district_admin'], (array) $current_role)) {
      /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $location_tree = $term_storage->loadTree('location', $id, NULL, TRUE);

      $location_ids = array_map(static fn($term) => $term->id(), $location_tree);

      if ($location_ids) {
        $query = $this->entityTypeManager->getStorage('mini_node')
          ->getQuery()
          ->condition('type', 'student_details')
          ->condition('field_location', $location_ids, 'IN')
          ->accessCheck(FALSE);

        if (!empty($status) && isset($status_map[$status])) {
          $query->condition('field_student_verification', $status_map[$status]);
        }

        $students = $query->execute();
        return is_array($students) ? count($students) : 0;
      }
    }

    if ($current_role === 'block_admin') {
      /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $location_tree = $term_storage->loadTree('location', $id, NULL, TRUE);

      $location_ids = array_map(static fn($term) => $term->id(), $location_tree);
      if (empty($location_ids)) {
        return 0;
      }
      if (!empty($location_ids)) {
        $query = $this->entityTypeManager->getStorage('mini_node')
          ->getQuery()
          ->condition('type', 'student_details')
          ->condition('field_location', $location_ids, 'IN')
          ->accessCheck(FALSE);

        if (!empty($status) && isset($status_map[$status])) {
          $query->condition('field_student_verification', $status_map[$status]);
        }

        $students = $query->execute();
        return is_array($students) ? count($students) : 0;
      }
    }
    return 0;
  }

  /**
   * Collects State-admin stats.
   */
  protected function getStateAdminContent(): array {
    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $districts = $term_storage->loadTree('location', 0, 1, TRUE);

    $registered_schools = 0;
    $approved_schools = 0;
    $pending_beo_approval = 0;
    $pending_deo_approval = 0;
    $claims_count = 0;
    $reimbursed_claims_count = 0;
    $pending_claims_count = 0;
    $rejected_claims_count = 0;

    if (!empty($districts)) {
      foreach ($districts as $district) {
        $district_id = $district->id();
        $location_ids = $this->rteReportHelper->getLocationsForParent('state_admin', $district_id);
        $registered_schools += count($this->rteReportHelper->getRegisteredSchoolList($district_id));
        $pending_beo_approval += $this->rteReportHelper->getSchoolStatus($district_id, 'submitted');
        $pending_deo_approval += $this->rteReportHelper->getSchoolStatus($district_id, 'approved_by_beo');
        $approved_schools += count($this->rteReportHelper->getRegisteredSchoolList($district_id, 'approved'));
        $claims_count += count($this->rteReportHelper->getReimbursementClaims($location_ids));
        $reimbursed_claims_count += count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_payment_completed'));
        $pending_claims_count += count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_payment_pending'));
        $rejected_claims_count += count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_submitted_rejected'));
      }
    }

    return [
      [
        'type' => 'School Registration',
        'submissions' => $registered_schools,
        'approved' => $approved_schools,
        'rejected' => 0,
        'pending' => $pending_beo_approval + $pending_deo_approval,
      ],
      [
        'type' => 'Reimbursement Claims',
        'submissions' => $claims_count,
        'approved' => $reimbursed_claims_count,
        'rejected' => $rejected_claims_count,
        'pending' => $pending_claims_count,
      ],
    ];
  }

  /**
   * Get values for students data (for State Admin dashboard).
   */
  protected function getStateAdminStudentContent() {
    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $districts = $term_storage->loadTree('location', 0, 1, TRUE);
    $total_applications = 0;
    $total_admitted = 0;
    $total_allotted = 0;
    $total_dropout = 0;
    if (!empty($districts)) {
      foreach ($districts as $district) {
        $total_applications += $this->studentDetails('state_admin', $district->id());
        $total_allotted += $this->studentStatus('state_admin', $district->id(), 'allotted');
        $total_admitted += $this->studentStatus('state_admin', $district->id(), 'admitted');
        $total_dropout += $this->studentStatus('state_admin', $district->id(), 'dropout');
      }
    }
    return [
      [
        'type' => 'Student Application',
        'applications' => $total_applications,
        'seat_allotted' => $total_allotted,
        'admitted' => $total_admitted,
        'dropped' => $total_dropout,
      ],
    ];
  }

  /**
   * Collects District-admin stats.
   */
  protected function getDistrictAdminContent(): array {
    $currentUserId = $this->currentUser->id();
    $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);
    if ($currentUser instanceof UserInterface) {
      $locationId = $currentUser->get('field_location_details')->getString() ?? NULL;
    }
    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $districts = $term_storage->loadTree('location', $locationId, 1, TRUE);
    $location_ids = $this->rteReportHelper->getLocationsForParent('district_admin', $locationId);

    $registered_schools = 0;
    $approved_schools = 0;
    $pending_beo_approval = 0;
    $pending_deo_approval = 0;
    $claims_count = 0;
    $reimbursed_claims_count = 0;
    $pending_claims_count = 0;
    $rejected_claims_count = 0;
    if ($location_ids) {
      $claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids));
      $reimbursed_claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_payment_completed'));
      $pending_claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_payment_pending'));
      $rejected_claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_submitted_rejected'));
    }
    if (!empty($districts)) {
      foreach ($districts as $district) {
        $district_id = $district->id();
        $registered_schools += count($this->rteReportHelper->getRegisteredSchoolList($district_id));
        $pending_beo_approval += $this->rteReportHelper->getSchoolStatus($district_id, 'submitted');
        $pending_deo_approval += $this->rteReportHelper->getSchoolStatus($district_id, 'approved_by_beo');
        $approved_schools += count($this->rteReportHelper->getRegisteredSchoolList($district_id, 'approved'));
      }
    }

    return [
      [
        'type' => 'School Registration',
        'submissions' => $registered_schools,
        'approved' => $approved_schools,
        'rejected' => 0,
        'pending' => $pending_beo_approval + $pending_deo_approval,
      ],
      [
        'type' => 'Reimbursement Claims',
        'submissions' => $claims_count,
        'approved' => $reimbursed_claims_count,
        'rejected' => $rejected_claims_count,
        'pending' => $pending_claims_count,
      ],
    ];
  }

  /**
   * Get values for students data (for District Admin dashboard).
   */
  protected function getDistrictAdminStudentContent() {
    $currentUserId = $this->currentUser->id();
    $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);
    if ($currentUser instanceof UserInterface) {
      $locationId = $currentUser->get('field_location_details')->getString() ?? NULL;
    }
    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $districts = $term_storage->loadTree('location', $locationId, 1, TRUE);

    $total_applications = 0;
    $total_admitted = 0;
    $total_allotted = 0;
    $total_dropout = 0;
    if (!empty($districts)) {
      foreach ($districts as $district) {
        $total_applications += $this->studentDetails('district_admin', $district->id());
        $total_allotted += $this->studentStatus('district_admin', $district->id(), 'allotted');
        $total_admitted += $this->studentStatus('district_admin', $district->id(), 'admitted');
        $total_dropout += $this->studentStatus('district_admin', $district->id(), 'dropout');
      }
    }
    return [
      [
        'type' => 'Student Application',
        'applications' => $total_applications,
        'seat_allotted' => $total_allotted,
        'admitted' => $total_admitted,
        'dropped' => $total_dropout,
      ],
    ];
  }

  /**
   * Collects Block-admin stats.
   */
  protected function getBlockAdminContent(): array {
    $currentUserId = $this->currentUser->id();
    $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);
    $locationId = NULL;
    if ($currentUser instanceof UserInterface) {
      $locationId = $currentUser->get('field_location_details')->getString() ?? NULL;
    }
    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $blocks = $term_storage->loadTree('location', $locationId, 1, TRUE);

    $registered_schools = 0;
    $approved_schools = 0;
    $pending_beo_approval = 0;
    $pending_deo_approval = 0;
    $claims_count = 0;
    $reimbursed_claims_count = 0;
    $pending_claims_count = 0;
    $rejected_claims_count = 0;

    if (!empty($blocks)) {
      foreach ($blocks as $block) {
        $block_id = $block->id();
        $location_ids = $this->rteReportHelper->getLocationsForParent('block_admin', $block_id);
        $claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids));
        $reimbursed_claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_payment_completed'));
        $pending_claims_count += count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_payment_pending'));
        $rejected_claims_count += count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_submitted_rejected'));
        $registered_schools += count($this->rteReportHelper->getRegisteredSchoolList($block_id));
        $pending_beo_approval += $this->rteReportHelper->getSchoolStatus($block_id, 'submitted');
        $pending_deo_approval += $this->rteReportHelper->getSchoolStatus($block_id, 'approved_by_beo');
        $approved_schools += count($this->rteReportHelper->getRegisteredSchoolList($block_id, 'approved'));
      }
    }

    return [
      [
        'type' => 'School Registration',
        'submissions' => $registered_schools,
        'approved' => $approved_schools,
        'rejected' => 0,
        'pending' => $pending_beo_approval + $pending_deo_approval,
      ],
      [
        'type' => 'Reimbursement Claims',
        'submissions' => $claims_count,
        'approved' => $reimbursed_claims_count,
        'rejected' => $rejected_claims_count,
        'pending' => $pending_claims_count,
      ],
    ];
  }

  /**
   * Get values for students data (for Block Admin dashboard).
   */
  protected function getBlockAdminStudentContent() {
    $currentUserId = $this->currentUser->id();
    $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);
    if ($currentUser instanceof UserInterface) {
      $locationId = $currentUser->get('field_location_details')->getString() ?? NULL;
    }

    $total_applications = 0;
    $total_admitted = 0;
    $total_allotted = 0;
    $total_dropout = 0;
    $total_applications = $this->studentDetails('district_admin', $locationId);
    $total_allotted = $this->studentStatus('district_admin', $locationId, 'allotted');
    $total_admitted = $this->studentStatus('district_admin', $locationId, 'admitted');
    $total_dropout = $this->studentStatus('district_admin', $locationId, 'dropout');

    return [
      [
        'type' => 'Student Application',
        'applications' => $total_applications,
        'seat_allotted' => $total_allotted,
        'admitted' => $total_admitted,
        'dropped' => $total_dropout,
      ],
    ];
  }

}
