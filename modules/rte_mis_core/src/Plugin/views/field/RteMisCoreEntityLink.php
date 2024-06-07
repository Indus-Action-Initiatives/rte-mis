<?php

namespace Drupal\rte_mis_core\Plugin\views\field;

use Drupal\eck\EckEntityInterface;
use Drupal\views\Plugin\views\field\EntityLink;
use Drupal\views\ResultRow;

/**
 * Field handler to present a link to an entity.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("entity_link")
 */
class RteMisCoreEntityLink extends EntityLink {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $row) {
    $entity = $this->getEntity($row);
    if ($entity instanceof EckEntityInterface && in_array($entity->bundle(), [
      'school_details', 'academic_session', 'student_details',
    ])) {
      $row->mini_node_field_data_langcode = $this->languageManager->getCurrentLanguage()->getId();
      return parent::render($row);
    }
    return [];
  }

}
