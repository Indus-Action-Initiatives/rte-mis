<?php

namespace Drupal\rte_mis_mail\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;

/**
 * Provides a form to configure Mobile Number settings.
 */
class RteMisSmsConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'rte_mis_mail.sms_settings';

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

    $form['student_login'] = [
      '#type' => 'details',
      '#title' => $this->t('Student Login'),
      '#open' => TRUE,
    ];

    $form['student_login']['enable_student_mobile_verification'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Student Mobile Number Verification'),
      '#default_value' => $config->get('student_login.enable_student_mobile_verification') ?? FALSE,
      '#description' => $this->t('Verification requirement.'),
    ];

    // --- Template ID Field for OTP ---
    $form['student_login']['template_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('MSG91 Template ID for OTP'),
      '#default_value' => $config->get('student_login.template_id') ?? '',
      '#description' => $this->t('<p>Enter the approved MSG91 template ID used for sending OTP messages.</p>
        <p><strong>Available replacement pattern:</strong></p>
        <ul>
          <li><code>{OTP}</code> – The OTP code sent to the user.</li>
        </ul>'),
      '#states' => [
        'visible' => [
          ':input[name="enable_student_mobile_verification"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="enable_student_mobile_verification"]' => ['checked' => TRUE],
        ],
      ],
      '#attributes' => [
        'data-maxlength' => 30,
        'class' => [
          'maxlength',
        ],
        'maxlength_js_label' => [
          $this->t(
            'Content limit is up to @limit characters, remaining: <strong>@remaining</strong>'
          ),
        ],
        '#maxlength_js_enforce' => TRUE,
      ],
      '#required' => TRUE,
    ];

    // User mobile number verification.
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
    // --- Template ID Field for OTP ---
    $form['mobile_number_verification']['mobile_number_verification_message_template_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('MSG91 Template ID for OTP'),
      '#default_value' => $config->get('mobile_number_verification.mobile_number_verification_message_template_id') ?? '',
      '#description' => $this->t('<p>Enter the approved MSG91 template ID used for sending OTP messages.</p>
        <p><strong>Available replacement pattern:</strong></p>
        <ul>
          <li><code>{OTP}</code> – The OTP code sent to the user.</li>
        </ul>'),
      '#states' => [
        'visible' => [
          ':input[name="enable_mobile_number_verification"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="enable_mobile_number_verification"]' => ['checked' => TRUE],
        ],
      ],
      '#attributes' => [
        'data-maxlength' => 30,
        'class' => [
          'maxlength',
        ],
        'maxlength_js_label' => [
          $this->t(
            'Content limit is up to @limit characters, remaining: <strong>@remaining</strong>'
          ),
        ],
        '#maxlength_js_enforce' => TRUE,
      ],
      '#required' => TRUE,
    ];
    $form['mobile_number_verification']['mobile_number_verification_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('SMS Verification Message'),
      '#default_value' => $config->get('mobile_number_verification.mobile_number_verification_message') ?? '',
      '#description' => $this->t('<p>The SMS message to send during verification.</p><p><strong>Replacement pattern:</strong></p><ul><li><code>!code</code> - Verification code</li></ul>'),
      '#states' => [
        'visible' => [
          ':input[name="enable_mobile_number_verification"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="enable_mobile_number_verification"]' => ['checked' => TRUE],
        ],
      ],
      '#attributes' => [
        'data-maxlength' => 30,
        'class' => [
          'maxlength',
        ],
        'maxlength_js_label' => [
          $this->t('Content limit is up to @limit characters, remaining: <strong>@remaining</strong>'),
        ],
        '#maxlength_js_enforce' => TRUE,
      ],
    ];

    // School verification status sms.
    $form['mobile_number_notification'] = [
      '#type' => 'details',
      '#title' => $this->t('School Notification SMS'),
      '#open' => TRUE,
    ];
    $form['mobile_number_notification']['enable_mobile_number_notification'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable school verification notification by sms'),
      '#default_value' => $config->get('mobile_number_notification.enable_mobile_number_notification') ?? FALSE,
      '#description' => $this->t('Send the sms notification to school about the verification status.'),
    ];
    // Template ID field.
    $form['mobile_number_notification']['mobile_number_notification_template_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SMS Template ID'),
      '#default_value' => $config->get('mobile_number_notification.mobile_number_notification_template_id') ?? '',
      '#description' => [
        '#markup' => $this->t('Enter the SMS template ID provided by the SMS gateway.<br><br>
          <p><strong>Available MSG91 Variables:</strong></p>
          <ul>
            <li><code>{SCHOOL_USER}</code> – School User Name</li>
            <li><code>{STATE}</code> – Updated application status (e.g., Approved, Rejected, Pending)</li>
          </ul>
        '),
      ],
      '#states' => [
        'visible' => [
          ':input[name="enable_mobile_number_notification"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="enable_mobile_number_notification"]' => ['checked' => TRUE],
        ],
      ],
      '#attributes' => [
        'data-maxlength' => 30,
        'class' => [
          'maxlength',
        ],
        'maxlength_js_label' => [
          $this->t('Content limit is up to @limit characters, remaining: <strong>@remaining</strong>'),
        ],
        '#maxlength_js_enforce' => TRUE,
      ],
    ];

    // Student application SMS verification.
    $form['student_verification'] = [
      '#type' => 'details',
      '#title' => $this->t('Student Verification Notification'),
      '#open' => TRUE,
    ];

    $form['student_verification']['enable_student_verification_sms'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Student Verification / Application Confirmation Notification'),
      '#default_value' => $config->get('student_verification.enable_student_verification_sms') ?? FALSE,
      '#description' => $this->t('Send an SMS notification to the student when their application verification status changes.'),
    ];

    $form['student_verification']['student_verification_sms_template_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('MSG91 Template ID'),
      '#default_value' => $config->get('student_verification.student_verification_sms_template_id') ?? '',
      '#description' => $this->t('
        <p>This SMS notification will be sent to the student when their application verification status is updated.</p>

        <p><strong>Available MSG91 Variables:</strong></p>
        <ul>
          <li><code>{STUDENT_NAME}</code> – Name of the student</li>
          <li><code>{APPLICATION_ID}</code> – Unique application ID</li>
          <li><code>{STATE}</code> – Updated application status (e.g., Approved, Rejected, Pending)</li>
        </ul>

        <p><strong>Sample SMS Template:</strong><br>
        Dear {STUDENT_NAME}, your application (ID: {APPLICATION_ID}) status has been updated to {STATE}. Please check the portal for more details.</p>
      '),
      '#states' => [
        'visible' => [
          ':input[name="enable_student_verification_sms"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="enable_student_verification_sms"]' => ['checked' => TRUE],
        ],
      ],
      '#attributes' => [
        'data-maxlength' => 30,
        'class' => ['maxlength'],
      ],
      '#maxlength_js_label' => $this->t('Content limit is up to @limit characters, remaining: <strong>@remaining</strong>'),
      '#maxlength_js_enforce' => TRUE,
    ];

    // Student Admission Workflow SMS Notification.
    $form['student_admission'] = [
      '#type'  => 'details',
      '#title' => $this->t('Student Admission Workflow Notification'),
      '#open'  => TRUE,
    ];

    // Enable SMS.
    $form['student_admission']['enable_student_admission_sms'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable admission workflow notification by SMS'),
      '#default_value' => $config->get('student_admission.enable_student_admission_sms') ?? FALSE,
      '#description' => $this->t('Send an SMS to the student when their admission workflow state changes.'),
    ];

    // Template ID field.
    $form['student_admission']['student_admission_sms_template_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('MSG91 Template ID'),
      '#default_value' => $config->get('student_admission.student_admission_sms_template_id') ?? '',
      '#description' => $this->t('
        <p>An SMS will be sent via MSG91 when the student’s admission state changes.</p>

        <p><strong>Available Template Variables:</strong></p>
        <ul>
          <li><code>{STUDENT_NAME}</code> – Student name</li>
          <li><code>{SCHOOL_NAME}</code> – School name</li>
          <li><code>{STATE}</code> – State</li>
          <li><code>{APPLICATION_ID}</code> – Application ID</li>
        </ul>

        <p><strong>Example Template:</strong><br>
        Dear {STUDENT_NAME}, your admission status has changed to {STATE} for school {SCHOOL_NAME}.</p>
      '),
      '#states' => [
        'visible' => [
          ':input[name="enable_student_admission_sms"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="enable_student_admission_sms"]' => ['checked' => TRUE],
        ],
      ],
      '#attributes' => [
        'data-maxlength' => 30,
        'class' => ['maxlength'],
      ],
      '#maxlength_js_label'   => $this->t('Content limit is up to @limit characters, remaining: <strong>@remaining</strong>'),
      '#maxlength_js_enforce' => TRUE,
    ];

    // School reimbursement sms.
    $form['mobile_number_reimbursement_notification'] = [
      '#type' => 'details',
      '#title' => $this->t('School Reimbursement Notification SMS'),
      '#open' => TRUE,
    ];
    $form['mobile_number_reimbursement_notification']['enable_reimbursement_mobile_number_notification'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable school Reimbursement notification by sms'),
      '#default_value' => $config->get('mobile_number_reimbursement_notification.enable_reimbursement_mobile_number_notification') ?? FALSE,
      '#description' => $this->t('Send the sms notification to school about the reimbursement status.'),
    ];
    $form['mobile_number_reimbursement_notification']['reimbursement_template_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reimbursement SMS Template ID'),
      '#default_value' => $config->get('mobile_number_reimbursement_notification.reimbursement_template_id') ?? '',
      '#description' => $this->t('
        Enter the MSG91 Template ID used for sending the reimbursement notification.<br><br>
        <strong>Available template variables:</strong>
        <ul>
          <li><code>{SCHOOL_NAME}</code> — Name of the school</li>
          <li><code>{STATE}</code> — Updated reimbursement status</li>
          <li><code>{REIMBURSEMENT_AMOUNT}</code> — Total fees reimbursable</li>
          <li><code>{AMOUNT_RECEIVED}</code> — Amount received</li>
          <li><code>{ACADEMIC_SESSION}</code> — Academic session</li>
          <li><code>{PAYMENT_HEAD}</code> — Payment head (<strong>Central / State</strong>)</li>
        </ul>
      '),
      '#states' => [
        'visible' => [
          ':input[name="enable_reimbursement_mobile_number_notification"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="enable_reimbursement_mobile_number_notification"]' => ['checked' => TRUE],
        ],
      ],
      '#attributes' => [
        'maxlength' => 30,
        'class' => ['template-id'],
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

    // Validation for sms verification.
    if ($values['enable_student_mobile_verification'] && empty($values['template_id'])) {
      $form_state->setErrorByName('template_id', $this->t('Student phone verification TemplateID is required.'));
    }
    // Validation for sms verification.
    if ($values['enable_mobile_number_verification'] && empty($values['mobile_number_verification_message_template_id'])) {
      $form_state->setErrorByName('mobile_number_verification_message_template_id', $this->t('Phone number verification TemplateID is required.'));
    }

    // School verification status number validation.
    if ($values['enable_mobile_number_notification'] && empty($values['mobile_number_notification_template_id'])) {
      $form_state->setErrorByName('mobile_number_notification_template_id', $this->t('School verification status TemplateID is required.'));
    }

    // Student application verification validation.
    if ($values['enable_student_verification_sms'] && empty($values['student_verification_sms_template_id'])) {
      $form_state->setErrorByName('student_verification_sms_template_id', $this->t('Student application verification notification TemplateID is required.'));
    }

    // Student admission validation.
    if ($form_state->getValue('enable_student_admission_sms') && empty($values['student_admission_sms_template_id'])) {
      $form_state->setErrorByName('student_admission_sms_template_id', $this->t('Student admission notification TemplateID is required.'));
    }

    // School reimbursement sms validation.
    if ($values['enable_reimbursement_mobile_number_notification'] && empty($values['reimbursement_template_id'])) {
      $form_state->setErrorByName('reimbursement_template_id', $this->t('School reimbursement Sms notification TemplateID is required.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->configFactory->getEditable($this::SETTINGS)
      ->set('student_login.enable_student_mobile_verification', $values['enable_student_mobile_verification'] ?? FALSE)
      ->set('student_login.template_id', $values['template_id'] ?? '')
      ->set('mobile_number_verification.enable_mobile_number_verification', $values['enable_mobile_number_verification'] ?? FALSE)
      ->set('mobile_number_verification.mobile_number_verification_message', $values['mobile_number_verification_message'] ?? '')
      ->set('mobile_number_verification.mobile_number_verification_message_template_id', $values['mobile_number_verification_message_template_id'] ?? '')
      ->set('mobile_number_notification.enable_mobile_number_notification', $values['enable_mobile_number_notification'] ?? FALSE)
      ->set('mobile_number_notification.mobile_number_notification_template_id', $values['mobile_number_notification_template_id'] ?? '')
      ->set('student_verification.enable_student_verification_sms', $values['enable_student_verification_sms'] ?? FALSE)
      ->set('student_verification.student_verification_sms_template_id', $values['student_verification_sms_template_id'] ?? '')
      ->set('student_admission.enable_student_admission_sms', $values['enable_student_admission_sms'] ?? FALSE)
      ->set('student_admission.student_admission_sms_template_id', $values['student_admission_sms_template_id'] ?? '')
      ->set('mobile_number_reimbursement_notification.enable_reimbursement_mobile_number_notification', $values['enable_reimbursement_mobile_number_notification'] ?? FALSE)
      ->set('mobile_number_reimbursement_notification.reimbursement_template_id', $values['reimbursement_template_id'] ?? '')
      ->save();

    // Load the existing field storage configuration.
    $fieldPhoneNumberConfig = FieldConfig::loadByName('user', 'user', 'field_phone_number');
    if ($fieldPhoneNumberConfig instanceof FieldConfig) {
      // Get the current settings.
      $settings = $fieldPhoneNumberConfig->getSettings();
      $settings['verify'] = $values['enable_mobile_number_verification'] ? 'required' : 'none';
      $settings['message'] = $values['mobile_number_verification_message'];
      $settings['msg91_otp_template_id'] = $values['mobile_number_verification_message_template_id'] ?? '';

      // Set the updated settings.
      $fieldPhoneNumberConfig->setSettings($settings);
      // Save the field configuration.
      $fieldPhoneNumberConfig->save();
    }
    parent::submitForm($form, $form_state);
  }

}
