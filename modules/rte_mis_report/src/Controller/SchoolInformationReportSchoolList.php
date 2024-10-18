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
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides school list for school information report.
 */
final class SchoolInformationReportSchoolList extends ControllerBase {
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
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The rte mis helper service.
   *
   * @var \Drupal\rte_mis_report\Services\RteReportHelper
   */
  protected $rteReportHelper;
  /**
   * The list of available mediums.
   *
   * @var array
   */
  protected $mediums;

  /**
   * The list of available education levels.
   *
   * @var array
   */
  protected $educationLevels;

  /**
   * The list of available board types.
   *
   * @var array
   */
  protected $boards;

  /**
   * Constructs the controller instance.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    ConfigFactoryInterface $config_factory,
    RteReportHelper $rte_report_helper,
    RequestStack $request_stack,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->configFactory = $config_factory;
    $this->rteReportHelper = $rte_report_helper;
    $this->requestStack = $request_stack;

    // Store the values of mediums, education levels and boards.
    $school_config = $config_factory->get('rte_mis_school.settings');
    $this->mediums = $school_config->get('field_default_options.field_medium') ?? [];
    $this->educationLevels = $school_config->get('field_default_options.field_education_level') ?? [];
    $this->boards = $school_config->get('field_default_options.field_board_type') ?? [];
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
   * Displays the role based details.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $id
   *   Location Id.
   *
   * @return array
   *   A render array.
   */
  public function build(Request $request, ?string $id = NULL) {
    if ((is_numeric($id) && $this->rteReportHelper->checkLocation($id)) || $id == NULL) {
      $currentUserId = $this->currentUser->id();
      $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);

      if ($currentUser instanceof UserInterface) {
        if (array_intersect(['district_admin', 'block_admin'], $currentUser->getRoles(TRUE))) {
          // Get location ID from user field.
          $locationId = $currentUser->get('field_location_details')->getString() ?? NULL;
          if (!$id && $locationId) {
            $url = Url::fromRoute('rte_mis_report.school_information_report_school_list', ['id' => $locationId])->toString();

            // Return a redirect response.
            return new RedirectResponse($url);
          }
        }
      }
      $header = $this->getHeaders();
      // Get all query params.
      $query_params = $request->query->all();
      $rows = $this->getData($id, $query_params);
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
      // Build pagination links.
      $build['pager'] = [
        '#type' => 'pager',
      ];

      if ($rows) {
        $query_params = $this->requestStack->getCurrentRequest()->query->all();
        // Add the Export to Excel button.
        $build['export_button'] = [
          '#type' => 'link',
          '#title' => $this->t('Export to Excel'),
          '#attributes' => ['class' => ['export-data-cta']],
        ];

        if ($id) {
          // If the ID is present, add it to the URL parameters.
          $url = Url::fromRoute('rte_mis_report.export_school_list_report', ['id' => $id]);
          // If the 'status' query parameter exists, add it to the URL options.
          if (!empty($query_params)) {
            $url->setOption('query', $query_params);
          }
        }
        else {
          // If no ID, generate the URL without passing 'id' parameter.
          $url = Url::fromRoute('rte_mis_report.export_school_list_report');
        }
        $build['export_button']['#url'] = $url;
      }

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
    // Table hearder.
    $header = [
      $this->t('No.'),
      $this->t('Udise Code'),
      $this->t('Schools'),
      $this->t('Total Seats'),
      $this->t('Total RTE seats'),
    ];

    // Dynamically add mediums columns in header.
    foreach ($this->mediums as $medium) {
      $header[] = "{$medium} (Medium)";
    }
    // Dynamically add boards columns in header.
    foreach ($this->boards as $board) {
      $header[] = "{$board} (Board)";
    }
    // Dynamically add education levels columns in header.
    foreach ($this->educationLevels as $education_level) {
      $header[] = "{$education_level} (Education Level)";
    }

    // Add reimbursement columns in header.
    $header = array_merge($header, [
      $this->t('Claims'),
      $this->t('Reimbursed'),
      $this->t('Pending'),
    ]);

    return (array) $header;
  }

  /**
   * Function to get the row data.
   *
   * @param string $id
   *   Location Id.
   * @param array $query_params
   *   Request query parameters.
   *
   * @return array
   *   An array of rows.
   */
  protected function getData($id = NULL, $query_params = []) {
    $content = [];
    // Return the data for block admin.
    $content = $this->getBlockAdminContent($id, $query_params);

    return $content;
  }

  /**
   * Get content for block admin.
   *
   * @param string $id
   *   Location Id.
   * @param array $query_params
   *   Request query parameters.
   *
   * @return array
   *   An array of rows.
   */
  protected function getBlockAdminContent($id = NULL, $query_params = []) {
    // Implemented data fetching logic.
    // Serial Number.
    $serialNumber = 1;

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
      // Filter out the schools for the selected block location and
      // query parameters.
      $schools = $this->rteReportHelper->filterSchools($locationId, $query_params);
      // Loop over the schools list and fetch the school data.
      foreach ($schools as $school) {
        $row_index = $serialNumber - 1;
        $school_data = $this->rteReportHelper->prepareSchoolListData($school);
        $claims_count = count($this->rteReportHelper->getReimbursementClaims([], NULL, $school));
        $reimbursed_claims_count = count($this->rteReportHelper->getReimbursementClaims([], 'reimbursement_claim_workflow_payment_completed', $school));
        $pending_claims_count = count($this->rteReportHelper->getReimbursementClaims([], 'reimbursement_claim_workflow_payment_pending', $school));
        $data[$row_index] = [
          $serialNumber,
          $school_data['udise_code'],
          $school_data['name'],
          $school_data['seats'],
          $school_data['rte_seats'],
        ];

        // Add medium value.
        foreach ($this->mediums as $medium_key => $medium) {
          $data[$row_index][] = isset($school_data['mediums'][$medium_key]) && $school_data['mediums'][$medium_key]
            ? 'Yes'
            : 'No';
        }

        // Add board value.
        foreach ($this->boards as $board => $board_name) {
          $data[$row_index][] = $school_data['board'] == $board
            ? 'Yes'
            : 'No';
        }

        // Add education levels value.
        foreach ($this->educationLevels as $education_level => $education_level_name) {
          $data[$row_index][] = isset($school_data['educational_levels'][$education_level])
            ? 'Yes'
            : 'No';
        }

        // Add reimbursement claims count.
        $data[$row_index][] = $claims_count;
        $data[$row_index][] = $reimbursed_claims_count;
        $data[$row_index][] = $pending_claims_count;

        $serialNumber++;
      }

      return $data;
    }

    return [];
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

    // Name of the file to be downloaded.
    $filename = 'school_information_report';
    $context = [
      'results' => [],
      'finished' => TRUE,
    ];

    return $this->rteReportHelper->excelDownload('School-Information-Report', $header, $rows, $filename, $max_columns, $context);
  }

}
