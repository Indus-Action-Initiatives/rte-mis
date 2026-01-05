<?php

declare(strict_types=1);

namespace Drupal\rte_mis_allocation\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\rte_mis_allocation\Form\StudentAdmissionReportFiltersForm;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides role based details for student.
 */
final class StudentAdmissionReportController extends ControllerBase {

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
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  public $configFactory;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The request service.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Constructs the controller instance.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    ConfigFactoryInterface $config_factory,
    $form_builder,
    $request,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->configFactory = $config_factory;
    $this->formBuilder = $form_builder;
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('config.factory'),
      $container->get('form_builder'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * Get the current filters from the request.
   */
  protected function getFilters(): array {
    $form = new StudentAdmissionReportFiltersForm();
    $years = $form->getAcademicYearOptions();
    $default = array_key_first($years);

    return [
      'admission_cycle'    => $this->request->query->get('admission_cycle', $default),
      'application_status' => $this->request->query->get('application_status', 'approved'),
      'allotment_status'   => $this->request->query->get('allotment_status', 'allotted'),
      'admission_status'   => $this->request->query->get('admission_status', 'admitted'),
    ];
  }

  /**
   * Acces for district and block admin.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current user account.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Current routeMatch.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   If the user can access to the allotment report dashboard.
   */
  public function access(AccountInterface $account, RouteMatchInterface $routeMatch) {
    // Checks if a district/block admin cannot access
    // their adjacent or above hierarchy data.
    $id = $routeMatch->getParameter('id') ?? NULL;
    $currentUser = $this->entityTypeManager->getStorage('user')->load($account->id());
    $currentUserRole = $currentUser->getRoles(TRUE);
    $currentUserLocation = $currentUser->get('field_location_details')->getString() ?? NULL;

    if (in_array('district_admin', $currentUserRole) && $currentUserLocation) {
      $locationTree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $currentUserLocation, NULL, FALSE);
      if ($currentUserLocation == $id) {
        return AccessResult::allowed()->setCacheMaxAge(0);
      }
      foreach ($locationTree as $value) {
        if ($value->tid == $id) {
          // If the location id is below block return access denied.
          if ($value->depth >= 1) {
            return AccessResult::forbidden()->setCacheMaxAge(0);
          }
          return AccessResult::allowed()->setCacheMaxAge(0);
        }
      }
      return AccessResult::allowedIf($id == NULL)->setCacheMaxAge(0);
    }
    elseif (in_array('block_admin', $currentUserRole) && $currentUserLocation) {
      if ($currentUserLocation == $id) {
        return AccessResult::allowed()->setCacheMaxAge(0);
      }
      return AccessResult::allowedIf($id == NULL)->setCacheMaxAge(0);
    }

    return AccessResult::allowed()->setCacheMaxAge(0);
  }

