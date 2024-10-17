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
 * Controller to build the school information report page.
 */
final class SchoolInformationReport extends ControllerBase {

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
   * Active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

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
    Connection $database,
    RteReportHelper $rte_report_helper,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->configFactory = $config_factory;
    $this->database = $database;
    $this->rteReportHelper = $rte_report_helper;

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
      $container->get('database'),
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
      // Block admin data will be found at `/school-information-list`.
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
      $currentUserId = $this->currentUser->id();
      $currentUser = $this->entityTypeManager->getStorage('user')->load($currentUserId);

      if ($currentUser instanceof UserInterface) {
        if (array_intersect(['district_admin', 'block_admin'], $currentUser->getRoles(TRUE))) {
          // Get location ID from user field.
          $locationId = $currentUser->get('field_location_details')->getString() ?? NULL;
          if (!$id && $locationId) {
            $url = Url::fromRoute('rte_mis_report.school_information_report', ['id' => $locationId])->toString();

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
        '#prefix' => '<div class="allotment-report-wrapper">',
        '#suffix' => '</div>',
        '#attributes' => ['class' => ['student-reports']],
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
        // Add the Export to Excel button.
        $build['export_button'] = [
          '#type' => 'link',
          '#title' => $this->t('Export to Excel'),
          '#attributes' => ['class' => ['export-data-cta']],
        ];

        if ($id) {
          // If the ID is present, add it to the URL parameters.
          $build['export_button']['#url'] = Url::fromRoute('rte_mis_report.export_school_information_report', ['id' => $id]);
        }
        else {
          // If no ID, generate the URL without passing 'id' parameter.
          $build['export_button']['#url'] = Url::fromRoute('rte_mis_report.export_school_information_report');
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
      $this->t('Registered Schools'),
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

    $currentUserRole = $this->currentUser->getRoles(TRUE);
    // Add headers for state admin.
    if (array_intersect(['app_admin', 'state_admin'], $currentUserRole) && !$id) {
      $header = array_merge([
        $this->t('No.'),
        $this->t('District Name'),
        $this->t('Total Blocks'),
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
          // Add headers for district.
          $header = array_merge([
            $this->t('No.'),
            $this->t('Block Name'),
          ], $header);
        }
        else {
          // Add headers for block.
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
        $district_id = $district->tid;
        $blocks = $this->rteReportHelper->getBlocksCount($district_id);
        // Get locations for the parent location.
        $location_ids = $this->rteReportHelper->getLocationsForParent('state_admin', $district_id);
        $schools = count($this->rteReportHelper->getRegisteredSchoolList($district_id));
        [$total_seats, $total_rte_seats] = $this->rteReportHelper->getSeatsCount($location_ids);
        $mediums_count = $this->rteReportHelper->getSchoolMediumsCount($location_ids);
        $boards_count = $this->rteReportHelper->getSchoolBoardsCount($location_ids);
        $education_levels_count = $this->rteReportHelper->getSchoolEducationLevelsCount($location_ids);
        $claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids));
        $reimbursed_claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_payment_completed'));
        $pending_claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_payment_pending'));
        // Create link render array.
        $url = Url::fromUri("internal:/school-information-report/{$district_id}");
        $block_list = Link::fromTextAndUrl((string) $blocks, $url)->toRenderable();

        // Create link render array.
        $url = Url::fromUri("internal:/school-information-list/{$district_id}");
        // Render link for total schools on the portal.
        $schools_link = Link::fromTextAndUrl((string) $schools, $url)->toRenderable();

        $row_index = $serialNumber - 1;
        $data[$row_index] = [
          $serialNumber,
          $district->name,
          [
            'data' => $block_list,
          ],
          [
            'data' => $schools_link,
          ],
          $total_seats,
          $total_rte_seats,
        ];

        // Push medium wise count.
        foreach ($this->mediums as $medium_key => $medium) {
          // If mediums count has a value greater than 0 we make it
          // clickable so that schools can be filtered, otherwise
          // render 0 as plain text.
          if (isset($mediums_count[$medium_key]) && $mediums_count[$medium_key]) {
            $url = Url::fromUri("internal:/school-information-list/{$district_id}", [
              'query' => ['medium' => $medium_key],
            ]);
            $link = Link::fromTextAndUrl($mediums_count[$medium_key], $url)->toRenderable();
            $data[$row_index][] = ['data' => $link];
          }
          else {
            $data[$row_index][] = 0;
          }
        }

        // Push board wise count.
        foreach ($this->boards as $board => $board_name) {
          // If boards count has a value greater than 0 we make it
          // clickable so that schools can be filtered, otherwise
          // render 0 as plain text.
          if (isset($boards_count[$board]) && $boards_count[$board]) {
            $url = Url::fromUri("internal:/school-information-list/{$district_id}", [
              'query' => ['board' => $board],
            ]);
            $link = Link::fromTextAndUrl($boards_count[$board], $url)->toRenderable();
            $data[$row_index][] = ['data' => $link];
          }
          else {
            $data[$row_index][] = 0;
          }
        }

        // Push education levels count.
        foreach ($this->educationLevels as $education_level => $education_level_name) {
          // If boards count has a value greater than 0 we make it
          // clickable so that schools can be filtered, otherwise
          // render 0 as plain text.
          if (isset($education_levels_count[$education_level]) && $education_levels_count[$education_level]) {
            $url = Url::fromUri("internal:/school-information-list/{$district_id}", [
              'query' => ['education_level' => $education_level],
            ]);
            $link = Link::fromTextAndUrl($education_levels_count[$education_level], $url)->toRenderable();
            $data[$row_index][] = ['data' => $link];
          }
          else {
            $data[$row_index][] = 0;
          }
        }

        // @todo Later when reimbursement claims report will be created
        // we need to make these as link.
        // Add reimbursement claims count.
        $data[$row_index][] = $claims_count;
        $data[$row_index][] = $reimbursed_claims_count;
        $data[$row_index][] = $pending_claims_count;

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
          $block_id = $block->tid;
          $schools = count($this->rteReportHelper->getRegisteredSchoolList($block_id));
          // Get locations for the parent location.
          $location_ids = $this->rteReportHelper->getLocationsForParent('district_admin', $block_id);
          [$total_seats, $total_rte_seats] = $this->rteReportHelper->getSeatsCount($location_ids);
          $mediums_count = $this->rteReportHelper->getSchoolMediumsCount($location_ids);
          $boards_count = $this->rteReportHelper->getSchoolBoardsCount($location_ids);
          $education_levels_count = $this->rteReportHelper->getSchoolEducationLevelsCount($location_ids);
          $claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids));
          $reimbursed_claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_payment_completed'));
          $pending_claims_count = count($this->rteReportHelper->getReimbursementClaims($location_ids, 'reimbursement_claim_workflow_payment_pending'));
          // Create link render array.
          $url = Url::fromUri("internal:/school-information-list/{$block_id}");
          $link = Link::fromTextAndUrl((string) $schools, $url)->toRenderable();

          $row_index = $serialNumber - 1;
          $data[$row_index] = [
            $serialNumber,
            $block->name,
            [
              'data' => $link,
            ],
            $total_seats,
            $total_rte_seats,
          ];

          // Push medium wise count.
          foreach ($this->mediums as $medium_key => $medium) {
            // If mediums count has a value greater than 0 we make it
            // clickable so that schools can be filtered, otherwise
            // render 0 as plain text.
            if (isset($mediums_count[$medium_key]) && $mediums_count[$medium_key]) {
              $url = Url::fromUri("internal:/school-information-list/{$block_id}", [
                'query' => ['medium' => $medium_key],
              ]);
              $link = Link::fromTextAndUrl($mediums_count[$medium_key], $url)->toRenderable();
              $data[$row_index][] = ['data' => $link];
            }
            else {
              $data[$row_index][] = 0;
            }
          }

          // Push board wise count.
          foreach ($this->boards as $board => $board_name) {
            // If boards count has a value greater than 0 we make it
            // clickable so that schools can be filtered, otherwise
            // render 0 as plain text.
            if (isset($boards_count[$board]) && $boards_count[$board]) {
              $url = Url::fromUri("internal:/school-information-list/{$block_id}", [
                'query' => ['board' => $board],
              ]);
              $link = Link::fromTextAndUrl($boards_count[$board], $url)->toRenderable();
              $data[$row_index][] = ['data' => $link];
            }
            else {
              $data[$row_index][] = 0;
            }
          }

          // Push education levels count.
          foreach ($this->educationLevels as $education_level => $education_level_name) {
            // If boards count has a value greater than 0 we make it
            // clickable so that schools can be filtered, otherwise
            // render 0 as plain text.
            if (isset($education_levels_count[$education_level]) && $education_levels_count[$education_level]) {
              $url = Url::fromUri("internal:/school-information-list/{$block_id}", [
                'query' => ['education_level' => $education_level],
              ]);
              $link = Link::fromTextAndUrl($education_levels_count[$education_level], $url)->toRenderable();
              $data[$row_index][] = ['data' => $link];
            }
            else {
              $data[$row_index][] = 0;
            }
          }

          // Add reimbursement claims count.
          $data[$row_index][] = $claims_count;
          $data[$row_index][] = $reimbursed_claims_count;
          $data[$row_index][] = $pending_claims_count;

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

    $filename = 'school_information_report';
    $context = [
      'results' => [],
      'finished' => TRUE,
    ];

    return $this->rteReportHelper->excelDownload('School-Information-Report', $header, $rows, $filename, $max_columns, $context);
  }

}
