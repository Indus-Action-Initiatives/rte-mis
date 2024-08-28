<?php

namespace Drupal\rte_mis_logs\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rte_mis_logs\Helper\LogHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Customer controller to display the log data.
 */
class LogsController extends ControllerBase {
  /**
   * Log Helper Service.
   *
   * @var \Drupal\rte_mis_logs\Helper\LogHelper
   */
  protected $logHelper;

  /**
   * The Config factory interface service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The date formater interface service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('rte_mis_logs.log_helper'),
      $container->get('config.factory'),
      $container->get('date.formatter'),
      $container->get('current_user')
    );
  }

  /**
   * LogsController constructor.
   *
   * @param \Drupal\rte_mis_logs\Helper\LogHelper $log_helper
   *   Log Helper Service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory interface service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter interface service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   */
  final public function __construct(
    LogHelper $log_helper,
    ConfigFactoryInterface $config_factory,
    DateFormatterInterface $date_formatter,
    AccountProxyInterface $current_user,
  ) {
    $this->logHelper = $log_helper;
    $this->configFactory = $config_factory;
    $this->dateFormatter = $date_formatter;
    $this->currentUser = $current_user;
  }

  /**
   * Returns the build to the logs admin page.
   */
  public function listLogs() {
    // Fetching column headers.
    $column_headers = $this->logHelper->getLogTableHeaders() ?? [];
    return [
      '#theme' => 'logs_table',
      '#cache' => ['max-age' => 0],
      '#attached' => [
        'library' => [
          'rte_mis_logs/logging',
        ],
        'drupalSettings' => [
          'rte_mis_logs' => [
            'column_headers' => $column_headers,
          ],
        ],
      ],
    ];
  }

  /**
   * Returns the build to the logs admin page.
   */
  public function listAjax(Request $request) {
    try {
      // Fetching all the logs from the file.
      $log_data = $this->logHelper->getLogTableData() ?? [];
      // Fetching the timezone.
      $timezone = $this->configFactory->get('system.date')->get('timezone.default');
      // The headings of the column on the table.
      $column_headers = $this->logHelper->getLogTableHeaders();

      // Checking for empty log data, timezone & column headers.
      if (!empty($log_data) && !empty($timezone) && !empty($column_headers)) {
        // Fetch the current user roles.
        $roles = $this->currentUser->getRoles();

        // Changing data from an array of JSON to an array of associative
        // arrays.
        $log_data = array_filter(array_map(function ($item) use ($timezone, $column_headers, $roles) {
          if (!empty($item) && $this->logHelper->jsonValidator($item)) {
            $item_array = json_decode($item, TRUE);
            // Apply role-based filtering.
            if (in_array('state_admin', $roles) && $item_array['channel'] !== 'rte_mis_lottery') {
              // Skip logs that are not 'rte_mis_lottery' for state_admin.
              return NULL;
            }

            if (!empty($item_array['created'])) {
              $item_array['created'] = $this->dateFormatter->format($item_array['created'], 'custom', 'd/m/Y-H:i', $timezone);
            }

            if (!empty($item_array)) {
                // Check for extra terms present in column but not in log.
                $extra_keys = array_diff($column_headers, array_keys($item_array));
              if (!empty($extra_keys)) {
                foreach ($extra_keys as $value) {
                  $item_array[$value] = 'No Data';
                }
              }
            }

            return $item_array;
          }
        }, $log_data));
        // Reindex the array to ensure keys are consecutive numeric keys.
        $log_data = array_values(array_filter($log_data));
      }

      // Pagination parameters for the datatables.
      $draw = $request->query->get('draw', 1);
      $total_records = count($log_data);
      $response = [
        'draw' => $draw,
        'recordsTotal' => $total_records,
        'data' => $log_data,
      ];

    }
    catch (\Exception $e) {
      $response = [
        'error' => 'An error occurred while retrieving log data.',
      ];
    }
    return new JsonResponse($response);
  }

}
