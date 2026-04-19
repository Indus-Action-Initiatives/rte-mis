<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure FAQ Page settings for the standalone FAQ page with dynamic Add More buttons.
 */
final class FaqPageSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'rte_mis_home_faq_page_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['rte_mis_home.faq_page_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('rte_mis_home.faq_page_settings');
    $categories = $config->get('categories') ?: [];
    $title = $config->get('title') ?: 'Frequently Asked Questions';

    // Store state on first load
    if ($form_state->get('num_categories') === NULL) {
      $form_state->set('num_categories', count($categories) ?: 1);
      
      // Initialize items count per category based on config data
      foreach ($categories as $c => $cat_data) {
        $count = isset($cat_data['items']) ? count($cat_data['items']) : 1;
        $form_state->set(['num_items', $c], $count ?: 1);
      }
    }

    $form['#tree'] = TRUE; // Must be true across the form

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Page Title'),
      '#default_value' => $title,
      '#required' => TRUE,
    ];

    $form['categories_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'faq-categories-wrapper'],
    ];

    $num_categories = $form_state->get('num_categories');

    for ($c = 0; $c < $num_categories; $c++) {
      $cat_data = $categories[$c] ?? [];
      $cat_has_title = !empty($cat_data['title']);
      
      // Items count for this category
      if ($form_state->get(['num_items', $c]) === NULL) {
        $form_state->set(['num_items', $c], 1);
      }
      $num_items = $form_state->get(['num_items', $c]);

      $form['categories_wrapper'][$c] = [
        '#type' => 'details',
        '#title' => $cat_has_title ? $cat_data['title'] : $this->t('Category @num', ['@num' => $c + 1]),
        '#open' => TRUE,
        '#attributes' => ['id' => 'faq-category-wrapper-' . $c],
      ];
      
      $form['categories_wrapper'][$c]['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Category Title'),
        '#default_value' => $cat_data['title'] ?? '',
      ];

      $form['categories_wrapper'][$c]['items'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'faq-items-wrapper-' . $c],
      ];

      for ($i = 0; $i < $num_items; $i++) {
        $item = $cat_data['items'][$i] ?? [];
        $item_has_q = !empty($item['question']);
        
        $form['categories_wrapper'][$c]['items'][$i] = [
          '#type' => 'details',
          '#title' => $item_has_q ? $item['question'] : $this->t('FAQ @num', ['@num' => $i + 1]),
          '#open' => $item_has_q || $i === ($num_items - 1),
        ];
        
        $form['categories_wrapper'][$c]['items'][$i]['question'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Question'),
          '#default_value' => $item['question'] ?? '',
        ];
        
        $answer_val = $item['answer'] ?? '';
        $form['categories_wrapper'][$c]['items'][$i]['answer'] = [
          '#type' => 'text_format',
          '#title' => $this->t('Answer'),
          '#format' => 'full_html',
          '#allowed_formats' => ['full_html'],
          '#default_value' => is_array($answer_val) ? $answer_val['value'] : $answer_val,
        ];
      }

      $form['categories_wrapper'][$c]['add_item'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add another FAQ to this category'),
        '#name' => 'add_item_' . $c,
        '#category_index' => $c, // Add a custom property to track which button was clicked
        '#submit' => ['::addItemSubmit'],
        '#ajax' => [
          'callback' => '::addItemCallback',
          'wrapper' => 'faq-items-wrapper-' . $c,
        ],
        '#limit_validation_errors' => [],
      ];
    }

    $form['add_category'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another Category'),
      '#submit' => ['::addCategorySubmit'],
      '#ajax' => [
        'callback' => '::addCategoryCallback',
        'wrapper' => 'faq-categories-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    // Important for forms with #tree = TRUE. 
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Submit handler for the "Add another Category" button.
   */
  public function addCategorySubmit(array &$form, FormStateInterface $form_state): void {
    $num_categories = $form_state->get('num_categories');
    $form_state->set('num_categories', $num_categories + 1);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for "Add another Category".
   */
  public function addCategoryCallback(array &$form, FormStateInterface $form_state): array {
    return $form['categories_wrapper'];
  }

  /**
   * Submit handler for the "Add another FAQ" button.
   */
  public function addItemSubmit(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $cat_index = $triggering_element['#category_index'];
    
    $num_items = $form_state->get(['num_items', $cat_index]);
    $form_state->set(['num_items', $cat_index], $num_items + 1);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for "Add another FAQ".
   */
  public function addItemCallback(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $cat_index = $triggering_element['#category_index'];
    return $form['categories_wrapper'][$cat_index]['items'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $raw_categories = $form_state->getValue('categories_wrapper') ?: [];
    
    $clean_categories = [];
    
    foreach ($raw_categories as $cat) {
      if (empty(trim($cat['title']))) {
        continue;
      }
      
      $clean_items = [];
      if (!empty($cat['items'])) {
        foreach ($cat['items'] as $item) {
          if (!empty(trim($item['question']))) {
            $clean_items[] = [
              'question' => $item['question'],
              // Extract the text format value correctly.
              'answer' => (isset($item['answer']['value'])) ? $item['answer']['value'] : $item['answer'],
            ];
          }
        }
      }
      
      $clean_categories[] = [
        'title' => trim($cat['title']),
        'items' => $clean_items,
      ];
    }

    $this->config('rte_mis_home.faq_page_settings')
      ->set('title', $form_state->getValue('title'))
      ->set('categories', $clean_categories)
      ->clear('faq_items') // Wipe the old flat array data to prevent cruft.
      ->save();

    parent::submitForm($form, $form_state);
  }

}
