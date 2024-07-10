<?php

namespace Drupal\rte_mis_lottery\Commands;

use Drupal\Core\Database\Connection;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush command file.
 */
class LotteryCommands extends DrushCommands {

  /**
   * Active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a RteLotteryHelper object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * A custom Drush command that delete data from lottery tables.
   *
   * @command delete-lottery-data
   * @aliases dld
   */
  public function deleteLotteryEntry() {
    if ($this->io()->confirm('Are you sure you want to delete the lottery data?', FALSE)) {
      // If user confirms, delete the data.
      $this->database->truncate('rte_mis_lottery_results')->execute();
      $this->database->truncate('rte_mis_lottery_school_seats_status')->execute();
      $this->output()->writeln('Deleted lottery data successfully');
    }
    else {
      // If user declines, output a message.
      $this->output()->writeln('Deletion cancelled.');
    }

  }

}
