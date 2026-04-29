<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure School Search Block settings.
 */
final class SchoolSearchSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'rte_mis_home_school_search_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['rte_mis_home.school_search_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('rte_mis_home.school_search_settings');

    $form['title_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title Prefix'),
      '#description' => $this->t('Light-weight text before the bold portion (e.g. "Search by").'),
      '#default_value' => $config->get('title_prefix') ?: 'Search by',
    ];

    $form['title_highlight'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title Highlight'),
      '#description' => $this->t('Bold/highlighted portion of the title.'),
      '#default_value' => $config->get('title_highlight') ?: 'School Name, PIN Code or Location',
    ];

    $form['search_placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Placeholder'),
      '#default_value' => $config->get('search_placeholder') ?: 'Find Schools',
    ];

    $form['search_action'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Action URL'),
      '#description' => $this->t('The form action URL for the search (e.g. /school-search).'),
      '#default_value' => $config->get('search_action') ?: '/school-search',
    ];

    $form['show_filters'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Filter Columns'),
      '#description' => $this->t('If unchecked, the filter columns (Dropdowns) will be hidden.'),
      '#default_value' => $config->get('show_filters') ?? TRUE,
    ];

    // ── Filter Columns ──
    $filters = $config->get('filters') ?: [];

    $form['filters'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Filter Columns'),
      '#tree' => TRUE,
      '#description' => $this->t('Configure up to 5 filter columns. Leave label empty to skip.'),
    ];

    for ($i = 0; $i < 5; $i++) {
      $filter = $filters[$i] ?? [];
      $form['filters'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('Filter @num', ['@num' => $i + 1]),
        '#open' => $i < 3 || !empty($filter['label']),
      ];

      $form['filters'][$i]['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Column Label'),
        '#default_value' => $filter['label'] ?? '',
        '#description' => $this->t('e.g. "Select School", "Select PIN", "Location".'),
      ];

      $form['filters'][$i]['placeholder'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Dropdown Placeholder'),
        '#default_value' => $filter['placeholder'] ?? '',
        '#description' => $this->t('e.g. "School Name", "Select PIN code".'),
      ];

      $form['filters'][$i]['name'] = [
        '#type' => 'machine_name',
        '#title' => $this->t('Field Name'),
        '#default_value' => $filter['name'] ?? '',
        '#description' => $this->t('Machine name for the form field.'),
        '#machine_name' => [
          'exists' => [$this, 'filterNameExists'],
        ],
        '#required' => FALSE,
      ];

      // Options textarea (one per line, value|label format).
      $options_text = '';
      if (!empty($filter['options'])) {
        $lines = [];
        foreach ($filter['options'] as $option) {
          $lines[] = ($option['value'] ?? $option['label']) . '|' . ($option['label'] ?? $option['value']);
        }
        $options_text = implode("\n", $lines);
      }

      $form['filters'][$i]['options_text'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Dropdown Options'),
        '#default_value' => $options_text,
        '#description' => $this->t('One option per line in <code>value|label</code> format. Example:<br><code>411001|411001</code><br><code>amritsar|Amritsar</code>'),
        '#rows' => 4,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Machine name existence callback — always returns FALSE since duplicates
   * across filters are acceptable.
   */
  public function filterNameExists($value): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $raw_filters = $form_state->getValue('filters') ?: [];
    $filters = [];

    foreach ($raw_filters as $filter) {
      if (empty(trim($filter['label'] ?? ''))) {
        continue;
      }

      // Parse options_text into structured array.
      $options = [];
      if (!empty(trim($filter['options_text'] ?? ''))) {
        $lines = preg_split('/\r\n|\n|\r/', trim($filter['options_text']));
        foreach ($lines as $line) {
          $line = trim($line);
          if (empty($line)) {
            continue;
          }
          if (str_contains($line, '|')) {
            [$value, $label] = explode('|', $line, 2);
          }
          else {
            $value = $label = $line;
          }
          $options[] = [
            'value' => trim($value),
            'label' => trim($label),
          ];
        }
      }

      $filters[] = [
        'label' => $filter['label'],
        'placeholder' => $filter['placeholder'] ?? 'Select...',
        'name' => $filter['name'] ?? strtolower(str_replace(' ', '_', $filter['label'])),
        'options' => $options,
      ];
    }

    $this->config('rte_mis_home.school_search_settings')
      ->set('title_prefix', $form_state->getValue('title_prefix'))
      ->set('title_highlight', $form_state->getValue('title_highlight'))
      ->set('search_placeholder', $form_state->getValue('search_placeholder'))
      ->set('search_action', $form_state->getValue('search_action'))
      ->set('show_filters', $form_state->getValue('show_filters'))
      ->set('filters', $filters)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
