<?php

namespace Drupal\rte_mis_logs\Logger;

use Drupal\filelog\Logger\FileLog;

/**
 * Updates the render method.
 */
class CustomFileLog extends FileLog {

  /**
   * {@inheritdoc}
   */
  protected function render(mixed $level, string $message, array $context = []): string {
    $entry = parent::render($level, $message, $context);
    // Regular expression pattern to match placeholders in the format.
    $pattern = '/\[(.*?)\]/';
    // Fetching the values of the log entry.
    if (!empty($entry)) {
      preg_match_all($pattern, $entry, $values);
    }

    // Adjusting the array if there is a presence of the client key.
    foreach ($values[1] as $key => $value) {
      if (strpos($value, 'client:') === 0) {
        // Split the client value.
        $clientValues = explode(', ', substr($value, 7));
        array_splice($values[1], $key, count($clientValues) - 1, $clientValues);
      }
    }

    // User entered format of the log.
    $format = $this->config->get('format');
    preg_match_all($pattern, $format, $keys);
    foreach ($keys[1] as $key => $value) {
      // Finding the position of the colon.
      $colonPos = strrpos($value, ':');
      // If colon exists, extract the substring after it.
      if ($colonPos !== FALSE) {
        $keys[1][$key] = substr($value, $colonPos + 1);
      }
    }

    // Storing the keys and values as an associative array.
    $store = [];
    if (count($keys[1]) == count($values[1])) {
      foreach ($keys[1] as $index => $element) {
        $date_string = $values[1][$index];
        $format = '';
        switch ($element) {
          case 'created':
            $format = 'D, m/d/Y - H:i';
            break;

          case 'long':
            $format = "l, F j, Y - H:i";
            $element = 'created';
            break;

          case 'short':
            $format = "m/d/Y - H:i";
            $element = 'created';
            break;

          // Add more cases for other date elements.
          default:
            $store[$element] = $values[1][$index];
            continue 2;
        }

        $date_time = \DateTime::createFromFormat($format, $date_string);
        if ($date_time !== FALSE) {
          $values[1][$index] = $date_time->getTimestamp();
        }
        $store[$element] = $values[1][$index];
      }
    }
    if ($store['channel'] == 'rte_mis_lottery') {
      return json_encode($store);
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    if ($context['channel'] == 'rte_mis_lottery') {
      parent::log($level, $message, $context);
      \Drupal::service('rte_mis_logs.log_helper')->sliceLogs();
    }
  }

}
