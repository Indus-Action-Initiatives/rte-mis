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
   * Deletes the data from the field_allotted_students field.
   *
   * @param int $entity_id
   *   The entity ID.
   *
   * @command rte_mis_lottery:delete-allotted-students
   * @aliases ldas
   * @usage lottery:delete-allotted-students 123
   *   Deletes the allotted students from the mini_node with ID 123.
   */
  public function clearAllotedStudentFieldInSchool($entity_id) {

    if ($this->io()->confirm('Are you sure you want to delete the alloted students in provided school?', FALSE)) {
      // Load the entity.
      $mini_node_storage = $this->entityTypeManager->getStorage('mini_node');
      $mini_node = $mini_node_storage->load($entity_id);
      if (!$mini_node) {
        $this->logger()->error(dt('Mini Node with ID @entity_id does not exist.', ['@entity_id' => $entity_id]));
        return;
      }
      // Check if the field_allotted_students field exists on the entity.
      if (!$mini_node->hasField('field_allotted_students')) {
        $this->logger()->error(dt('Field field_allotted_students does not exist on mini node with ID @entity_id.', ['@entity_id' => $entity_id]));
        return;
      }
      // Get the referenced paragraphs.
      $paragraphs = $mini_node->get('field_allotted_students')->referencedEntities();
      // Delete each referenced paragraph.
      foreach ($paragraphs as $paragraph) {
        $paragraph->delete();
      }
      // Clear the field.
      $mini_node->set('field_allotted_students', []);
      $mini_node->save();
      $this->logger()->success(dt('The field_allotted_students field on mini_node with ID @entity_id has been cleared and its referenced paragraphs deleted.', ['@entity_id' => $entity_id]));
    }
    else {
      // If user declines, output a message.
      $this->output()->writeln('Operation cancelled.');
    }

  }

}
