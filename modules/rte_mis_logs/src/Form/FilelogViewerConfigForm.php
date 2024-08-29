<?php

namespace Drupal\rte_mis_logs\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure FileLog Viewer settings for this site.
 */
final class FilelogViewerConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'rte_mis_logs_rte_mis_logs_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['rte_mis_logs.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Input field for selecting the log rotation in file based on log lines.
    $form['msg_size'] = [
      '#title' => $this->t('Database log messages to keep'),
      '#type' => 'select',
      '#description' => $this->t('The maximum number of messages to keep in the database log.
      All your search, filter & sort operations will take place within this messages'),
      '#options' => [
        '100' => 100,
        '1000' => 1000,
        '10000' => 10000,
      ],
      '#default_value' => $this->config('rte_mis_logs.settings')->get('msg_size'),
    ];

    // Check box for selecting which columns to display on the log table.
    $form['log_columns'] = [
      '#title' => $this->t('Select log columns header'),
      '#type' => 'checkboxes',
      '#description' => $this->t('Check this box to enable the column to be present on the log table.'),
      '#options' => [
        'created' => $this->t('Created'),
        'user' => $this->t('User'),
        'channel' => $this->t('Channel'),
        'ip' => $this->t('IP'),
        'level' => $this->t('Level'),
        'message' => $this->t('Message'),
        'location' => $this->t('Location'),
        'referrer' => $this->t('Referrer'),
        'uid' => $this->t('UID'),
        'mail' => $this->t('Mail'),

      ],
      '#element_validate' => [
        [$this, 'validateLogColumns'],
      ],
      '#default_value' => array_keys(array_filter($this->config('rte_mis_logs.settings')->get('log_columns'))),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * Form element validation, to ensure at least one checkbox is checked.
   */
  public function validateLogColumns(array &$element, FormStateInterface $form_state) {
    $values = $form_state->getValue($element['#parents']);
    if (empty(array_filter($values))) {
      $form_state->setError($element, $this->t('At least one checkbox must be selected.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('rte_mis_logs.settings')
      ->set('msg_size', $form_state->getValue('msg_size'))
      ->save();
    $log_columns = array_fill_keys($form_state->getValue('log_columns'), TRUE);
    $this->config('rte_mis_logs.settings')
      ->set('log_columns', $log_columns)
      ->save();
    parent::submitForm($form, $form_state);
  }

}
