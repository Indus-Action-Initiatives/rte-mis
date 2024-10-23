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
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
   * Constructs the controller instance.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    ConfigFactoryInterface $config_factory,
    RteReportHelper $rte_report_helper,
    Connection $database,
    RouteMatchInterface $route_match,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->configFactory = $config_factory;
    $this->rteReportHelper = $rte_report_helper;
    $this->database = $database;
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
      $container->get('database'),
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
      // Block admin data will be found at `/schools-list`.
      return AccessResult::forbidden();
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

    if ((is_numeric($id) && $this->rteReportHelper->checkLocation($id)) || $id == NULL) {
      // Current user id.
      $currentUserId = $this->currentUser->id();
      $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);

      if ($currentUser instanceof UserInterface) {
        if (in_array('district_admin', $currentUser->getRoles(TRUE))) {
          // Get location ID from user field.
          $locationId = $currentUser->get('field_location_details')->getString() ?? NULL;
          if (!$id && $locationId) {
            $url = Url::fromRoute('rte_mis_report.controller.school_registration_report', ['id' => $locationId])->toString();

            // Return a redirect response.
            return new RedirectResponse($url);
          }
        }
      }

      $header = $this->getHeaders($id);
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

      $build['pager'] = [
        '#type' => 'pager',
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
          $build['export_button']['#url'] = Url::fromRoute('rte_mis_report.export_district_block_excel', ['id' => $id]);
        }
        else {
          // If no ID, generate the URL without passing 'id' parameter.
          $build['export_button']['#url'] = Url::fromRoute('rte_mis_report.export_district_block_excel');
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
   */
  protected function getHeaders($id = NULL) {
    // Return header based on the user role.
    $header = [
      $this->t('Total Schools'), $this->t('Registered Schools'), $this->t('Pending BEO Approval'), $this->t('Pending DEO Approval'), $this->t('Approved Schools'), $this->t('Mapping Completed'), $this->t('Mapping Pending'),
    ];

    $currentUserRole = $this->currentUser->getRoles(TRUE);

    if (array_intersect(['app_admin', 'state_admin'], $currentUserRole) && !$id) {
      $header = array_merge([
        $this->t('No.'), $this->t('District Name'), $this->t('Total Block'),
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
            $this->t('No.'), $this->t('Total Block'),
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
      }

    }

    return $content;
  }

  /**
   * Get content for state admin.
   */
  protected function getStateAdminContent($id = NULL) {
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
        $registered_schools = count($this->rteReportHelper->getRegisteredSchoolList($district->tid));
        $pending_beo_approval = $this->rteReportHelper->getSchoolStatus($district->tid, 'submitted');
        $pending_deo_approval = $this->rteReportHelper->getSchoolStatus($district->tid, 'approved_by_beo');
        $approved_school = count($this->rteReportHelper->getRegisteredSchoolList($district->tid, 'approved'));
        $mapping_completed = count($this->rteReportHelper->mappingStatus($district->tid, TRUE));
        $mapping_pending = count($this->rteReportHelper->mappingStatus($district->tid));

        $status_types = [
          'registered' => $registered_schools,
          'pending_beo_approval' => $pending_beo_approval,
          'pending_deo_approval' => $pending_deo_approval,
          'approved' => $approved_school,
          'mapping_completed' => $mapping_completed,
          'mapping_pending' => $mapping_pending,
        ];

        // Create link render array.
        $url = Url::fromUri("internal:/school-registration-report/{$district->tid}");
        // Render link for total schools on the portal.
        $block_list = Link::fromTextAndUrl($blocks, $url)->toRenderable();

        // Create link render array.
        $url = Url::fromUri("internal:/schools-list/{$district->tid}");
        // Render link for total schools on the portal.
        $total_school_link = Link::fromTextAndUrl($total_schools, $url)->toRenderable();

        // Initialize an array to store the links.
        $status_links = [];
        foreach ($status_types as $status => $status_value) {
          if ($status_value != 0) {
            $url = Url::fromUri("internal:/schools-list/{$district->tid}", [
              'query' => ['status' => $status],
            ]);
            $link = Link::fromTextAndUrl($status_value, $url)->toRenderable();
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
      $blocks = $this->rteReportHelper->locationList($locationId);
      if ($blocks) {
        foreach ($blocks as $block) {
          $total_schools = $this->rteReportHelper->getSchoolListCount($block->tid);
          $registered_schools = count($this->rteReportHelper->getRegisteredSchoolList($block->tid));
          $pending_beo_approval = $this->rteReportHelper->getSchoolStatus($block->tid, 'submitted');
          $pending_deo_approval = $this->rteReportHelper->getSchoolStatus($block->tid, 'approved_by_beo');
          $approved_school = count($this->rteReportHelper->getRegisteredSchoolList($block->tid, 'approved'));
          $mapping_completed = count($this->rteReportHelper->mappingStatus($block->tid, TRUE));
          $mapping_pending = count($this->rteReportHelper->mappingStatus($block->tid));

          $status_types = [
            'registered' => $registered_schools,
            'pending_beo_approval' => $pending_beo_approval,
            'pending_deo_approval' => $pending_deo_approval,
            'approved' => $approved_school,
            'mapping_completed' => $mapping_completed,
            'mapping_pending' => $mapping_pending,
          ];

          // Create link render array.
          $url = Url::fromUri("internal:/schools-list/{$block->tid}");
          // Render link for total schools on the portal.
          $total_school_link = Link::fromTextAndUrl($total_schools, $url)->toRenderable();

          // Initialize an array to store the links.
          $status_links = [];
          foreach ($status_types as $status => $status_value) {
            if ($status_value != 0) {
              $url = Url::fromUri("internal:/schools-list/{$block->tid}", [
                'query' => ['status' => $status],
              ]);
              $link = Link::fromTextAndUrl($status_value, $url)->toRenderable();
              $status_links[] = ['data' => $link];
            }
            else {
              // Add plain text if the value is zero.
              $status_links[] = ['data' => $status_value];
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

    $filename = 'school_registration_report';
    $context = [
      'results' => [],
      'finished' => TRUE,
    ];

    return $this->rteReportHelper->excelDownload('School-Registration-Report', $header, $rows, $filename, $max_columns, $context);
  }

}
