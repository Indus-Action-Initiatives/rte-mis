<?php

declare(strict_types=1);

namespace Drupal\rte_mis_report\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\rte_mis_report\Services\RteReportHelper;
use Drupal\rte_mis_report\Form\SchoolRegistrationReportFilterForm;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides school registration report block wise.
 */
final class SchoolRegistrationReportBlockController extends ControllerBase {

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
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs the controller instance.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    ConfigFactoryInterface $config_factory,
    RteReportHelper $rte_report_helper,
    RequestStack $request_stack,
    RouteMatchInterface $route_match,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->configFactory = $config_factory;
    $this->rteReportHelper = $rte_report_helper;
    $this->requestStack = $request_stack;
    $this->routeMatch = $route_match;
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
      $container->get('request_stack'),
      $container->get('current_route_match'),
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
   * Get Pending Approvals label.
   */
  public function getPendingApprovalsLabel() {
    $filters = $this->getFilters();

    $mapping_status = $filters['pending_approvals'] ?? 'all';

    if ($mapping_status == 'all') {
      return [
        (string) $this->t('Pending Approvals (BEO)'),
        (string) $this->t('Pending Approvals (DEO)'),
      ];
    }
    if ($mapping_status == 'block_officer') {
      return [(string) $this->t('Pending Approvals (BEO)')];
    }
    if ($mapping_status == 'district_officer') {
      return [(string) $this->t('Pending Approvals (DEO)')];
    }
    return $this->t('Pending Approvals');
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
   * Displays the role based details.
   *
   * @param string $id
   *   Location Id.
   *
   * @return array
   *   A render array.
   */
  public function build(?string $id = NULL) {

    if ((is_numeric($id) && $this->rteReportHelper->checkLocation($id)) || $id == NULL) {
      $currentUserId = $this->currentUser->id();
      $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);

      if ($currentUser instanceof UserInterface) {
        if (array_intersect(['district_admin', 'block_admin'], $currentUser->getRoles(TRUE))) {
          // Get location ID from user field.
          $locationId = $currentUser->get('field_location_details')->getString() ?? NULL;
          if (!$id && $locationId) {
            $url = Url::fromRoute('rte_mis_report.controller.school_registration_report_school_details', ['id' => $locationId])->toString();

            // Return a redirect response.
            return new RedirectResponse($url);
          }
        }
      }

      $header = $this->getHeaders();
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
        'rte_mis_report.export_schools_excel',
        $id ? ['id' => $id] : [],
        ['query' => $this->requestStack->getCurrentRequest()->query->all()]
      )->toString();
      $pdf_url   = Url::fromRoute(
        'rte_mis_report.export_schools_pdf',
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
   * Function to get the headers.
   *
   * @return array
   *   An array of headers.
   */
  protected function getHeaders() {
    // For block admin.
    $header = [
      $this->t('No.'), $this->t('School Udise Code'), $this->t('Schools'), $this->t('Registered'), ...((array) $this->getPendingApprovalsLabel()), $this->t('Approved'), $this->getMappingStatusLabel(),
    ];

    return (array) $header;
  }

  /**
   * Function to get the row data.
   *
   * @param string $id
   *   Location Id.
   *
   * @return array
   *   An array of rows.
   */
  protected function getData($id = NULL) {
    $academic_year = $this->getFilters()['admission_cycle'];
    $content = [];
    $query = $this->requestStack->getCurrentRequest()->query->all();

    $form_id = $query['form_id'] ?? NULL;
    $content = $form_id ?
      $this->getBlockAdminContent($id, $academic_year) :
      $this->getBlockAdminContent($id);

    return $content;
  }

  /**
   * Get content for block admin.
   *
   * @param string $id
   *   Location Id.
   * @param mixed $academic_year
   *   Academic year.
   */
  protected function getBlockAdminContent($id = NULL, $academic_year = NULL) {
    // Implemented data fetching logic.
    // Serial Number.
    $serialNumber = 1;
    // Retrieve the 'status' query parameter, defaulting to NULL if not set.
    $status = $this->requestStack->getCurrentRequest()->query->get('status', NULL);

    if ($id == NULL) {
      // Get current user id.
      $currentUserId = $this->currentUser->id();
      $currentUserRole = $this->currentUser->getRoles(TRUE);
      if (array_intersect(['district_admin', 'block_admin'], $currentUserRole)) {
        /** @var \Drupal\user\Entity\User */
        $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);

        if ($currentUser instanceof UserInterface) {
          // Get location ID from user field.
          $locationId = $currentUser->get('field_location_details')->getString() ?? NULL;
        }
      }
      else {
        $locationId = '0';
      }
    }
    else {
      $locationId = $id;
    }

    if ($locationId == '0' || $locationId) {
      $data = [];
      // If there is no status in the url,
      // Follow the normal process of getting schools.

      if (!$status) {
        $schools = $this->rteReportHelper->getSchoolList($locationId);
      }
      else {
        $valid_statuses = [
          'registered',
          'pending_beo_approval',
          'pending_deo_approval',
          'approved',
          'mapping_completed',
          'mapping_pending',
        ];

        // If there is a valid status return response accordingly.
        if (in_array($status, $valid_statuses)) {
          $schools = $this->rteReportHelper->getRegisteredSchoolStatus($locationId, $status);
        }
        // If the provided status is not valid then return 0.
        else {
          return 0;
        }

      }

      if (empty($schools)) {
        return 0;
      }
      foreach ($schools as $school) {
        if (!$status) {
          // If there is no registration found.
          // Don't check further and return 'N/A'
          // for all other entries.
          $mini_node_id = $this->rteReportHelper->checkRegistration($school->tid, $school->school_name, $academic_year);
          if (!$mini_node_id) {
            $registered = 'No';
            $pending_beo_approval = 'N/A';
            $pending_deo_approval = 'N/A';
            $approved = 'N/A';
            $mapping_completed = 'N/A';
            $mapping_pending = 'N/A';
          }
          else {
            $registered = 'Yes';
            $schoolStatus = $this->rteReportHelper->checkSchoolStatus($mini_node_id);
            $pending_beo_approval = $schoolStatus['pending_beo_approval'];
            $pending_deo_approval = $schoolStatus['pending_deo_approval'];
            $approved = $schoolStatus['approved'];
            $mapping_completed = $schoolStatus['mapping_completed'];
            $mapping_pending = $schoolStatus['mapping_pending'];
          }
        }
        else {
          $registered = 'Yes';
          $schoolStatus = $this->rteReportHelper->checkSchoolStatus($school->id);
          $pending_beo_approval = $schoolStatus['pending_beo_approval'];
          $pending_deo_approval = $schoolStatus['pending_deo_approval'];
          $approved = $schoolStatus['approved'];
          $mapping_completed = $schoolStatus['mapping_completed'];
          $mapping_pending = $schoolStatus['mapping_pending'];
        }

        $filters = $this->getFilters();
        $pending_approval_status = $filters['pending_approvals'] ?? 'all';
        if ($pending_approval_status == 'all') {
          $pending_by_status = [$pending_beo_approval, $pending_deo_approval];
        }
        if ($pending_approval_status == 'block_officer') {
          $pending_by_status = [$pending_beo_approval];
        }
        if ($pending_approval_status == 'district_officer') {
          $pending_by_status = [$pending_deo_approval];
        }

        $mapping_by_status = $this->getMappingStatusValue([
          'mapping_completed' => $mapping_completed,
          'mapping_pending' => $mapping_pending,
        ]);

        $data[] = [$serialNumber, $school->name, $school->school_name, $registered, ...$pending_by_status, $approved, $mapping_by_status,
        ];
        $serialNumber++;
      }

      return $data;
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
    // Get the headers.
    $header = $this->getHeaders();
    // Get the row datas.
    $rows = $this->getData($id);
    // Count the maximum number of columns to be utilized.
    $max_columns = count($header);

    $academic_year = $this->getFilters()['admission_cycle'];
    $academic_year = str_replace('_', '–', $academic_year);

    // Name of the file to be downloaded.
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
