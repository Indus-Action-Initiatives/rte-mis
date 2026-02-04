<?php

namespace Drupal\rte_mis_school\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'fee_details_table_widget' widget.
 *
 * @FieldWidget(
 *   id = "fee_details_table_widget",
 *   label = @Translation("Fee details table widget"),
 *   field_types = {
 *     "entity_reference_revisions"
 *   }
 * )
 */
class FeeDetailsTableWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    // Render only once.
    if ($delta !== 0) {
      return [];
    }

    if ($this->fieldDefinition->getName() !== 'field_fee_details') {
      return $element;
    }

    // Read from / to class.
    $from = $form['field_education_level_from']['widget']['#default_value'][0] ?? NULL;
    $to = $form['field_education_level_to']['widget']['#default_value'][0] ?? NULL;

    // Fallback for edit form.
    if (!$from || !$to) {
      $entity = $items->getEntity();
      $from = $entity->get('field_education_level_from')->value ?? 1;
      $to = $entity->get('field_education_level_to')->value ?? 12;
    }
    $options = $form["field_education_level_from"]["widget"]["#options"];

    $from = (int) $from;
    $to   = (int) $to;

    if ($from > $to) {
      [$from, $to] = [$to, $from];
    }

    $classes = range($from, $to);

    // Index existing paragraphs by class.
    $existing = [];
    foreach ($items as $item) {
      if (!empty($item->entity)) {
        $class = $item->entity->get('field_class_list')->value;
        $existing[$class] = $item->entity;
      }
    }

    // Wrapper.
    $element['#type'] = 'container';
    $element['#tree'] = TRUE;
    $element['#attributes']['id'] = 'fee-table-wrapper';

    // Put widget values under "value".
    $element['value']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Class'),
        $this->t('Annual fee'),
        $this->t('Total students'),
      ],
      '#attributes' => [
        'style' => 'overflow:auto;display:block;',
      ],
      '#tree' => TRUE,
    ];

    foreach ($classes as $class) {
      $paragraph = $existing[$class] ?? NULL;
      if (isset($options[$class])) {
        $class_label = $options[$class];
      }
      else {
        $class_label = $class;
      }

      $element['value']['table'][$class]['class_label'] = [
        '#markup' => $class_label,
      ];
      $element['value']['table'][$class]['total_fees'] = [
        '#type' => 'number',
        '#title' => $this->t('Annual fee for class @c', ['@c' => $class]),
        '#title_display' => 'invisible',
        '#default_value' => $paragraph ? $paragraph->get('field_total_fees')->value : '',
        '#min' => 0,
      ];

      $element['value']['table'][$class]['total_students'] = [
        '#type' => 'number',
        '#title' => $this->t('Total students for class @c', ['@c' => $class]),
        '#title_display' => 'invisible',
        '#default_value' => $paragraph ? $paragraph->get('field_total_students')->value : '',
        '#min' => 0,
      ];

      $element['value']['table'][$class]['class_value'] = [
        '#type' => 'hidden',
        '#value' => $class,
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {

    $storage = \Drupal::entityTypeManager()->getStorage('paragraph');
    $items = [];

    // Extract rows from table.
    if (empty($values[0]['value']['table'])) {
      return [];
    }

    $rows = $values[0]['value']['table'];

    $host = $form_state->getFormObject()->getEntity();
    $field_name = $this->fieldDefinition->getName();

    $existing = [];

    // IMPORTANT: during paragraph widget validation the host entity
    // may NOT contain this field.
    if ($host->hasField($field_name)) {
      foreach ($host->get($field_name) as $item) {
        if ($item->entity) {
          $key = $item->entity->get('field_class_list')->value;
          $existing[$key] = $item->entity;
        }
      }
    }

    // Create / update paragraphs.
    foreach ($rows as $class => $row) {
      $fee = $row['total_fees'] ?? NULL;
      $students = $row['total_students'] ?? NULL;

      // Treat empty strings as empty.
      if ($fee === '' && $students === '') {
        continue;
      }

      if (isset($existing[$class])) {
        $paragraph = $existing[$class];
      }
      else {
        $paragraph = $storage->create([
          'type' => 'fee_details',
          'field_class_list' => $class,
        ]);
      }

      $paragraph->set('field_total_fees', $fee);
      $paragraph->set('field_total_students', $students);
      $paragraph->save();

      $items[] = [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ];
    }

    return $items;
  }

}
