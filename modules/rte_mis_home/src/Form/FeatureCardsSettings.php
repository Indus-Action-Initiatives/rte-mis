<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Feature Cards global defaults for rte_mis_home.
 */
final class FeatureCardsSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'rte_mis_home_feature_cards_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['rte_mis_home.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('rte_mis_home.settings');
    $cards = $config->get('feature_cards') ?: [];

    $icon_options = [
      'education'   => $this->t('Education (book)'),
      'reservation' => $this->t('Reservation (people)'),
      'location'    => $this->t('Location (pin)'),
      'calendar'    => $this->t('Calendar'),
      'document'    => $this->t('Document'),
      'info'        => $this->t('Info'),
    ];

    $form['feature_cards'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Feature Cards (global defaults)'),
      '#tree' => TRUE,
    ];

    for ($i = 0; $i < 6; $i++) {
      $card = $cards[$i] ?? [];
      $form['feature_cards'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('Card @num', ['@num' => $i + 1]),
        '#open' => $i < 3,
      ];
      $form['feature_cards'][$i]['icon'] = [
        '#type' => 'select',
        '#title' => $this->t('Icon'),
        '#options' => $icon_options,
        '#default_value' => $card['icon'] ?? 'info',
        '#empty_option' => $this->t('- None -'),
      ];
      $form['feature_cards'][$i]['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title'),
        '#default_value' => $card['title'] ?? '',
      ];
      $form['feature_cards'][$i]['description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Description'),
        '#rows' => 2,
        '#default_value' => $card['description'] ?? '',
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $raw = $form_state->getValue('feature_cards') ?: [];
    // Strip empty cards before saving.
    $cards = array_values(array_filter($raw, fn($c) => !empty($c['title'])));

    $this->config('rte_mis_home.settings')
      ->set('feature_cards', $cards)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
