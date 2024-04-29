<?php

namespace Drupal\rte_mis_student\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a configuration form for rte_mis_student.
 */
class RteMisStudentConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'rte_mis_student.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rte_mis_student_setting_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      $this::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config($this::SETTINGS);

    $form['student_login'] = [
      '#type' => 'details',
      '#title' => $this->t('Student Login'),
      '#open' => TRUE,
    ];

    $form['student_login']['mobile_otp_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message for student login(OTP)'),
      '#default_value' => $config->get('student_login.mobile_otp_message') ?? '',
      '#description' => $this->t('The message to send during student login. Replacement parameters for verification code is !code.'),
      '#attributes' => [
        'data-maxlength' => 200,
        'class' => [
          'maxlength',
        ],
        'maxlength_js_label' => [
          $this->t('Content limit is up to @limit characters, remaining: <strong>@remaining</strong>'),
        ],
        '#maxlength_js_enforce' => TRUE,
      ],
      '#required' => TRUE,
    ];
    $form['#attached']['library'][] = 'maxlength/maxlength';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (empty($values['mobile_otp_message'])) {
      $form_state->setErrorByName('mobile_otp_message', $this->t('Message is required.'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->configFactory->getEditable($this::SETTINGS)
      ->set('student_login.mobile_otp_message', $values['mobile_otp_message'])
      ->save();
    parent::submitForm($form, $form_state);
  }

}
