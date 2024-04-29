<?php

namespace Drupal\rte_mis_student\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'pm_appraisal_default_widget' widget.
 *
 * @FieldWidget(
 *   id = "school_weight_widget",
 *   label = @Translation("School Weight Widget"),
 *   field_types = {
 *     "school_weight_field_type"
 *   }
 * )
 */
class SchoolsFieldWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['school'] = [
      '#type' => 'textfield',
      '#title' => 'School Name',
      '#default_value' => isset($items[$delta]->school) ? $items[$delta]->school : NULL,
      '#weight' => 0,
    ];

    $element['weight'] = [
      '#type' => 'number',
      '#title' => 'Weight',
      '#default_value' => isset($items[$delta]->weight) ? $items[$delta]->weight : NULL,
      '#weight' => 0,
    ];

    return $element;
  }

}
