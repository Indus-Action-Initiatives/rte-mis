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
   * Constructs the controller instance.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    ConfigFactoryInterface $config_factory,
    RteReportHelper $rte_report_helper,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->configFactory = $config_factory;
    $this->rteReportHelper = $rte_report_helper;
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

      if ($rows) {
        // Add the Export to Excel button.
        $build['export_button'] = [
          '#type' => 'link',
          '#title' => $this->t('Export to Excel'),
          '#attributes' => ['class' => ['export-data-cta']],
        ];

        if ($id) {
          // If the ID is present, add it to the URL parameters.
          $build['export_button']['#url'] = Url::fromRoute('rte_mis_report.export_schools_excel', ['id' => $id]);
        }
        else {
          // If no ID, generate the URL without passing 'id' parameter.
          $build['export_button']['#url'] = Url::fromRoute('rte_mis_report.export_schools_excel');
        }
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
    // For block admin.
    $header = [
      $this->t('No.'), $this->t('School Udise Code'), $this->t('Schools'), $this->t('Registered'), $this->t('Pending BEO Approval'), $this->t('Pending DEO Approval'), $this->t('Approved'), $this->t('Mapping Completed'), $this->t('Mapping Pending'),
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
    $content = [];
    // Return the data for block admin.
    $content = $this->getBlockAdminContent($id);

    return $content;
  }

  /**
   * Get content for block admin.
   *
   * @param string $id
   *   Location Id.
   */
  protected function getBlockAdminContent($id = NULL) {
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
      $schools = $this->rteReportHelper->getSchoolList($locationId);
      if (empty($schools)) {
        return 0;
      }
      foreach ($schools as $school) {
        $school_name = $this->entityTypeManager->getStorage('taxonomy_term')->load($school)->get('field_school_name')->getString();
        $udise_code = $this->entityTypeManager->getStorage('taxonomy_term')->load($school)->getName();
        // If there is no registration found.
        // Don't check further and return 'N/A'
        // for all other entries.
        $mini_node_id = $this->rteReportHelper->checkRegistration($school, $school_name);
        if (!$mini_node_id) {
          $registration_status = 'No';
          $pending_beo_approval = 'N/A';
          $pending_deo_approval = 'N/A';
          $approved = 'N/A';
          $mapping_completed = 'N/A';
          $mapping_pending = 'N/A';
        }
        else {
          $registration_status = 'Yes';
          $schoolStatus = $this->rteReportHelper->checkSchoolStatus($mini_node_id);
          $pending_beo_approval = $schoolStatus['pending_beo_approval'];
          $pending_deo_approval = $schoolStatus['pending_deo_approval'];
          $approved = $schoolStatus['approved'];
          $mapping_completed = $schoolStatus['mapping_completed'];
          $mapping_pending = $schoolStatus['mapping_pending'];
        }

        $data[] = [$serialNumber, $udise_code, $school_name, $registration_status, $pending_beo_approval, $pending_deo_approval, $approved, $mapping_completed, $mapping_pending,
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

    // Name of the file to be downloaded.
    $filename = 'school_registration_report';
    $context = [
      'results' => [],
      'finished' => TRUE,
    ];

    return $this->rteReportHelper->excelDownload('School-Registration-Report', $header, $rows, $filename, $max_columns, $context);
  }

}
