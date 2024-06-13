<?php

namespace Drupal\rte_mis_core\Form;

use Drupal\taxonomy\Form\TermDeleteForm;

/**
 * This class override the TermDeleteForm.
 */
class OverrideTermDeleteForm extends TermDeleteForm {

  /**
   * {@inheritdoc}
   */
  protected function getDeletionMessage() {
    return $this->t('Deleted %bundle %name.', [
      '%bundle' => $this->entity->bundle(),
      '%name' => $this->entity->label(),
    ]);
  }

}
