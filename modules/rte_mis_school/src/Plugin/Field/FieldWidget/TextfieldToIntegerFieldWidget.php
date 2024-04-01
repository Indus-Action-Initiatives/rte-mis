<?php

namespace Drupal\rte_mis_school\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Plugin implementation of the 'textfield_to_integer' widget.
 *
 * @FieldWidget(
 *   id = "textfield_to_integer",
 *   label = @Translation("Integer textfield"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class TextfieldToIntegerFieldWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'label' => 'Number',
      'placeholder' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $value = $items[$delta]->value ?? NULL;
    $element += [
      '#type' => 'number',
      '#default_value' => $value,
      '#placeholder' => $this->getSetting('placeholder'),
      '#element_validate' => [
        [$this, 'validateUdiseLength'],
      ],
    ];
    $element['#title'] = $this->getSetting('label');

    return ['value' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $this->getSetting('placeholder'),
      '#description' => $this->t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];

    $element['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $this->getSetting('label'),
      '#description' => $this->t('Label will be displayed above the field.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {

    $summary[] = $this->t('Label: @label', [
      '@label' => $this->getSetting('label'),
    ]);

    $summary[] = $this->t('Placeholder: @placeholder', [
      '@placeholder' => $this->getSetting('placeholder'),
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $violation, array $form, FormStateInterface $form_state) {
    return $element['value'];
  }

  /**
   * Validates the length of the udise code.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateUdiseLength($element, FormStateInterface $form_state) {
    $value = $element['#value'];

    // Check if the value is exactly 11 digits long.
    if (strlen($value) !== 11) {
      $form_state->setError($element, $this->t('The School Udise Code must be exactly 11 digits long.'));
    }
  }

}
