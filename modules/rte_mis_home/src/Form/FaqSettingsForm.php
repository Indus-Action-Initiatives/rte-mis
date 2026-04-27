<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure FAQ global defaults for rte_mis_home.
 */
final class FaqSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'rte_mis_home_faq_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['rte_mis_home.faq_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('rte_mis_home.faq_settings');
    $items = $config->get('faq_items') ?: [];
    $title = $config->get('title') ?: 'Frequently Asked Questions';

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('FAQ Section Title'),
      '#default_value' => $title,
      '#required' => TRUE,
    ];

    $form['faq_items'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('FAQ Items'),
      '#tree' => TRUE,
    ];

    for ($i = 0; $i < 10; $i++) {
      $item = $items[$i] ?? [];
      $form['faq_items'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('FAQ @num', ['@num' => $i + 1]),
        '#open' => $i < 3 || !empty($item['question']),
      ];
      $form['faq_items'][$i]['question'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Question'),
        '#default_value' => $item['question'] ?? '',
      ];
      $answer_val = $item['answer'] ?? '';
      $form['faq_items'][$i]['answer'] = [
        '#type' => 'text_format',
        '#title' => $this->t('Answer'),
        '#format' => 'full_html',
        '#allowed_formats' => ['full_html'],
        '#default_value' => is_array($answer_val) ? $answer_val['value'] : $answer_val,
      ];
      $form['faq_items'][$i]['open'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Initially Open'),
        '#default_value' => $item['open'] ?? FALSE,
      ];
    }

    $form['view_all_link'] = [
      '#type' => 'details',
      '#title' => $this->t('View All Link'),
      '#open' => TRUE,
    ];

    $form['view_all_link']['view_all_link_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link URL'),
      '#default_value' => $config->get('view_all_link_url'),
      '#description' => $this->t('Example: /faqs or https://example.com/faqs'),
    ];

    $form['view_all_link']['view_all_link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link Text'),
      '#default_value' => $config->get('view_all_link_text') ?: $this->t('View All'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $raw = $form_state->getValue('faq_items') ?: [];
    // Strip empty cards before saving.
    $items = array_values(array_filter($raw, fn($c) => !empty(trim($c['question']))));

    $this->config('rte_mis_home.faq_settings')
      ->set('title', $form_state->getValue('title'))
      ->set('faq_items', $items)
      ->set('view_all_link_url', $form_state->getValue('view_all_link_url'))
      ->set('view_all_link_text', $form_state->getValue('view_all_link_text'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
