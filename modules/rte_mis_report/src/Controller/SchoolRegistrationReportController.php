<?php

declare(strict_types=1);

namespace Drupal\rte_mis_report\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\rte_mis_report\Services\RteReportHelper;
use Drupal\rte_mis_report\Form\SchoolRegistrationReportFilterForm;
use Drupal\user\UserInterface;
use PhpParser\Node\Stmt\Else_;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides role based details for student.
 */
final class SchoolRegistrationReportController extends ControllerBase {

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
   * The rte mis helper service.
   *
   * @var \Drupal\rte_mis_report\Services\RteReportHelper
   */
  protected $rteReportHelper;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs the controller instance.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    ConfigFactoryInterface $config_factory,
    RteReportHelper $rte_report_helper,
    Connection $database,
    RouteMatchInterface $route_match,
    RequestStack $requestStack,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->configFactory = $config_factory;
    $this->rteReportHelper = $rte_report_helper;
    $this->database = $database;
    $this->routeMatch = $route_match;
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('config.factory'),
      $container->get('rte_mis_report.report_helper'),
      $container->get('database'),
      $container->get('current_route_match'),
      $container->get('request_stack'),
    );
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

    if (array_intersect(['app_admin', 'state_admin'], $currentUserRole)) {
      // If Id is not passed then user is allowed.
      if (!$id) {
        return AccessResult::allowed();
      }

      $locationTree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', 0, 2, FALSE);
      foreach ($locationTree as $value) {
        if ($value->tid == $id) {
          // If the location id is below block return access denied.
          if ($value->depth >= 1) {
            return AccessResult::forbidden()->setCacheMaxAge(0);
          }
          return AccessResult::allowed()->setCacheMaxAge(0);
        }
      }
    }
    elseif (in_array('district_admin', $currentUserRole) && $currentUserLocation) {
      // Check if the $id is defined and doesn't match the user location id.
      // Then return forbidden, as district will see only their details.
      if ($id && $currentUserLocation != $id) {
        return AccessResult::forbidden()->setCacheMaxAge(0);
      }
      // If the Id for district is not defined in the url.
      // It will redirect to its id.
    }
    elseif (in_array('block_admin', $currentUserRole)) {
      // For block admin result forbidden.
      // Block admin data will be found at `/schools-lists`.
      return AccessResult::forbidden();
    }

    return AccessResult::allowed()->setCacheMaxAge(0);
  }

  /**
   * Get the filter form and request parameters.
   */
  protected function getFilters():array {
    $form = new SchoolRegistrationReportFilterForm();
    $years = $form->getAcademicYearOptions();
    $default_year = !empty($years) ? reset($years) : NULL;
    return [
      'admission_cycle' => $this->requestStack->getCurrentRequest()->query->get('admission_cycle', $default_year),
      'pending_approvals' => $this->requestStack->getCurrentRequest()->query->get('pending_approvals', 'all'),
      'mapping_status' => $this->requestStack->getCurrentRequest()->query->get('mapping_status', 'mapped'),
    ];
  }

  /**
   * Displays the role based details.
   *
   * @return array
   *   A render array.
   */
  public function build(?string $id = NULL) {

    // If user is district admin and no ID passed, redirect to their location.
    $currentUserId = $this->currentUser->id();
    /** @var \Drupal\user\Entity\User $currentUser */
    $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);

    if ($currentUser instanceof UserInterface) {
      $roles = $currentUser->getRoles(TRUE);

      if (in_array('district_admin', $roles)) {
        $locationId = $currentUser->get('field_location_details')->getString() ?? NULL;

        // Redirect before table rendering.
        if (!$id && $locationId) {
          $url = Url::fromRoute(
            'rte_mis_report.controller.school_registration_report',
            ['id' => $locationId],
            ['query' => $this->requestStack->getCurrentRequest()->query->all()]
          )->toString();

          return new RedirectResponse($url);
        }
      }
    }

    if ((is_numeric($id) && $this->rteReportHelper->checkLocation($id)) || $id == NULL) {
      $header = $this->getHeaders($id);
      $rows = $this->getData($id);

      $term = NULL;
      if ($id) {
        $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($id);
      }
      if (!$id) {
        $page_title = "District Wise School Report";
      }
      elseif ($term && !$term->parent->target_id) {
        $page_title = "Block Wise School Report – " . $term->label();
      }
      else {
        $page_title = "Schools Wise Report – " . $term->label();
      }

      $build = [];
      $build = [
        '#type' => 'container',
        '#attributes' => ['class' => ['school-report-wrapper-container']],
        'heading' => [
          '#markup' => '<h2 class="student-report-title">' . $page_title . '</h2>',
        ],
      ];

      $build['filter_form'] = $this->formBuilder()->getForm(
        SchoolRegistrationReportFilterForm::class,
        $id
      );

      // Create a table with data.
      $build['table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#prefix' => '<div class="school-report-wrapper">',
        '#suffix' => '</div>',
        '#attributes' => ['class' => ['school-reports']],
        '#empty' => $this->t('No data to display.'),
        '#cache' => [
          'contexts' => ['user'],
          'tags' => [
            'user_list',
            'taxonomy_term_list',
            'mini_node_list',
          ],
        ],
      ];

      $build['pager'] = [
        '#type' => 'pager',
      ];

      $excel_url = url::fromRoute(
        'rte_mis_report.export_district_block_excel',
        $id ? ['id' => $id] : [],
        ['query' => $this->requestStack->getCurrentRequest()->query->all()]
      )->toString();
      $pdf_url   = Url::fromRoute(
        'rte_mis_report.export_district_block_pdf',
        $id ? ['id' => $id] : [],
        ['query' => $this->requestStack->getCurrentRequest()->query->all()]
      )->toString();

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
      $build['#attached']['library'][] = 'rte_mis_gin/rte_mis_school-report';

      return $build;

    }
    else {
      throw new NotFoundHttpException();
    }
  }

  /**
   * Get Pending Approvals label.
   */
  public function getPendingApprovalsLabel() {
    $filters = $this->getFilters();

    $mapping_status = $filters['pending_approvals'] ?? 'all';

    if ($mapping_status == 'all') {
      return [
        $this->t('Pending Approvals (BEO)'),
        $this->t('Pending Approvals (DEO)'),
      ];
    }
    if ($mapping_status == 'block_officer') {
      return [$this->t('Pending Approvals (BEO)')];
    }
    if ($mapping_status == 'district_officer') {
      return [$this->t('Pending Approvals (DEO)')];
    }
    return [$this->t('Pending Approvals')];
  }

  /**
   * Get Pending Approvals value.
   */
  public function getPendingApprovalsValue(array $counts) {
    $filters = $this->getFilters();
    $status = $filters['pending_approvals'] ?? 'all';

    return match($status) {
      'all' => $counts['all'],
      'block_officer' => $counts['block_officer'],
      'district_officer' => $counts['district_officer'],
    };
  }

  /**
   * Get Mapping Status label.
   */
  public function getMappingStatusLabel() {
    $filters = $this->getFilters();
    $mapping_status = $filters['mapping_status'] ?? 'mapped';
    if ($mapping_status == 'mapped') {
      return $this->t('Mapping Status (Mapped)');
    }
    if ($mapping_status == 'unmapped') {
      return $this->t('Mapping Status (Unmapped)');
    }
    return $this->t('Mapping Status');
  }

  /**
   * Get Mapping Status value.
   */
  public function getMappingStatusValue(array $counts) {
    $filters = $this->getFilters();
    $mapping_status = $filters['mapping_status'] ?? 'mapped';

    return match($mapping_status) {
      'mapped' => $counts['mapping_completed'],
      'unmapped' => $counts['mapping_pending'],
    };
  }

  /**
   * Function to get the headers.
   */
  protected function getHeaders($id = NULL) {
    // Return header based on the user role.
    $header = [
      $this->t('Total Schools'), $this->t('Registered Schools'), ...((array) $this->getPendingApprovalsLabel()), $this->t('Approved'), $this->getMappingStatusLabel(),
    ];

    $currentUserRole = $this->currentUser->getRoles(TRUE);

    if (array_intersect(['app_admin', 'state_admin'], $currentUserRole) && !$id) {
      $header = array_merge([
        $this->t('No.'), $this->t('District Name'), $this->t('Blocks'),
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
            $this->t('No.'), $this->t('Blocks'),
          ], $header);
        }
      }

    }

    return (array) $header;
  }

  /**
   * Function to get the row data.
   */
  protected function getData($id = NULL) {
    $content = [];
    $currentUserRole = $this->currentUser->getRoles(TRUE);
    $filters = $this->getFilters();
    $academic_year = $filters['admission_cycle'] ?? NULL;
    $query = $this->requestStack->getCurrentRequest()->query->all();

    if (array_intersect(['app_admin', 'state_admin'], $currentUserRole) && !$id) {
      $content = $this->getStateAdminContent($id, $academic_year);
      if (!$query) {
        $content = $this->getStateAdminContent($id);
      }
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
          $content = $this->getDistrictAdminContent($id, $academic_year);
          if (!$query) {
            $content = $this->getDistrictAdminContent($id);
          }
        }
      }

    }

    return $content;
  }

  /**
   * Get content for state admin.
   */
  protected function getStateAdminContent($id = NULL, $academic_year = NULL) {
    // Implemented data fetching logic.
    // Serial Number.
    $serialNumber = 1;
    $parent_id = '0';
    $districts = $this->rteReportHelper->locationList($parent_id);
    $data = [];

    if ($districts) {
      foreach ($districts as $district) {
        $blocks = $this->rteReportHelper->getBlocksCount($district->tid);
        $total_schools = $this->rteReportHelper->getSchoolListCount($district->tid);
        $registered_schools = count($this->rteReportHelper->getRegisteredSchoolList($district->tid, NULL, $academic_year));
        $pending_beo_approval = $this->rteReportHelper->getSchoolStatus($district->tid, 'submitted', $academic_year);
        $pending_deo_approval = $this->rteReportHelper->getSchoolStatus($district->tid, 'approved_by_beo', $academic_year);
        $approved_school = count($this->rteReportHelper->getRegisteredSchoolList($district->tid, 'approved', $academic_year));
        $mapping_completed = count($this->rteReportHelper->mappingStatus($district->tid, TRUE, $academic_year));
        $mapping_pending = count($this->rteReportHelper->mappingStatus($district->tid));
        $filters = $this->getFilters();
        $pending_approval_status = $filters['pending_approvals'] ?? 'all';
        $mapping_by_status = $filters['mapping_status'] ?? 'mapped';

        $status_types = [];
        $status_types['registered'] = $registered_schools;
        if ($pending_approval_status === 'block_officer') {
          $status_types['pending_beo_approval'] = $pending_beo_approval;
        }
        elseif ($pending_approval_status === 'district_officer') {
          $status_types['pending_deo_approval'] = $pending_deo_approval;
        }
        else {
          $status_types['pending_beo_approval'] = $pending_beo_approval;
          $status_types['pending_deo_approval'] = $pending_deo_approval;
        }
        $status_types['approved'] = $approved_school;
        if ($mapping_by_status == 'mapped') {
          $status_types['mapping_completed'] = $mapping_completed;
        }
        else {
          $status_types['mapping_pending'] = $mapping_pending;
        }
        // Get current query parameters (filters).
        $query = $this->requestStack->getCurrentRequest()->query->all();

        // Create URL with filters preserved.
        $url = Url::fromUri(
          "internal:/school-registration-report/{$district->tid}",
          ['query' => $query]
        );

        // Render link.
        $block_list = Link::fromTextAndUrl((string) $blocks, $url)->toRenderable();

        // Create link render array.
        $url = Url::fromUri(
          "internal:/schools-lists/{$district->tid}",
          ['query' => $query]
        );
        // Render link for total schools on the portal.
        $total_school_link = Link::fromTextAndUrl((string) $total_schools, $url)->toRenderable();

        // Initialize an array to store the links.
        $status_links = [];
        foreach ($status_types as $status => $status_value) {
          if ($status_value != 0) {
            $url = Url::fromUri("internal:/schools-lists/{$district->tid}", [
              'query' => array_merge(
                ['status' => $status],
                $query
              ),
            ]);
            $link = Link::fromTextAndUrl((string) $status_value, $url)->toRenderable();
            $status_links[] = ['data' => $link];
          }
          else {
            // Add plain text if the value is zero.
            $status_links[] = ['data' => $status_value];
          }
        }
        $data[] = [$serialNumber, $district->name, ['data' => $block_list], ['data' => $total_school_link], ...$status_links,
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
  protected function getDistrictAdminContent($id = NULL, $academic_year = NULL) {
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
      $blocks = $this->rteReportHelper->locationList($locationId);
      if ($blocks) {
        foreach ($blocks as $block) {
          $total_schools = $this->rteReportHelper->getSchoolListCount($block->tid);
          $registered_schools = count($this->rteReportHelper->getRegisteredSchoolList($block->tid, NULL, $academic_year));
          $pending_beo_approval = $this->rteReportHelper->getSchoolStatus($block->tid, 'submitted', $academic_year);
          $pending_deo_approval = $this->rteReportHelper->getSchoolStatus($block->tid, 'approved_by_beo', $academic_year);
          $approved_school = count($this->rteReportHelper->getRegisteredSchoolList($block->tid, 'approved', $academic_year));
          $mapping_completed = count($this->rteReportHelper->mappingStatus($block->tid, TRUE, $academic_year));
          $mapping_pending = count($this->rteReportHelper->mappingStatus($block->tid, FALSE, $academic_year));
          $filters = $this->getFilters();
          $pending_approval_status = $filters['pending_approvals'] ?? 'all';
          $mapping_by_status = $filters['mapping_status'] ?? 'mapped';

          $status_types = [];
          $status_types['registered'] = $registered_schools;
          if ($pending_approval_status === 'block_officer') {
            $status_types['pending_beo_approval'] = $pending_beo_approval;
          }
          elseif ($pending_approval_status === 'district_officer') {
            $status_types['pending_deo_approval'] = $pending_deo_approval;
          }
          else {
            $status_types['pending_beo_approval'] = $pending_beo_approval;
            $status_types['pending_deo_approval'] = $pending_deo_approval;
          }
          $status_types['approved'] = $approved_school;
          if ($mapping_by_status == 'mapped') {
            $status_types['mapping_completed'] = $mapping_completed;
          }
          else {
            $status_types['mapping_pending'] = $mapping_pending;
          }

          $query = $this->requestStack->getCurrentRequest()->query->all();

          $url = Url::fromUri(
            "internal:/schools-lists/{$block->tid}",
            ['query' => $query]
          );

          // Render link for total schools on the portal.
          $total_school_link = Link::fromTextAndUrl((string) $total_schools, $url)->toRenderable();
          // Initialize an array to store the links.
          $status_links = [];
          foreach ($status_types as $status => $status_value) {
            if ($status_value != 0) {
              $url = Url::fromUri("internal:/schools-lists/{$block->tid}", [
                'query' => array_merge(
                  ['status' => $status],
                  $query
                ),
              ]);
              $link = Link::fromTextAndUrl((string) $status_value, $url)->toRenderable();
              $status_links[] = ['data' => $link];
            }
            else {
              // Add plain text if the value is zero.
              $status_links[] = ['data' => (string) $status_value];
            }
          }
          // Add the generated links to the data array.
          $data[] = [
            $serialNumber,
            $block->name,
            ['data' => $total_school_link], ...$status_links,
          ];
          $serialNumber++;
        }
        return $data;
      }

    }
    return 0;

  }

  /**
   * Function to download data in excel.
   *
   * @param string $id
   *   Location Id.
   */
  public function exportToExcel(?string $id = NULL) {
    $header = $this->getHeaders($id);
    $rows = $this->getData($id);
    $max_columns = count($header);
    $academic_year = $this->getFilters()['admission_cycle'];
    $academic_year = str_replace('_', '–', $academic_year);

    $filename = 'school_registration_report';
    $context = [
      'results' => [],
      'finished' => TRUE,
    ];

    $title = 'School Registration Report (' . $academic_year . ')';

    return $this->rteReportHelper->excelDownload($title, $header, $rows, $filename, $max_columns, $context);
  }

  /**
   * Function to download data in PDF.
   *
   * @param string|null $id
   *   Location Id.
   */
  public function exportToPdf(?string $id = NULL) {
    // Get headers and rows.
    $header = $this->getHeaders($id);
    $rows = $this->getData($id);
    $academic_year = $this->getFilters()['admission_cycle'];
    $academic_year = str_replace('_', '–', $academic_year);

    // Ensure rows are valid.
    if (empty($rows) || !is_array($rows)) {
      throw new NotFoundHttpException('No data available for PDF export.');
    }

    // Normalize row values (final fix – no empty cells).
    foreach ($rows as &$row) {
      foreach ($row as &$cell) {

        // Case 1: Numeric values (int/float)
        if (is_int($cell) || is_float($cell)) {
          // Keep as-is.
          $cell = (string) $cell;
          continue;
        }

        // Case 2: String values.
        if (is_string($cell)) {
          $cell = trim($cell);
          if ($cell === '') {
            $cell = '0';
          }
          continue;
        }

        // Case 3: Render array values.
        if (is_array($cell) && isset($cell['data'])) {
          if (isset($cell['data']['#markup'])) {
            $cell = trim((string) strip_tags($cell['data']['#markup']));
          }
          elseif (isset($cell['data']['#title'])) {
            $cell = trim((string) $cell['data']['#title']);
          }
          else {
            $cell = '0';
          }
          continue;
        }

        // Fallback.
        $cell = '0';
      }
    }

    // File name.
    $filename = 'school_registration_report';
    $title = 'School Registration Report (' . $academic_year . ')';

    return $this->rteReportHelper->pdfDownload(
      $title,
      $header,
      $rows,
      $filename
    );
  }

}