  /**
   * Displays the role based details.
   *
   * @return array
   *   A render array.
   */
  public function build(?string $id = NULL) {

    if ((is_numeric($id) && $this->checkLocation($id)) || $id == NULL) {
      $currentUserId = $this->currentUser->id();
      $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);

      if ($currentUser instanceof UserInterface) {
        if (array_intersect(['district_admin', 'block_admin'], $currentUser->getRoles(TRUE))) {
          // Get location ID from user field.
          $locationId = $currentUser->get('field_location_details')->getString() ?? NULL;
          if (!$id && $locationId) {
            $query = $this->request->query->all();

            $url = Url::fromRoute(
              'rte_mis_allocation.controller.student_admission_report',
              ['id' => $locationId],
              ['query' => $query]
            );

            /** @var \Drupal\Core\GeneratedUrl $generated_url */
            $generated_url = $url->toString(TRUE);

            return new RedirectResponse($generated_url->getGeneratedUrl());
          }
        }
      }

      $term = NULL;
      if ($id) {
        $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($id);
      }

      if (!$id) {
        $page_title = "District Wise Students Admissions Report";
      }
      elseif ($term && !$term->parent->target_id) {
        $page_title = "Block Wise Students Admissions Report – " . $term->label();
      }
      else {
        $page_title = "Schools Wise Admissions Report – " . $term->label();
      }

      $build = [];
      $build = [
        '#type' => 'container',
        '#attributes' => ['class' => ['student-report-wrapper']],
        'heading' => [
          '#markup' => '<h2 class="student-report-title">' . $page_title . '</h2>',
        ],
      ];

      $build['filters'] = $this->formBuilder->getForm(
        StudentAdmissionReportFiltersForm::class,
        $id
      );

      // Create a table with data.
      $build['table'] = [
        '#type' => 'table',
        '#header' => $this->getHeaders($id),
        '#rows' => $this->getData($id),
        '#prefix' => '<div class="allotment-report-wrapper">',
        '#suffix' => '</div>',
        '#attributes' => ['class' => ['student-reports']],
        '#empty' => $this->t('No data to display.'),
        '#cache' => [
          'contexts' => [
            'user',
            'url.query_args:admission_cycle',
            'url.query_args:application_status',
            'url.query_args:allotment_status',
            'url.query_args:admission_status',
          ],
          'tags' => [
            'user_list',
            'taxonomy_term_list',
            'mini_node_list',
          ],
        ],
      ];

      if (array_intersect(['state_admin', 'app_admin'], $currentUser->getRoles(TRUE))) {
        // Ensure cache contexts exist and are an array.
        if (empty($build['table']['#cache']['contexts'])) {
          $build['table']['#cache']['contexts'] = [];
        }
        $route = $id ? 'rte_mis_allocation.export_excel_with_id' : 'rte_mis_allocation.export_excel';
        $pdf_route = $id ? 'rte_mis_allocation.export_pdf_with_id' : 'rte_mis_allocation.export_pdf';
        $params = $id ? ['id' => $id] : [];

        // Merge filter-based cache contexts.
        $build['table']['#cache']['contexts'] = array_merge(
          $build['table']['#cache']['contexts'],
          [
            'url.query_args:application_status',
            'url.query_args:allotment_status',
            'url.query_args:admission_status',
            'url.query_args:admission_cycle',
          ]
        );
      }

      $excel_url = Url::fromRoute($route, $params, ['query' => $this->request->query->all()])->toString();
      $pdf_url   = Url::fromRoute($pdf_route, $params, ['query' => $this->request->query->all()])->toString();

      $build['export'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['export-buttons']],
        'dropdown' => [
          '#type' => 'inline_template',
          '#template' => '
            <div class="export-dropdown">
              <button class="export-btn">{{ "Download"|t }} ▼</button>
              <ul class="export-menu">
                <li><a href="{{ excel }}">{{ "Download Excel"|t }}</a></li>
                <li><a href="{{ pdf }}">{{ "Download PDF"|t }}</a></li>
              </ul>
            </div>
          ',
          '#context' => [
            'excel' => $excel_url,
            'pdf' => $pdf_url,
          ],
        ],
      ];

      $build['#attached']['library'][] = 'rte_mis_gin/rte_mis_student-report';

