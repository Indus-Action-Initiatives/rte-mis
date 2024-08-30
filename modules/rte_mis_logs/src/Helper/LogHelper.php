<?php

namespace Drupal\rte_mis_logs\Helper;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\filelog\LogFileManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Customer Helper.
 *
 * @package Drupal\rte_mis_logs
 */
class LogHelper {

  /**
   * The filelog.file_manager service.
   *
   * @var \Drupal\filelog\LogFileManagerInterface
   */
  protected LogFileManagerInterface $fileManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The config.factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $config;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * LogHelper constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config.factory service.
   * @param \Drupal\filelog\LogFileManagerInterface $file_manager
   *   The filelog service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file_system service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    LogFileManagerInterface $file_manager,
    FileSystemInterface $file_system,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->config = $config_factory;
    $this->fileManager = $file_manager;
    $this->fileSystem = $file_system;
    $this->logger = $logger_factory->get('rte_mis_logs');
  }

  /**
   * Prepare the log table data.
   */
  public function getLogTableData() {
    $file_location = $this->fileManager->getFileName();
    if (!empty($file_location)) {
      $file_content = file($file_location);
    }
    return $file_content;
  }

  /**
   * Function that slices the logs in the file,if exceeds the maximum anount.
   */
  public function sliceLogs() {
    $file_location = $this->fileManager->getFileName();
    $file_content = 0;
    if (!empty($file_location)) {
      $file_content = file($file_location);
      $file_count = count($file_content);
    }
    $max_content_allowed = $this->config->get('rte_mis_logs.settings')->get('msg_size');
    $difference = $file_count - $max_content_allowed;
    if ($difference > 0) {
      // Remove the initial log entries to meet the size limit.
      $trimmed_content = array_slice($file_content, $difference);
      // Function to overwrite the written content.
      $this->overwriteLogDetails($file_location, $trimmed_content);
    }
  }

  /**
   * Overwrites the logwritten by filelog module.
   */
  public function overwriteLogDetails($file_location, $trimmed_content) {
    try {
      file_put_contents($file_location, $trimmed_content);
    }
    catch (\Exception $e) {
      // Handle file write error.
      $this->logger->error('Error writing to log file: @error', ['@error' => $e->getMessage()]);
    }
  }

  /**
   * Filter the logs based on search parameters.
   *
   * @param array $total_log_data
   *   The unfiltered log data.
   * @param string $search_value
   *   The searched string.
   *
   * @return array
   *   Returns the filtered array.
   */
  public function searchData(array $total_log_data, string $search_value = '') {
    $column_headers = $this->getLogTableHeaders();
    if (!empty($search_value)) {
      $filtered_data = array_filter($total_log_data, function ($log_entry) use ($search_value, $column_headers) {
        // Iterate over column headers to search in relevant fields.
        foreach ($column_headers as $header) {
          // Check header key, if it exists in log entry and search value found.
          if (array_key_exists($header, $log_entry) && stripos($log_entry[$header], $search_value) !== FALSE) {
            return TRUE;
          }
        }
        return FALSE;
      });
      return $filtered_data;
    }
    return $total_log_data;
  }

  /**
   * Filter on the basis of each column.
   *
   * @param array $log_data
   *   The total log data.
   * @param array $filters
   *   The search and column name value stored in an array.
   *
   * @return array
   *   The filtered data.
   */
  public function filterData(array $log_data, array $filters) {
    $filtered_data = array_filter($log_data, function ($row) use ($filters) {
      foreach ($filters as $column_name => $search_value) {
        // Check if the column exists in the row.
        if (array_key_exists($column_name, $row)) {
          // If any filter condition fails, discard the row.
          if (stripos($row[$column_name], $search_value) === FALSE) {
            return FALSE;
          }
        }
        // If any specified column is missing, discard the row.
        else {
          return FALSE;
        }
      }
      // All filter conditions passed, keep the row.
      return TRUE;
    });
    return $filtered_data;
  }

  /**
   * Sorting the databased on column and direction.
   *
   * @param array $data
   *   The table contents.
   * @param string $column
   *   The name of the column based on which the sort to take place.
   * @param string $dir
   *   The direction of the sort asc/desc.
   *
   * @return array
   *   The sorted array
   */
  public function sortData(array $data, string $column, string $dir = 'desc') {
    if (!empty($data)) {
      usort($data, function ($a, $b) use ($column, $dir) {
        if (!isset($a[$column]) || !isset($b[$column])) {
          return 0;
        }
        // Perform case-insensitive comparison.
        $result = strcasecmp($a[$column], $b[$column]);

        // Adjust result based on sort direction.
        if ($dir === 'desc') {
          // Reverse the result for descending sort.
          $result *= -1;
        }
        return $result;
      });
    }
    return $data;
  }

  /**
   * Checks if the item is json or not.
   *
   * @param string $data
   *   The input data.
   *
   * @return bool
   *   The result of the json validation.
   */
  public function jsonValidator(string $data) {
    if (!empty($data)) {
      return is_string($data) && is_array(json_decode($data, TRUE)) ? TRUE : FALSE;
    }
    return FALSE;
  }

  /**
   * Fetch the column headers.
   *
   * @return array
   *   The name of the headers.
   */
  public function getLogTableHeaders() {
    $log_columns = $this->config->get('rte_mis_logs.settings')->get('log_columns');
    // Filter out any indexed elements (numeric keys).
    $log_columns = array_filter($log_columns, function ($key) {
      return is_string($key);
    }, ARRAY_FILTER_USE_KEY);

    // Initialize an empty array to store keys with the value 'true'.
    $enabled_columns = [];

    // Iterate through the filtered 'log_columns' array.
    foreach ($log_columns as $key => $value) {
      // If the value is 'true', add the key to the $enabled_columns array.
      if ($value === TRUE) {
        $enabled_columns[] = $key;
      }
    }

    return $enabled_columns;
  }

}
