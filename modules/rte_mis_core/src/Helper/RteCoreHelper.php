<?php

namespace Drupal\rte_mis_core\Helper;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Class RTE Core Helper.
 *
 * @package Drupal\rte_mis_core\Helper
 */
class RteCoreHelper {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an RteCoreHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Get the academic session information.
   *
   * @param string $event_type
   *   The academic session item type.
   */
  public function isAcademicSessionValid(string $event_type) {
    // Return if event type if empty.
    if (empty($event_type)) {
      return FALSE;
    }

    $current_academic_year = _rte_mis_core_get_current_academic_year();
    // Check if there are any existing academic for the same academic year.
    $academic_session = $this->entityTypeManager->getStorage('mini_node')->loadByProperties([
      'type' => 'academic_session',
      'field_academic_year' => $current_academic_year,
      'status' => 1,
    ]);

    if (count($academic_session) >= 1) {
      $academic_session = array_values($academic_session);
      $items = $academic_session[0]->get('field_session_details')->getValue();
      // Check if any academic_session item is added.
      if (count($items) > 0) {
        foreach ($items as $item) {
          $target_id = $item['target_id'];
          $timeline = $this->entityTypeManager->getStorage('paragraph')->load($target_id);
          // Check if paragraph exists.
          if ($timeline instanceof ParagraphInterface) {
            $type = $timeline->get('field_event_type')->getString();

            if ($type === $event_type) {
              $date = $timeline->get('field_date')->getValue();
              // Check if the current date & time falls under the school
              // registration window. If `YES` then allow the access to the
              // registration page else restrict the access.
              $current_time = time();
              $start = strtotime($date[0]['value']);
              $end = strtotime($date[0]['end_value']);

              if ($current_time >= $start && $current_time <= $end) {
                return TRUE;
              }
            }
          }
        }
      }
    }

    return FALSE;
  }

}
