<?php

namespace Drupal\rte_mis_lottery\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a RteLotteryHelper object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Command that delete data from lottery tables.
   *
   * @command rte_mis_lottery:delete-lottery-data
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

  /**
   * Command that deletes student mini_node.
   *
   * @command rte_mis_lottery:delete-student-data
   * @aliases dsd
   */
  public function deleteStudentEntry() {
    if ($this->io()->confirm('Are you sure you want to delete the Student Mini Node?', FALSE)) {
      $mini_node_storage = $this->entityTypeManager->getStorage('mini_node');
      $result = $mini_node_storage->getQuery()
        ->condition('type', 'student_details')
        ->accessCheck(FALSE)
        ->execute();

      foreach ($result as $value) {
        $student = $mini_node_storage->load($value);
        $student->delete();
      }
    }
    else {
      // If user declines, output a message.
      $this->output()->writeln('Deletion cancelled.');
    }

  }

  /**
   * Deletes all allocation mini node.
   *
   * @command rte_mis_lottery:delete-student-allocation
   * @aliases dsa
   */
  public function deleteStudentAllocation() {

    if ($this->io()->confirm('Are you sure you want to delete all allocation mini nodes?', FALSE)) {
      // Load the entity.
      $mini_node_storage = $this->entityTypeManager->getStorage('mini_node');
      $result = $mini_node_storage->getQuery()
        ->condition('type', 'allocation')
        ->accessCheck(FALSE)
        ->execute();
      foreach ($result as $value) {
        $student = $mini_node_storage->load($value);
        $student->delete();
      }

      $this->logger()->success(dt('Deleted all allocatio mini node.'));
    }
    else {
      // If user declines, output a message.
      $this->output()->writeln('Operation cancelled.');
    }

  }

}
