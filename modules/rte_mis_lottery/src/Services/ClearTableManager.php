<?php

namespace Drupal\rte_mis_lottery\Services;

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
   * Constructs a ClearTableManager object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Truncates all the entries from the custom `lottery_status` table.
   */
  public function clearTable() {
    $this->database->truncate('lottery_status')->execute();
  }

}
