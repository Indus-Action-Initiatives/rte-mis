<?php

namespace Drupal\rte_mis_mail\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;

/**
 * Provides a form to configure Mobile Number settings.
 */
class RteMisMailConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'rte_mis_mail.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rte_mis_mail_setting_config_form';
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

    $form['email_verification'] = [
      '#type' => 'details',
      '#title' => $this->t('Email Verification'),
      '#open' => TRUE,
    ];
    $form['email_verification']['enabled_email_verification'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Email Verification'),
      '#default_value' => $config->get('email_verification.enabled_email_verification') ?? FALSE,
      '#description' => $this->t('Enabled email verification on user registration.'),
    ];
    $form['email_verification']['email_verification_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email Verification Subject'),
      '#default_value' => $config->get('email_verification.email_verification_subject') ?? '',
      '#description' => $this->t('Enter the subject that will sent on the verification mail.'),
      '#states' => [
        'visible' => [
          ':input[name="enabled_email_verification"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="enabled_email_verification"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['email_verification']['email_verification_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Email Verification Message'),
      '#default_value' => $config->get('email_verification.email_verification_message') ?? '',
      '#description' => $this->t('The message to send during verification mail. Replacement parameters for verification code is !code.'),
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
      '#states' => [
        'visible' => [
          ':input[name="enabled_email_verification"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="enabled_email_verification"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['mobile_number_verification'] = [
      '#type' => 'details',
      '#title' => $this->t('Mobile Number Verification'),
      '#open' => TRUE,
    ];
    $form['mobile_number_verification']['enable_mobile_number_verification'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Mobile Number Verification'),
      '#default_value' => $config->get('mobile_number_verification.enable_mobile_number_verification') ?? FALSE,
      '#description' => $this->t('Verification requirement.'),
    ];
    $form['mobile_number_verification']['mobile_number_verification_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('SMS Verification Message'),
      '#default_value' => $config->get('mobile_number_verification.mobile_number_verification_message') ?? '',
      '#description' => $this->t('The SMS message to send during verification. Replacement parameters for verification code is !code.'),
      '#states' => [
        'visible' => [
          ':input[name="enable_mobile_number_verification"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="enable_mobile_number_verification"]' => ['checked' => TRUE],
        ],
      ],
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
    ];

    $form['school_notification_email'] = [
      '#type' => 'details',
      '#title' => $this->t('School Notification Email'),
      '#open' => TRUE,
    ];
    $form['school_notification_email']['enabled_email_notification'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('School Status Notification'),
      '#default_value' => $config->get('school_notification_email.enabled_email_notification') ?? FALSE,
      '#description' => $this->t('Enabled email notification on school verification.'),
    ];
    $form['school_notification_email']['email_notification_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email Notification Subject'),
      '#default_value' => $config->get('school_notification_email.email_notification_subject') ?? '',
      '#description' => $this->t('Enter the subject that will sent on the verification mail.'),
      '#states' => [
        'visible' => [
          ':input[name="enabled_email_notification"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="enabled_email_notification"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['school_notification_email']['email_notification_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Email Notification Message'),
      '#default_value' => $config->get('school_notification_email.email_notification_message') ?? '',
      '#description' => $this->t('The message send when the school state changs. Replacement parameters are !user for user, !existing_state for the existing state, !modified_state for the modified state.'),
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
      '#states' => [
        'visible' => [
          ':input[name="enabled_email_notification"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="enabled_email_notification"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['mobile_number_notification'] = [
      '#type' => 'details',
      '#title' => $this->t('School Notification SMS'),
      '#open' => TRUE,
    ];
    $form['mobile_number_notification']['enable_mobile_number_notification'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Mobile Number Verification'),
      '#default_value' => $config->get('mobile_number_notification.enable_mobile_number_notification') ?? FALSE,
      '#description' => $this->t('Notification requirement.'),
    ];
    $form['mobile_number_notification']['mobile_number_notification_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('SMS Notification Message'),
      '#default_value' => $config->get('mobile_number_notification.mobile_number_notification_message') ?? '',
      '#description' => $this->t('The SMS send when the school state changs. Replacement parameters are !user for user, !existing_state for the existing state, !modified_state for the modified state.'),
      '#states' => [
        'visible' => [
          ':input[name="enable_mobile_number_notification"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="enable_mobile_number_notification"]' => ['checked' => TRUE],
        ],
      ],
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
    ];

    $form['#attached']['library'][] = 'maxlength/maxlength';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if ($values['enabled_email_verification']) {
      if (empty($values['email_verification_subject'])) {
        $form_state->setErrorByName('email_verification_subject', $this->t('Email verification subject is required.'));
      }
      if (empty($values['email_verification_message'])) {
        $form_state->setErrorByName('email_verification_message', $this->t('Email verification message is required.'));
      }
    }

    if ($values['enable_mobile_number_verification'] && empty($values['mobile_number_verification_message'])) {
      $form_state->setErrorByName('mobile_number_verification_message', $this->t('Phone verification message is required.'));
    }

    if ($values['enabled_email_notification']) {
      if (empty($values['email_notification_subject'])) {
        $form_state->setErrorByName('email_notification_subject', $this->t('Email notification subject is required.'));
      }
      if (empty($values['email_notification_message'])) {
        $form_state->setErrorByName('email_notification_message', $this->t('Email notification message is required.'));
      }
    }

    if ($values['enable_mobile_number_notification'] && empty($values['mobile_number_notification_message'])) {
      $form_state->setErrorByName('mobile_number_notification_message', $this->t('Phone notification message is required.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->configFactory->getEditable($this::SETTINGS)
      ->set('email_verification.enabled_email_verification', $values['enabled_email_verification'] ?? FALSE)
      ->set('email_verification.email_verification_subject', $values['email_verification_subject'] ?? '')
      ->set('email_verification.email_verification_message', $values['email_verification_message'] ?? '')
      ->set('mobile_number_verification.enable_mobile_number_verification', $values['enable_mobile_number_verification'] ?? FALSE)
      ->set('mobile_number_verification.mobile_number_verification_message', $values['mobile_number_verification_message'] ?? '')
      ->set('school_notification_email.enabled_email_notification', $values['enabled_email_notification'] ?? FALSE)
      ->set('school_notification_email.email_notification_subject', $values['email_notification_subject'] ?? '')
      ->set('school_notification_email.email_notification_message', $values['email_notification_message'] ?? '')
      ->set('mobile_number_notification.enable_mobile_number_notification', $values['enable_mobile_number_notification'] ?? FALSE)
      ->set('mobile_number_notification.mobile_number_notification_message', $values['mobile_number_notification_message'] ?? '')
      ->save();

    // Load the existing field storage configuration.
    $fieldPhoneNumberConfig = FieldConfig::loadByName('user', 'user', 'field_phone_number');
    if ($fieldPhoneNumberConfig instanceof FieldConfig) {
      // Get the current settings.
      $settings = $fieldPhoneNumberConfig->getSettings();
      $settings['verify'] = $values['enable_mobile_number_verification'] ? 'required' : 'none';
      $settings['message'] = $values['mobile_number_verification_message'];
      // Set the updated settings.
      $fieldPhoneNumberConfig->setSettings($settings);
      // Save the field configuration.
      $fieldPhoneNumberConfig->save();
    }
    parent::submitForm($form, $form_state);
  }

}
