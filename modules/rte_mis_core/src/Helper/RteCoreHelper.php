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
   * Get the campaign information.
   *
   * @param string $event_type
   *   The campaign item type.
   */
  public function isCampaignValid(string $event_type) {
    // Return if event type if empty.
    if (empty($event_type)) {
      return FALSE;
    }

    $current_academic_year = _rte_mis_core_get_current_academic_year();
    // Check if there are any existing campaign for the same academic year.
    $campaign = $this->entityTypeManager->getStorage('mini_node')->loadByProperties([
      'type' => 'campaign',
      'field_academic_year' => $current_academic_year,
      'status' => 1,
    ]);

    if (count($campaign) >= 1) {
      $campaign = array_values($campaign);
      $items = $campaign[0]->get('field_campaign_items')->getValue();
      // Check if any campaign item is added.
      if (count($items) > 0) {
        foreach ($items as $item) {
          $target_id = $item['target_id'];
          $date_range = $this->entityTypeManager->getStorage('paragraph')->load($target_id);
          // Check if paragraph exists.
          if ($date_range instanceof ParagraphInterface) {
            $type = $date_range->get('field_event_type')->getString();

            if ($type === $event_type) {
              $date = $date_range->get('field_date')->getValue();
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
