<?php

namespace Drupal\rte_mis_smsgateway_msg91\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure MSG91 Custom Gateway settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The MSG91 service handler.
   *
   * @var \Drupal\rte_mis_smsgateway_msg91\Service\MSG91SMSService
   */
  protected $msg91Service;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->msg91Service = $container->get('rte_mis_smsgateway_msg91.msg91_service');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['rte_mis_smsgateway_msg91.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rte_mis_smsgateway_msg91_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('rte_mis_smsgateway_msg91.settings');

    $form['auth_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('MSG91 Auth URL'),
      '#default_value' => $config->get('auth_url') ?: 'https://api.msg91.com/api/v5/flow/',
      '#required' => TRUE,
    ];

    $form['auth_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('MSG91 Authentication ID'),
      '#default_value' => $config->get('auth_key'),
      '#required' => TRUE,
    ];

    $form['template_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('MSG91 Template ID'),
      '#default_value' => $config->get('template_id'),
    ];

    $form['country_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Country Code'),
      '#default_value' => $config->get('country_code') ?: '91',
    ];

    // --- Test SMS Section ---
    $form['test_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Test Mobile Number'),
      '#description' => $this->t('Enter a valid mobile number with country code (e.g. 919876543210).'),
    ];

    $form['test_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Test Message'),
      '#default_value' => 'Test message from Drupal MSG91 Custom Gateway',
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Configuration'),
      '#button_type' => 'primary',
    ];

    $form['actions']['test_sms'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send Test SMS'),
      '#submit' => ['::sendTestSms'],
    ];

    return $form;
  }

  /**
   * Save configuration form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('rte_mis_smsgateway_msg91.settings')
      ->set('auth_url', $form_state->getValue('auth_url'))
      ->set('auth_key', $form_state->getValue('auth_key'))
      ->set('template_id', $form_state->getValue('template_id'))
      ->set('country_code', $form_state->getValue('country_code'))
      ->save();

    $this->messenger()->addStatus($this->t('MSG91 configuration saved successfully.'));
    parent::submitForm($form, $form_state);
  }

  /**
   * Custom handler to send a test SMS.
   */
  public function sendTestSms(array &$form, FormStateInterface $form_state) {
    $mobile = trim($form_state->getValue('test_number'));
    $message = trim($form_state->getValue('test_message'));

    if (empty($mobile)) {
      $this->messenger()->addError($this->t('Please enter a valid mobile number.'));
      return;
    }

    $response = $this->msg91Service->sendMessage(
      $mobile,
      $message,
      '',
      [
        'STUDENT_NAME' => 'TestName',
        'STATE' => 'Approved',
        '{APPLICATION_ID}' => 'TEST12345',
      ]);

    if ($response && isset($response['type']) && $response['type'] === 'success') {
      $this->messenger()->addStatus($this->t('Test SMS sent successfully.'));
    }
    else {
      $this->messenger()->addError($this->t('Failed to send Test SMS. Check logs for details.'));
    }
  }

}