      return $build;

    }
    else {
      throw new NotFoundHttpException();
    }
  }

  /**
   * Function to get the current academic year filter.
   */
  protected function getAcademicYear(): ?string {
    return $this->getFilters()['admission_cycle'] ?? NULL;
  }

  /**
   * Function to check if location exists.
   */
  protected function checkLocation($id) {
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
      'vid' => 'location',
      'tid' => $id,
      'status' => 1,
    ]);
    return (bool) $term;
  }

  /**
   * Function to get the headers.
   */
  public function getHeaders($id = NULL) {

    $currentUserRole = $this->currentUser->getRoles(TRUE);

    // Return header based on the user role.
    $header = [
      $this->t('Schools'), $this->t('RTE seats'), $this->t('Applications Recieved'), $this->getApplicationStatusColumnLabel(), $this->getAllotmentStatusColumnLabel(), $this->getAdmissionStatusColumnLabel(),
    ];

    if (array_intersect(['app_admin', 'state_admin'], $currentUserRole) && !$id) {
      $header = array_merge([
        $this->t('No.'), $this->t('District Name'), $this->t('Block'),
      ], $header);
    }
    else {
      $locationMap = [];
      $locationTree = $this->entityTypeManager->getStorage('taxonomy_term')->loadtree('location', 0, 2, FALSE);
      foreach ($locationTree as $term) {
        // Add tid and depth to the map.
        $locationMap[$term->tid] = $term->depth;
      }

      if (array_key_exists($id, $locationMap)) {
        $depth = $locationMap[$id];
        if ($depth == 0) {
          // It is a district location.
          $header = array_merge([
            $this->t('No.'), $this->t('Block'),
          ], $header);
        }
        else {
          // It is a block location.
          $header = array_merge([
            $this->t('No.'),
          ], $header);
        }
      }

    }

    return (array) $header;
  }

  /**
   * Function to get the row data.
   */
  public function getData($id = NULL) {
    $content = [];
    $currentUserRole = $this->currentUser->getRoles(TRUE);

    if (array_intersect(['app_admin', 'state_admin'], $currentUserRole) && !$id) {
      $content = $this->getStateAdminContent($id);
    }
    else {
      $locationMap = [];
      $locationTree = $this->entityTypeManager->getStorage('taxonomy_term')->loadtree('location', 0, 2, FALSE);
      foreach ($locationTree as $term) {
        // Add tid and depth to the map.
        $locationMap[$term->tid] = $term->depth;
      }

      if (array_key_exists($id, $locationMap)) {
        $depth = $locationMap[$id];
        if ($depth == 0) {
          // It is a district location.
          $content = $this->getDistrictAdminContent($id);
        }
        else {
          // It is a block location.
          $content = $this->getBlockAdminContent($id);
        }
      }

    }

    return $content;
  }

  /**
   * Get application status column label.
   */
  protected function getApplicationStatusColumnLabel(): string {
    $filters = $this->getFilters();

    $map = [
      'verified'   => 'Verified',
      'duplicate'  => 'Duplicate',
      'incomplete' => 'Incomplete',
      'rejected'   => 'Rejected',
      'approved'   => 'Approved',
    ];

    $status = $filters['application_status'] ?? 'approved';
    $label = $map[$status] ?? 'Approved';

    return (string) $this->t('Application by Status (@status)', [
      '@status' => $label,
    ]);
  }

  /**
   * Get application status value from counts.
   */
  protected function getApplicationStatusValue(array $counts): int {
    $status = $this->getFilters()['application_status'] ?? 'approved';

    return match ($status) {
      'verified'   => $counts['applied'],
      'duplicate'  => $counts['duplicate'],
      'incomplete' => $counts['incomplete'],
      'rejected'   => $counts['rejected'],
      'approved'   => $counts['approved'],
      default      => $counts['approved'],
    };
  }

  /**
   * Get allotment status column Label.
   */
  protected function getAllotmentStatusColumnLabel(): string {
    $filters = $this->getFilters();

    $map = [
      'allotted'   => 'Allotted',
      'unallotted' => 'Unallotted',
    ];

    $status = $filters['allotment_status'] ?? 'allotted';
    $label = $map[$status] ?? 'Allotted';

    return (string) $this->t('@status', [
      '@status' => $label,
    ]);
  }

  /**
   * Get allotment status column Value.
   */
  protected function getAllotmentStatusValue(array $counts): string {
    $status = $this->getFilters()['allotment_status'] ?? 'allotted';

    return match ($status) {
      'allotted'   => (string) $counts['allotted'],
      'unallotted' => (string) $counts['unallotted'],
      default      => (string) $counts['allotted'],
    };
  }

  /**
   * Get admission status column Label.
   */
  protected function getAdmissionStatusColumnLabel(): string {
    $filters = $this->getFilters();

    $map = [
      'admitted'   => 'Admitted',
      'unadmitted' => 'Unadmitted',
    ];

    $status = $filters['admission_status'] ?? 'admitted';
    $label = $map[$status] ?? 'Admitted';

    return (string) $this->t('@status', [
      '@status' => $label,
    ]);
  }

  /**
   * Get admission status column Value.
   */
  protected function getAdmissionStatusValue(array $counts): string {
    $status = $this->getFilters()['admission_status'] ?? 'admitted';
    return match ($status) {
      'admitted'   => (string) $counts['admitted'],
      'unadmitted' => (string) $counts['unadmitted'],
      default      => (string) $counts['admitted'],
    };
  }

  /**
   * Get content for state admin.
   */
  protected function getStateAdminContent($id = NULL) {
    // Implemented data fetching logic.
    // Serial Number.
    $serialNumber = 1;
    $districts = $this->entityTypeManager()->getStorage('taxonomy_term')->loadTree('location', 0, 1, TRUE) ?? NULL;
    $data = [];
    if ($districts) {
      foreach ($districts as $district) {
        $blocks = $this->getBlocksCount($district->id());
        $schools = count($this->getSchoolList($district->id()));
        $total_rte_seats = $this->totalRteSeats('state_admin', $district->id());
        $total_applications = $this->studentDetails('state_admin', $district->id());
        $total_applied = $this->studentDetails('state_admin', $district->id(), 'applied');
        $total_duplicate = $this->studentDetails('state_admin', $district->id(), 'duplicate');
        $total_incomplete = $this->studentDetails('state_admin', $district->id(), 'incomplete');
        $total_rejected = $this->studentDetails('state_admin', $district->id(), 'rejected');
        $total_approved = $this->studentDetails('state_admin', $district->id(), 'approved');
        $total_allotted = $this->studentStatus('state_admin', $district->id(), 'allotted');
        // $total_unallotted = $total_approved - $total_allotted;
        $total_admitted = $this->studentStatus('state_admin', $district->id(), 'admitted');
        $total_not_admitted = $this->studentStatus('state_admin', $district->id(), 'not_admitted');
        // $total_not_admitted = $total_allotted - $total_admitted;
        $total_dropout = $this->studentStatus('state_admin', $district->id(), 'dropout');
        $total_unallotted = $total_approved - ($total_allotted + $total_admitted + $total_not_admitted + $total_dropout);
        $application_by_status = $this->getApplicationStatusValue([
          'applied'    => $total_applied,
          'duplicate'  => $total_duplicate,
          'incomplete' => $total_incomplete,
          'rejected'   => $total_rejected,
          'approved'   => $total_approved,
        ]);

        $allotment_by_status = $this->getAllotmentStatusValue([
          'allotted'   => $total_allotted,
          'unallotted' => $total_unallotted,
        ]);

        $admission_by_status = $this->getAdmissionStatusValue([
          'admitted'   => $total_admitted,
          'unadmitted' => $total_not_admitted + $total_dropout,
        ]);

        // Create link render array.
        $block_id = $district->id();
        $query = $this->request->query->all();
        $url = Url::fromRoute(
          'rte_mis_allocation.controller.student_admission_report',
          ['id' => $block_id],
          ['query' => $query]
        );
        // $url = Url::fromUri("internal:/student-admission-report/{$block_id}");
        $link = Link::fromTextAndUrl($district->label(), $url)->toRenderable();

        $data[] = [$serialNumber, ['data' => $link], $blocks, $schools, $total_rte_seats,
          $total_applications, $application_by_status, $allotment_by_status, $admission_by_status,
        ];
        $serialNumber++;
      }

      return $data;
    }
    // Return a markup about missing location.
    return 0;
  }

  /**
   * Get content for district admin.
   */
  protected function getDistrictAdminContent($id = NULL) {
    // Implemented data fetching logic.
    // Serial Number.
    $serialNumber = 1;
    if ($id == NULL) {
      $currentUserId = $this->currentUser->id();
      /** @var \Drupal\user\Entity\User */
      $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);
      if ($currentUser instanceof UserInterface) {
        // Get location ID from user field.
        $locationId = $currentUser->get('field_location_details')->getString() ?? NULL;
      }
    }
    else {
      $locationId = $id;
    }

    if ($locationId) {
      $data = [];
      $blocks = $this->entityTypeManager()->getStorage('taxonomy_term')->loadTree('location', $locationId, 1, TRUE) ?? NULL;
      if ($blocks) {
        foreach ($blocks as $block) {
          $schools = count($this->getSchoolList($block->id()));
          $total_rte_seats = $this->totalRteSeats('district_admin', $block->id());
          $total_applications = $this->studentDetails('district_admin', $block->id());
          $total_applied = $this->studentDetails('district_admin', $block->id(), 'applied');
          $total_duplicate = $this->studentDetails('district_admin', $block->id(), 'duplicate');
          $total_incomplete = $this->studentDetails('district_admin', $block->id(), 'incomplete');
          $total_rejected = $this->studentDetails('district_admin', $block->id(), 'rejected');
          $total_approved = $this->studentDetails('district_admin', $block->id(), 'approved');
          $total_allotted = $this->studentStatus('district_admin', $block->id(), 'allotted');
          // $total_unallotted = $total_approved - $total_allotted;
          $total_admitted = $this->studentStatus('district_admin', $block->id(), 'admitted');
          $total_not_admitted = $this->studentStatus('district_admin', $block->id(), 'not_admitted');
          // $total_not_admitted = $total_allotted - $total_admitted;
          $total_dropout = $this->studentStatus('district_admin', $block->id(), 'dropout');
          $total_unallotted = $total_approved - ($total_allotted + $total_admitted + $total_not_admitted + $total_dropout);
          $application_by_status = $this->getApplicationStatusValue([
            'applied'    => $total_applied,
            'duplicate'  => $total_duplicate,
            'incomplete' => $total_incomplete,
            'rejected'   => $total_rejected,
            'approved'   => $total_approved,
          ]);
          $allotment_by_status = $this->getAllotmentStatusValue([
            'allotted'   => $total_allotted,
            'unallotted' => $total_unallotted,
          ]);

          $admission_by_status = $this->getAdmissionStatusValue([
            'admitted'   => $total_admitted,
            'unadmitted' => $total_not_admitted + $total_dropout,
          ]);

          // Create link render array.
          $block_id = $block->id();
          $query = $this->request->query->all();
          $url = Url::fromRoute(
            'rte_mis_allocation.controller.student_admission_report',
            ['id' => $block_id],
            ['query' => $query]
          );
          // $url = Url::fromUri("internal:/student-admission-report/{$block_id}");
          $link = Link::fromTextAndUrl($block->label(), $url)->toRenderable();

          $data[] = [$serialNumber, ['data' => $link], $schools, $total_rte_seats,
            $total_applications, $application_by_status, $allotment_by_status, $admission_by_status,
          ];
          $serialNumber++;
        }
        return $data;
      }

    }
    return 0;

  }

  /**
   * Get content for block admin.
   */
  protected function getBlockAdminContent($id = NULL) {
    // Implemented data fetching logic.
    // Serial Number.
    $serialNumber = 1;

    if ($id == NULL) {
      $currentUserId = $this->currentUser->id();

      /** @var \Drupal\user\Entity\User */
      $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);

      if ($currentUser instanceof UserInterface) {
        // Get location ID from user field.
        $locationId = $currentUser->get('field_location_details')->getString() ?? NULL;
      }
    }
    else {
      $locationId = $id;
    }

    if ($locationId) {
      $data = [];
      $schools = $this->getSchoolList($locationId);
      if (empty($schools)) {
        return 0;
      }
      foreach ($schools as $school) {
        $school_miniNode = $this->entityTypeManager->getStorage('user')->load($school)->get('field_school_details')->referencedEntities();
        $school_miniNode = reset($school_miniNode);
        $total_rte_seats = $this->totalRteSeats('block_admin', $school_miniNode->id());
        $total_applications = $this->studentDetails('block_admin', $school_miniNode->id());
        $total_applied = $this->studentDetails('block_admin', $school_miniNode->id(), 'applied');
        $total_duplicate = $this->studentDetails('block_admin', $school_miniNode->id(), 'duplicate');
        $total_incomplete = $this->studentDetails('block_admin', $school_miniNode->id(), 'incomplete');
        $total_rejected = $this->studentDetails('block_admin', $school_miniNode->id(), 'rejected');
        $total_approved = $this->studentDetails('block_admin', $school_miniNode->id(), 'approved');
        $total_allotted = $this->studentStatus('block_admin', $school_miniNode->id(), 'allotted');
        // $total_unallotted = $total_approved - $total_allotted;
        $total_admitted = $this->studentStatus('block_admin', $school_miniNode->id(), 'admitted');
        $total_not_admitted = $this->studentStatus('block_admin', $school_miniNode->id(), 'not_admitted');
        // $total_not_admitted = $total_allotted - $total_admitted;
        $total_dropout = $this->studentStatus('block_admin', $school_miniNode->id(), 'dropout');
        $total_unallotted = $total_approved - ($total_allotted + $total_admitted + $total_not_admitted + $total_dropout);
        $application_by_status = $this->getApplicationStatusValue([
          'applied'    => $total_applied,
          'duplicate'  => $total_duplicate,
          'incomplete' => $total_incomplete,
          'rejected'   => $total_rejected,
          'approved'   => $total_approved,
        ]);
        $allotment_by_status = $this->getAllotmentStatusValue([
          'allotted'   => $total_allotted,
          'unallotted' => $total_unallotted,
        ]);

        $admission_by_status = $this->getAdmissionStatusValue([
          'admitted'   => $total_admitted,
          'unadmitted' => $total_not_admitted + $total_dropout,
        ]);

        $data[] = [$serialNumber, $school_miniNode->get('field_school_name')->getString(), $total_rte_seats,
          $total_applications, $application_by_status, $allotment_by_status, $admission_by_status,
        ];
        $serialNumber++;
      }

      return $data;
    }
    // Return a markup about missing location.
    return 0;
  }

  /**
   * Function to get block admin count.
   *
   * @param string $locationId
   *   The location id to get the student details.
   *
   * @return int
   *   Count of block admin users or user location details based on role.
   */
  public function getBlocksCount(?string $locationId = NULL): int {
    if ($locationId) {
      // Get the blocks list of taxonomy term based
      // on the district id provided.
      $location_tree = $this->entityTypeManager()->getStorage('taxonomy_term')->loadTree('location', $locationId, 1, FALSE) ?? NULL;
      return $location_tree ? count($location_tree) : 0;
    }
    return 0;
  }

  /**
   * Gets the school_admins based on the user's role and location.
   *
   * @param string $locationId
   *   The location id to get the student details.
   */
  public function getSchoolList(?string $locationId = NULL) {

    $location_tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $locationId, NULL, FALSE) ?? NULL;
    $locations = [];

    if ($location_tree) {
      foreach ($location_tree as $value) {
        $locations[] = $value->tid;
      }
    }

    // Query to count active user with same location id &
    // `school_admin` user role.
    if (!empty($locations)) {
      $query = $this->entityTypeManager->getStorage('user')
        ->getQuery()
        ->condition('roles', 'school_admin')
        ->condition('status', 1)
        ->condition('field_school_details.entity:mini_node.field_location', $locations, 'IN')
        ->condition('field_school_details.entity:mini_node.field_school_verification', 'school_registration_verification_approved_by_deo')
        ->accessCheck(FALSE);

      $schools = $query->execute();

      // Return an array of all the schools under a location id.
      return $schools;
    }

    return [];
  }

  /**
   * Get the total list of RTE seats.
   *
   * @param string $current_role
   *   The current user role.
   * @param string $id
   *   The location id to get the student details for state & district.
   *   And the mini node id for block admin.
   * @param string|null $academic_year
   *   The academic year to filter the schools.
   *
   * @return int
   *   The count of total rte seats in a particular location.
   */
  public function totalRteSeats(string $current_role, ?string $id = NULL, $academic_year = NULL): int {
    // Get the language from default option config.
    $school_config = $this->configFactory->get('rte_mis_school.settings');
    $languages = $school_config->get('field_default_options.field_medium') ?? [];
    $academic_year = $academic_year ?? $this->getAcademicYear();
    if (in_array($current_role, ['state_admin', 'district_admin'])) {
      $seats = 0;
      $locationIds = [];
      $location = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $id, NULL, FALSE) ?? NULL;
      if ($location) {
        foreach ($location as $value) {
          $locationIds[] = $value->tid;
        }
      }

      // Get all the registered schools
      // with location as $district_id.
      if ($locationIds) {
        $query = $this->entityTypeManager->getStorage('mini_node')
          ->getQuery()
          ->condition('type', 'school_details')
          ->condition('field_academic_year', $academic_year)
          ->condition('field_school_verification', 'school_registration_verification_approved_by_deo')
          ->condition('field_location', $locationIds, 'IN')
          ->accessCheck(FALSE);

        $schools = $query->execute();

        foreach ($schools as $value) {
          // Get the seat information of each school
          // and add it to total seats.
          $seats += $this->eachSchoolSeatCount($languages, $value);
        }

        return $seats;
      }

    }
    elseif ($current_role == 'block_admin') {
      // Gte the seat information of the school.
      return $this->eachSchoolSeatCount($languages, $id, $academic_year);
    }
    return 0;
  }

  /**
   * Function to count the seats in each school.
   *
   * @param array $languages
   *   The languages from the config.
   * @param string $id
   *   The `id` of the school.
   * @param string|null $academic_year
   *   The academic year to filter the schools.
   *
   * @return int
   *   Return the count of seat in each school.
   */
  protected function eachSchoolSeatCount(array $languages, string $id, ?string $academic_year = NULL) {
    $totalEachSchool = 0;
    $school_details = $this->entityTypeManager->getStorage('mini_node')->load($id);
    if ($academic_year === NULL) {
      $academic_year = $this->getAcademicYear();
    }
    if ($academic_year && $school_details->get('field_academic_year')->getString() !== $academic_year) {
      return 0;
    }
    // Check for both single and dual entry.
    foreach ($school_details->get('field_entry_class')->referencedEntities() as $entry_class) {
      foreach ($languages as $key => $language) {
        $rte_seats[$entry_class->get('field_entry_class')->getString()]['rte_seat'][$key] = $entry_class->get('field_rte_student_for_' . $key)->getString();
        $totalEachSchool += $rte_seats[$entry_class->get('field_entry_class')->getString()]['rte_seat'][$key];
      }
    }
    return $totalEachSchool;
  }

  /**
   * Get the list of applied students.
   *
   * @param string $current_role
   *   The current user role.
   * @param string $id
   *   The location id to get the student details for state & district.
   *   And the mini node id for block admin.
   * @param string $status
   *   The current status of the student application.
   *
   * @return int
   *   The count of total rte seats in a particular district.
   */
  public function studentDetails(string $current_role, ?string $id = NULL, $status = NULL): int {
    if (in_array($current_role, ['state_admin', 'district_admin'])) {
      // Load all the locations under current location id.
      $location = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $id, NULL, FALSE);
      $locationIds = [];
      if ($location) {
        foreach ($location as $value) {
          $locationIds[] = $value->tid;
        }
      }

      // Get all the registered schools
      // with location as $locationIds.
      if ($locationIds) {
        $query = $this->entityTypeManager->getStorage('mini_node')
          ->getQuery()
          ->condition('type', 'student_details')
          ->condition('field_location', $locationIds, 'IN')
          ->accessCheck(FALSE);

        $academic_year = $this->getAcademicYear();
        if ($academic_year) {
          $query->condition('field_academic_year', $academic_year);
        }

        if ($status == 'applied') {
          $query->condition('field_student_verification', 'student_workflow_submitted');
        }
        elseif ($status == 'duplicate') {
          $query->condition('field_student_verification', 'student_workflow_duplicate');
        }
        elseif ($status == 'incomplete') {
          $query->condition('field_student_verification', 'student_workflow_incomplete');
        }
        elseif ($status == 'rejected') {
          $query->condition('field_student_verification', 'student_workflow_rejected');
        }
        elseif ($status == 'approved') {
          $query->condition('field_student_verification', 'student_workflow_approved');
        }

        $students = $query->execute();
        $total_students = count($students);

        return $total_students;
      }
    }
    elseif ($current_role == 'block_admin') {
      // List down the students which have a particular school with a id.
      $query = $this->entityTypeManager->getStorage('mini_node')
        ->getQuery()
        ->condition('type', 'student_details')
        ->accessCheck(FALSE);

      $academic_year = $this->getAcademicYear();
      if ($academic_year) {
        $query->condition('field_academic_year', $academic_year);
      }

      if ($status == 'applied') {
        $query->condition('field_student_verification', 'student_workflow_submitted');
      }
      elseif ($status == 'duplicate') {
        $query->condition('field_student_verification', 'student_workflow_duplicate');
      }
      elseif ($status == 'incomplete') {
        $query->condition('field_student_verification', 'student_workflow_incomplete');
      }
      elseif ($status == 'rejected') {
        $query->condition('field_student_verification', 'student_workflow_rejected');
      }
      elseif ($status == 'approved') {
        $query->condition('field_student_verification', 'student_workflow_approved');
      }
      $students = $query->execute();
      // Total student preferrence for a single school.
      $count = [];
      foreach ($students as $student) {
        $student = $this->entityTypeManager->getStorage('mini_node')->load($student);
        // Each School preference entity.
        $preferences = $student->get('field_school_preferences')->referencedEntities();
        $preferenceSingleStudent = [];
        foreach ($preferences as $value) {
          $referencedSchool = $value->get('field_school_id')->referencedEntities();
          $preferenceSingleStudent[] = reset($referencedSchool)->id();
        }
        // If the student has a preference of current school
        // Add and return it via count.
        if (in_array($id, array_unique($preferenceSingleStudent))) {
          $count[] = $student->id();
        }
      }
      return count($count);
    }
    return 0;
  }

  /**
   * Function to check student allotment status.
   *
   * @param string $current_role
   *   The current user role.
   * @param string $id
   *   The location id to get the student details for state & district.
   *   And the mini node id for block admin.
   * @param string $status
   *   The current status of the allocation process.
   *
   * @return int
   *   The count of total student with requested status.
   */
  public function studentStatus($current_role, ?string $id = NULL, $status = NULL): int {
    if (in_array($current_role, ['state_admin', 'district_admin'])) {
      // For state, district admins get the location id via `$id`.
      $student_count = 0;
      $school_list = [];
      // Get the list of school for the user role and user location.
      $school_admin = $this->getSchoolList($id);
      // Get the school mini node list from the school admin user id.
      if (!empty($school_admin)) {
        foreach ($school_admin as $value) {
          $school_miniNode = $this->entityTypeManager->getStorage('user')->load($value);
          if ($school_miniNode instanceof UserInterface) {
            $school_id = $school_miniNode->get('field_school_details')->getString() ?? NULL;
            if ($school_id !== NULL) {
              $school_list[] = $school_id;
            }
          }
        }
      }

      // Query to count the number of student with different status values.
      if ($school_list) {
        $query = $this->entityTypeManager->getStorage('mini_node')
          ->getQuery()
          ->condition('type', 'allocation')
          ->condition('field_school', $school_list, 'IN')
          ->accessCheck(FALSE);

        $academic_year = $this->getAcademicYear();
        if ($academic_year) {
          $query->condition('field_academic_year_allocation', $academic_year);
        }

        if ($status == 'admitted') {
          $query->condition('field_student_allocation_status', 'student_admission_workflow_admitted');
        }
        elseif ($status == 'not_admitted') {
          $query->condition('field_student_allocation_status', 'student_admission_workflow_not_admitted');
        }
        elseif ($status == 'dropout') {
          $query->condition('field_student_allocation_status', 'student_admission_workflow_dropout');
        }
        elseif ($status == 'allotted') {
          $query->condition('field_student_allocation_status', 'student_admission_workflow_allotted');
        }

        $student_count = $query->execute();
        return count($student_count);
      }

    }
    elseif ($current_role == 'block_admin') {
      // Get the school miniNode `$id` and check for entries in
      // allocation miniNode with requested status.
      $query = $this->entityTypeManager->getStorage('mini_node')
        ->getQuery()
        ->condition('type', 'allocation')
        ->condition('field_school', $id)
        ->accessCheck(FALSE);

      $academic_year = $this->getAcademicYear();
      if ($academic_year) {
        $query->condition('field_academic_year_allocation', $academic_year);
      }

      if ($status == 'admitted') {
        $query->condition('field_student_allocation_status', 'student_admission_workflow_admitted');
      }
      elseif ($status == 'not_admitted') {
        $query->condition('field_student_allocation_status', 'student_admission_workflow_not_admitted');
      }
      elseif ($status == 'dropout') {
        $query->condition('field_student_allocation_status', 'student_admission_workflow_dropout');
      }
      elseif ($status == 'allotted') {
        $query->condition('field_student_allocation_status', 'student_admission_workflow_allotted');
      }

      $student_count = $query->execute();
      return count($student_count);
    }
    return 0;
  }

}
