<?php

namespace Drupal\rte_mis_logs\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('rte_mis_logs.log_helper'),
      $container->get('config.factory'),
      $container->get('date.formatter'),
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
   */
  final public function __construct(
    LogHelper $log_helper,
    ConfigFactoryInterface $config_factory,
    DateFormatterInterface $date_formatter,
  ) {
    $this->logHelper = $log_helper;
    $this->configFactory = $config_factory;
    $this->dateFormatter = $date_formatter;
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
        // Changing data from an array of json to an array of associative array.
        $log_data = array_filter(array_map(function ($item) use ($timezone, $column_headers) {
          if (!empty($item) && $this->logHelper->jsonValidator($item)) {
            $item_array = json_decode($item, TRUE);
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
        $log_data = array_values($log_data);
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
