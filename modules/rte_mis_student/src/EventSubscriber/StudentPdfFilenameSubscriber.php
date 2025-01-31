<?php

namespace Drupal\rte_mis_student\EventSubscriber;

use Drupal\entity_print\Event\FilenameAlterEvent;
use Drupal\entity_print\Event\PrintEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for changing the file name in student downloaded pdf.
 */
class StudentPdfFilenameSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   *
   * Returns the event names to listen for, the methods that should be executed.
   *
   * @return array
   *   The event names to listen for and the associated method names.
   */
  public static function getSubscribedEvents() {
    return [
      // Listen for the FILENAME_ALTER event to alter the PDF filename.
      PrintEvents::FILENAME_ALTER => 'alterFilename',
    ];
  }

  /**
   * Alters the filename of the generated PDF.
   *
   * This method is called when the FILENAME_ALTER event is dispatched. It
   * iterates over the entities being printed and sets a custom filename for
   * nodes of the 'transactions' content type.
   *
   * @param \Drupal\entity_print\Event\FilenameAlterEvent $event
   *   The event to alter the PDF filename.
   */
  public function alterFilename(FilenameAlterEvent $event) {

    // Get the entities being printed.
    $entities = $event->getEntities();
    // Iterate over each entity to check and modify its filename if necessary.
    foreach ($entities as $entity) {
      $filename = [];
      // Check if the entity is a node.
      if ($entity->getEntityTypeId() === 'mini_node') {
        // Check if the bundle is 'student_details'.
        if ($entity->bundle() == 'student_details') {
          // Get the created date of the entity and format it.
          $created_date = $entity->getCreatedTime();
          $formatted_date = date('Y-m-d', $created_date);
          // Get the student name.
          $student_name = $entity->get('field_student_name')->getString();
          // Construct the new filename.
          $new_filename = $student_name . '-' . $formatted_date;
          // Update the filenames array with the new filename for this entity.
          $filename[$entity->id()] = $new_filename;
        }
      }
    }
    // Update the filenames in the event with the modified filenames array.
    $event->setFilenames($filename);
  }

}
