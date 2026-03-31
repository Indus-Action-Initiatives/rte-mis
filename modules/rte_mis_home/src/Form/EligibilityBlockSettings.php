<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure rte_mis_home eligibility block settings.
 */
final class EligibilityBlockSettings extends ConfigFormBase
{
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'rte_mis_home_eligibility_block_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array
  {
    return ['rte_mis_home.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $config = $this->config('rte_mis_home.settings');

    $form['show_class_age'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Class & Age column'),
      '#default_value' => $config->get('show_class_age') ?? TRUE,
    ];

    // --- Eligibility Items ---
    $eligibility = $config->get('eligibility_items') ?: [];
    $num_eligibility = $form_state->get('num_eligibility');
    if ($num_eligibility === NULL) {
      $num_eligibility = max(1, count($eligibility));
      $form_state->set('num_eligibility', $num_eligibility);
    }

    $form['eligibility_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'eligibility-wrapper'],
    ];

    $form['eligibility_wrapper']['eligibility'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Disadvantaged group/Economically Weaker Section'),
      '#tree' => TRUE,
    ];

    for ($i = 0; $i < $num_eligibility; $i++) {
      $form['eligibility_wrapper']['eligibility'][$i] = [
        '#type' => 'textfield',
        '#title' => $this->t('Item @num', ['@num' => $i + 1]),
        '#default_value' => $eligibility[$i] ?? '',
      ];
    }

    $form['eligibility_wrapper']['add_eligibility'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another eligibility item'),
      '#submit' => ['::addOneSubmit'],
      '#ajax' => [
        'callback' => '::addOneCallback',
        'wrapper' => 'eligibility-wrapper',
      ],
      '#name' => 'add_eligibility',
      '#limit_validation_errors' => [],
    ];

    // --- Priority Items ---
    $priorities = $config->get('priority_items') ?: [];
    $num_priorities = $form_state->get('num_priorities');
    if ($num_priorities === NULL) {
      $num_priorities = max(1, count($priorities));
      $form_state->set('num_priorities', $num_priorities);
    }

    $form['priorities_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'priorities-wrapper'],
    ];

    $form['priorities_wrapper']['priorities'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('School Allotment Priorities'),
      '#tree' => TRUE,
    ];

    for ($i = 0; $i < $num_priorities; $i++) {
      $form['priorities_wrapper']['priorities'][$i] = [
        '#type' => 'textfield',
        '#title' => $this->t('Priority @num', ['@num' => $i + 1]),
        '#default_value' => $priorities[$i] ?? '',
      ];
    }

    $form['priorities_wrapper']['add_priority'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another priority item'),
      '#submit' => ['::addOneSubmit'],
      '#ajax' => [
        'callback' => '::addOneCallback',
        'wrapper' => 'priorities-wrapper',
      ],
      '#name' => 'add_priority',
      '#limit_validation_errors' => [],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Submit handler for the "Add another" buttons.
   */
  public function addOneSubmit(array &$form, FormStateInterface $form_state)
  {
    $trigger = $form_state->getTriggeringElement();
    if ($trigger['#name'] === 'add_eligibility') {
      $num = $form_state->get('num_eligibility') + 1;
      $form_state->set('num_eligibility', $num);
    }
    elseif ($trigger['#name'] === 'add_priority') {
      $num = $form_state->get('num_priorities') + 1;
      $form_state->set('num_priorities', $num);
    }
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for the "Add another" buttons.
   */
  public function addOneCallback(array &$form, FormStateInterface $form_state)
  {
    $trigger = $form_state->getTriggeringElement();
    if ($trigger['#name'] === 'add_eligibility') {
      return $form['eligibility_wrapper'];
    }
    elseif ($trigger['#name'] === 'add_priority') {
      return $form['priorities_wrapper'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    $config = $this->configFactory->getEditable('rte_mis_home.settings');

    $eligibility = array_filter(array_map('trim', $form_state->getValue('eligibility')));
    $priorities = array_filter(array_map('trim', $form_state->getValue('priorities')));

    $config
      ->set('show_class_age', (bool) $form_state->getValue('show_class_age'))
      ->set('eligibility_items', array_values($eligibility))
      ->set('priority_items', array_values($priorities))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
