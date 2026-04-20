<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Application Process settings with dynamic Add More steps and guidelines.
 */
class ApplicationProcessSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'rte_mis_home_application_process_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['rte_mis_home.application_process_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('rte_mis_home.application_process_settings');
    
    // Default 6 steps if empty
    $steps = $config->get('steps') ?: [];
    if (empty($steps)) {
      $steps = [
        ['icon' => 'register', 'title' => 'Register', 'description' => 'Create account with mobile number'],
        ['icon' => 'document', 'title' => 'Fill Details', 'description' => 'Enter student information'],
        ['icon' => 'upload', 'title' => 'Upload Documents', 'description' => 'Submit required documents'],
        ['icon' => 'school', 'title' => 'Select Schools', 'description' => 'Choose up to 5 schools'],
        ['icon' => 'submit', 'title' => 'Submit', 'description' => 'Review and submit application'],
        ['icon' => 'track', 'title' => 'Track Status', 'description' => 'Monitor application progress'],
      ];
    }
    
    $guidelines_items = $config->get('guidelines_items') ?: [];
    if (empty($guidelines_items)) {
      $guidelines_items = [
        ['text' => 'Keep documents in PDF/JPG format (max 2MB each)'],
        ['text' => 'Applications can be saved as draft and completed later'],
        ['text' => 'SMS and email notifications at each stage'],
        ['text' => 'Transparent lottery system for fair seat allocation'],
      ];
    }

    if ($form_state->get('num_steps') === NULL) {
      $form_state->set('num_steps', count($steps) ?: 1);
    }
    if ($form_state->get('num_guidelines') === NULL) {
      $form_state->set('num_guidelines', count($guidelines_items) ?: 1);
    }

    $form['#tree'] = TRUE;

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Header Title'),
      '#default_value' => $config->get('title') ?: 'Application Process',
      '#required' => TRUE,
    ];

    $form['subtitle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Header Subtitle'),
      '#default_value' => $config->get('subtitle') ?: 'Complete your RTE admission in 6 simple steps',
    ];

    $form['steps'] = [
      '#type' => 'details',
      '#title' => $this->t('Timeline Steps'),
      '#open' => TRUE,
      '#attributes' => ['id' => 'steps-wrapper'],
    ];

    $num_steps = $form_state->get('num_steps');

    for ($i = 0; $i < $num_steps; $i++) {
      $step_data = $steps[$i] ?? [];

      $form['steps'][$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Step @num', ['@num' => $i + 1]),
      ];
      $form['steps'][$i]['icon'] = [
        '#type' => 'select',
        '#title' => $this->t('Icon'),
        '#options' => [
          'register' => $this->t('Register (Person + Plus)'),
          'document' => $this->t('Document (File)'),
          'upload' => $this->t('Upload (File Upload)'),
          'school' => $this->t('School (Building)'),
          'submit' => $this->t('Submit (Paper Plane)'),
          'track' => $this->t('Track Status (Bell)'),
        ],
        '#default_value' => $step_data['icon'] ?? 'register',
        '#description' => $this->t('Select the predefined icon to use for this step.'),
      ];
      $form['steps'][$i]['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title'),
        '#default_value' => $step_data['title'] ?? '',
      ];
      $form['steps'][$i]['description'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Description'),
        '#default_value' => $step_data['description'] ?? '',
      ];
    }

    $form['add_step'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another Step'),
      '#submit' => ['::addStepSubmit'],
      '#ajax' => [
        'callback' => '::addStepCallback',
        'wrapper' => 'steps-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    $form['guidelines'] = [
      '#type' => 'details',
      '#title' => $this->t('Important Guidelines Section'),
      '#open' => TRUE,
    ];

    $form['guidelines']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Guidelines Title'),
      '#default_value' => $config->get('guidelines_title') ?: 'Important Guidelines',
    ];

    $form['guidelines']['items'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Guidelines Items'),
      '#attributes' => ['id' => 'guidelines-wrapper'],
    ];

    $num_guidelines = $form_state->get('num_guidelines');

    for ($i = 0; $i < $num_guidelines; $i++) {
      $item_data = $guidelines_items[$i] ?? [];
      
      $form['guidelines']['items'][$i]['text'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Item @num Text', ['@num' => $i + 1]),
        '#default_value' => $item_data['text'] ?? '',
      ];
    }

    $form['add_guideline_item'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another Guideline Item'),
      '#submit' => ['::addGuidelineSubmit'],
      '#ajax' => [
        'callback' => '::addGuidelineCallback',
        'wrapper' => 'guidelines-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    $form['guidelines']['button_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Call-to-Action Button Text'),
      '#default_value' => $config->get('button_text') ?: 'Apply Now',
    ];
    
    $form['guidelines']['button_link'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Call-to-Action Button Link'),
      '#default_value' => $config->get('button_link') ?: '#',
    ];

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

  public function addStepSubmit(array &$form, FormStateInterface $form_state): void {
    $num = $form_state->get('num_steps');
    $form_state->set('num_steps', $num + 1);
    $form_state->setRebuild();
  }

  public function addStepCallback(array &$form, FormStateInterface $form_state): array {
    return $form['steps'];
  }

  public function addGuidelineSubmit(array &$form, FormStateInterface $form_state): void {
    $num = $form_state->get('num_guidelines');
    $form_state->set('num_guidelines', $num + 1);
    $form_state->setRebuild();
  }

  public function addGuidelineCallback(array &$form, FormStateInterface $form_state): array {
    return $form['guidelines']['items'];
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $raw_steps = $form_state->getValue('steps') ?: [];
    $clean_steps = [];
    foreach ($raw_steps as $key => $step) {
      if (is_numeric($key) && !empty(trim($step['title']))) {
        $clean_steps[] = [
          'icon' => trim($step['icon']),
          'title' => trim($step['title']),
          'description' => trim($step['description']),
        ];
      }
    }

    $raw_guidelines = $form_state->getValue(['guidelines', 'items']) ?: [];
    $clean_guidelines = [];
    foreach ($raw_guidelines as $key => $item) {
      if (is_numeric($key) && !empty(trim($item['text']))) {
        $clean_guidelines[] = [
          'text' => trim($item['text']),
        ];
      }
    }

    $this->config('rte_mis_home.application_process_settings')
      ->set('title', $form_state->getValue('title'))
      ->set('subtitle', $form_state->getValue('subtitle'))
      ->set('steps', $clean_steps)
      ->set('guidelines_title', $form_state->getValue(['guidelines', 'title']))
      ->set('guidelines_items', $clean_guidelines)
      ->set('button_text', $form_state->getValue(['guidelines', 'button_text']))
      ->set('button_link', $form_state->getValue(['guidelines', 'button_link']))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
