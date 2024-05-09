<?php

namespace Drupal\rte_mis_student\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mobile_number\Exception\MobileNumberException;
use Drupal\rte_mis_student\Services\MobileOtpServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This class provide the form for checking the student application status.
 */
class StudentApplicationStatusForm extends FormBase {

  /**
   * Mobile OTP service.
   *
   * @var Drupal\rte_mis_student\Services\MobileOtpServiceInterface
   */
  protected $mobileOtpService;

  /**
   * Constructs the service objects.
   *
   * Class constructor.
   */
  public function __construct(MobileOtpServiceInterface $mobile_otp_service) {
    $this->mobileOtpService = $mobile_otp_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('rte_mis_student.mobile_otp_service')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'student_application_status_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['search_by'] = [
      '#type' => 'radios',
      '#title' => $this->t('Get the status of application by'),
      '#required' => TRUE,
      '#options' => [
        'application_number' => $this->t('Registration Number'),
        'phone_number' => $this->t('Phone Number'),
      ],
    ];

    $form['application_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enter your Registration ID:'),
      '#states' => [
        'visible' => [
          'input[name="search_by"]' => ['value' => 'application_number'],
        ],
        'required' => [
          'input[name="search_by"]' => ['value' => 'application_number'],
        ],
      ],
    ];

    $form['phone_number'] = [
      '#type' => 'number',
      '#title' => $this->t('Phone Number:'),
      '#states' => [
        'visible' => [
          'input[name="search_by"]' => ['value' => 'phone_number'],
        ],
        'required' => [
          'input[name="search_by"]' => ['value' => 'phone_number'],
        ],
      ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    try {
      if ($values['search_by'] == 'application_number' && empty($values['application_number'])) {
        $form_state->setErrorByName('application_number', $this->t('Please add registration id'));
      }
      elseif ($values['search_by'] == 'phone_number') {
        $this->mobileOtpService->testMobileNumber($values['phone_number'], 'IN');
      }

    }
    catch (MobileNumberException $e) {
      switch ($e->getCode()) {
        case MobileNumberException::ERROR_NO_NUMBER:
          $form_state->setErrorByName('phone_number', $this->t('Phone number is required.'));
          break;

        case MobileNumberException::ERROR_INVALID_NUMBER:
        case MobileNumberException::ERROR_WRONG_TYPE:
          $form_state->setErrorByName('phone_number', $this->t('The phone number is not a valid mobile number.'));
          break;

        case MobileNumberException::ERROR_WRONG_COUNTRY:
          $form_state->setErrorByName('phone_number', $this->t('The country value is not valid.'));
          break;
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $value = '';
    if ($values['search_by'] == 'application_number') {
      $value = $values['application_number'];
    }
    elseif ($values['search_by'] == 'phone_number') {
      $value = $values['phone_number'];
      $phoneNumber = $this->mobileOtpService->testMobileNumber($value, 'IN');
      $phoneNumber = $this->mobileOtpService->getCallableNumber($phoneNumber);
      // Set the cookie.
      setcookie('student-phone', $phoneNumber, strtotime("+1 day"), '/', NULL, TRUE, TRUE);
    }
    // Redirect to student listing view.
    $form_state->setRedirect('rte_mis_student.controller.student_application_status', [], [
      'query' => [
        'search-by' => $values['search_by'],
        'value' => $value,
      ],
    ]);
  }

}
