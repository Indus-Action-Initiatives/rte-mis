<?php

namespace Drupal\rte_mis_lottery\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;

/**
 * Class ClearTableManager.
 *
 * Provides functionality to truncate a specified table.
 */
class ClearTableManager {

  /**
   * Active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  public $configFactory;

  /**
   * Constructs a ClearTableManager object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(Connection $database, ConfigFactoryInterface $config_factory) {
    $this->database = $database;
    $this->configFactory = $config_factory;
  }

  /**
   * Truncates all the entries from the custom `lottery_status` table.
   */
  public function clearTable() {
    $config = $this->configFactory->get('rte_mis_lottery.settings');
    $time_interval = $config->get('time_interval');
    // Calculate the current timestamp.
    $expected_expiry = strtotime("-{$time_interval} hours", time());
    // Delete records where created timestamp is
    // earlier than the current timestamp.
    $this->database->delete('lottery_status')
      ->condition('created', $expected_expiry, '<')
      ->execute();
  }

}
