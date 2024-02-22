<?php

namespace Drupal\rte_mis_mail\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;

/**
 * Provides a form to configure Mobile Number settings.
 */
class EmailSmsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'email_sms_config_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['email_sms_form.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('email_sms_form.settings');

    $form['email_verify'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Email Verification'),
      '#default_value' => $config->get('email_verify'),
      '#description' => $this->t('Verification requirement.'),
    ];

    $form['email_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Email Verification Message'),
      '#default_value' => $config->get('email_message'),
      '#description' => $this->t('The email to send during verification. Replacement parameters for verification code is !code.'),
      '#states' => [
        'visible' => [
          ':input[name="email_verify"]' =>
        ['checked' => TRUE],
        ],
      ],
    ];

    $form['sms_verify'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('SMS Verification'),
      '#default_value' => $config->get('sms_verify'),
      '#description' => $this->t('Verification requirement.'),
    ];
    $form['sms_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('SMS Verification Message'),
      '#default_value' => $config->get('sms_message'),
      '#description' => $this->t('The SMS message to send during verification.Replacement parameters for verification code is !code.'),
      '#states' => [
        'visible' => [
          ':input[name="sms_verify"]' =>
        ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable('email_sms_form.settings')
      ->set('email_verify', $form_state->getValue('email_verify'))
      ->set('email_message', $form_state->getValue('email_message'))
      ->set('sms_verify', $form_state->getValue('sms_verify'))
      ->set('sms_message', $form_state->getValue('sms_message'))
      ->save();

    // Load the existing field storage configuration.
    $field_config = FieldConfig::loadByName('user', 'user', 'field_phone_number');
    if ($field_config) {
      // Get the current settings.
      $settings = $field_config->getSettings();

      $settings['verify'] = $form_state->getValue('sms_verify') ? "required" : "none";
      if ($form_state->getValue('sms_message')) {
        $settings['message'] = $form_state->getValue('sms_message');
      }

      // Set the updated settings.
      $field_config->setSettings($settings);

      // Save the field configuration.
      $field_config->save();
      parent::submitForm($form, $form_state);
    }
  }

}
