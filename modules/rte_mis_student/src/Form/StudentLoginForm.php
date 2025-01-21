<?php

namespace Drupal\rte_mis_student\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mobile_number\Exception\MobileNumberException;
use Drupal\rte_mis_student\Services\MobileOtpServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * This class create login form for student.
 */
class StudentLoginForm extends FormBase {

  /**
   * Mobile OTP service.
   *
   * @var Drupal\rte_mis_student\Services\MobileOtpServiceInterface
   */
  protected $mobileOtpService;

  /**
   * Number used keeping track of OTP.
   *
   * @var int
   */
  protected $count = 0;

  /**
   * Retrieves the currently active request object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs the service objects.
   *
   * Class constructor.
   */
  public function __construct(MobileOtpServiceInterface $mobile_otp_service, Request $request_stack, EntityTypeManagerInterface $entity_type_manager) {
    $this->mobileOtpService = $mobile_otp_service;
    $this->request = $request_stack;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('rte_mis_student.mobile_otp_service'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'student_login_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $phoneNumber = $this->request->query->get('phone') ?? NULL;
    $form['message_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'message-wrapper',
      ],
    ];

    $form['otp_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'verify-otp-wrapper',
      ],
    ];

    $form['otp_wrapper']['phone_number'] = [
      '#type' => 'number',
      '#title' => $this->t('Mobile Number'),
      '#attributes' => [
        'id' => 'phone-number-field',
      ],
      '#default_value' => $phoneNumber ?? NULL,
      '#attributes' => [
        'readonly' => isset($phoneNumber) ? TRUE : FALSE,
      ],
    ];

    $form['otp_wrapper']['request_otp'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate OTP'),
      '#submit' => ['::mailIncrement'],
      '#name' => 'generate-otp',
      '#attributes' => [
        'class' => ['otp-send-button'],
      ],
      '#ajax' => [
        'callback' => [$this, 'otpGenerate'],
        'wrapper' => 'verify-otp-wrapper',
        'progress' => [
          'type' => 'fullscreen',
        ],
      ],
      '#suffix' => '<div id="resend-timer"></div>',
    ];

    if ($this->count > 0) {
      $form['otp_wrapper']['verify_otp'] = [
        '#type' => 'number',
        '#title' => $this->t('Enter OTP'),
      ];

      $form['otp_wrapper']['submit_otp'] = [
        '#type' => 'button',
        '#value' => $this->t('Verify OTP'),
        '#name' => 'verify-otp',
        '#ajax' => [
          'callback' => [$this, 'verifyOtp'],
          'wrapper' => 'verify-otp-wrapper',
          'progress' => [
            'type' => 'fullscreen',
          ],
        ],
      ];
    }

    $form['#attached']['library'][] = 'rte_mis_student/resend_timer';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $name = $form_state->getTriggeringElement()['#name'] ?? NULL;
    try {
      $mobile_number = $this->mobileOtpService->testMobileNumber($values['phone_number'], 'IN');
      if (in_array($name, ['generate-otp']) && !$this->mobileOtpService->checkFlood($mobile_number)) {
        $form_state->setErrorByName('phone_number', $this->t('Too many OTP requested, please try again shortly.'));
      }
      elseif ($name == 'verify-otp' && !$this->mobileOtpService->checkFlood($mobile_number, 'otp')) {
        $form_state->setErrorByName('verify_otp', $this->t('Too many verification attempt failed, please try again later.'));
      }
      elseif ($this->count > 0 && $name == 'verify-otp' && empty($values['verify_otp'])) {
        $form_state->setErrorByName('verify_otp', $this->t('Please enter valid OTP.'));
      }

    }
    catch (MobileNumberException $e) {
      switch ($e->getCode()) {
        case MobileNumberException::ERROR_NO_NUMBER:
          $form_state->setErrorByName('phone_number', $this->t('Phone number is required.'));
          break;

        case MobileNumberException::ERROR_INVALID_NUMBER:
        case MobileNumberException::ERROR_WRONG_TYPE:
          $form_state->setErrorByName('phone_number', $this->t('The phone number is not a valid mobile number'));
          break;

        case MobileNumberException::ERROR_WRONG_COUNTRY:
          $form_state->setErrorByName('phone_number', $this->t('The country %value provided for %field does not match the mobile number prefix.'));
          break;
      }
    }

  }

  /**
   * This function send the OTP if all the error validated before sending.
   */
  public function mailIncrement(array &$form, FormStateInterface $form_state) {
    $this->count++;
    // Validate phone number.
    $phone_number = $form_state->getValue('phone_number');
    if (!empty($phone_number)) {
      $mobile_number = $this->mobileOtpService->testMobileNumber($phone_number, 'IN');
      $otp = $this->mobileOtpService->generateOtp();
      $result = $this->mobileOtpService->sendOtp($mobile_number, $otp);
      if ($result == 'delivered') {
        $this->messenger()->addMessage($this->t('OTP sent to your mobile number.'));
        $token = $this->mobileOtpService->createToken($mobile_number);
        $hashedCode = $this->mobileOtpService->codeHash($mobile_number, $token, $otp);
        $data = [
          'verification_code' => $hashedCode,
          'timestamp' => time(),
          'token' => $token,
        ];
        $this->mobileOtpService->insertData($data, $token);
        $storage = $form_state->getStorage();
        $storage['mobile_otp_verification']['token'] = $token;
        $form_state->setStorage($storage);
        $form_state->setRebuild();
      }
      else {
        $this->messenger()->addError($this->t('Cannot send sms, Please try again later.'));
      }

    }
  }

  /**
   * Ajax callback for OTP generate.
   */
  public function otpGenerate(array &$form, FormStateInterface $form_state) {
    $flag = TRUE;
    $messages = $this->messenger()->all();
    $this->messenger()->deleteAll();
    $response = new AjaxResponse();
    foreach ($messages as $type => $message) {
      if ($type == 'error') {
        $flag = FALSE;
      }
      $response->addCommand(new MessageCommand($message[0]->__toString(), '#message-wrapper', ['type' => $type], TRUE));
    }
    if ($flag) {
      $settings['rte_mis_student']['resend_time'] = time();
      $response->addCommand(new SettingsCommand($settings, TRUE));
    }
    $response->addCommand(new ReplaceCommand(NULL, $form['otp_wrapper']));
    return $response;
  }

  /**
   * Ajax callback for verifying OTP.
   */
  public function verifyOtp(array &$form, FormStateInterface $form_state) {
    $successRedirect = TRUE;
    $destination = $this->request->query->get('destination') ?? NULL;
    $queryPhoneNumber = $this->request->query->get('phone') ?? NULL;
    $response = new AjaxResponse();
    $verifyError = $form_state->getError($form['otp_wrapper']['verify_otp']);
    $otp = $form_state->getValue('verify_otp');
    $phone_number = $form_state->getValue('phone_number');
    if (!empty($otp) && !empty($phone_number) && !isset($verifyError)) {
      $mobile_number = $this->mobileOtpService->testMobileNumber($phone_number, 'IN');
      $number = $this->mobileOtpService->getCallableNumber($mobile_number);
      $formStorage = $form_state->getStorage();
      $token = $formStorage['mobile_otp_verification']['token'] ?? NULL;
      $hashedCode = $this->mobileOtpService->fetchOtpFromDb($mobile_number, $token, $otp);
      if ($hashedCode) {
        // Create and set the cookie.
        $tokenCookie = new Cookie('student-token', $token, strtotime("+1 day"), '/', NULL, TRUE, FALSE, FALSE, 'Strict');
        $phoneNumberCookie = new Cookie('student-phone', $number, strtotime("+1 day"), '/', NULL, TRUE, TRUE, FALSE, 'Strict');

        if (isset($destination) && !empty($destination)) {
          $url = URL::fromUserInput($destination, [
            'query' => [
              'code' => $hashedCode,
              'destination' => Url::fromRoute('view.student_registration.page_1')->toString(),
            ],
          ]);
          $routeParameter = $url->getRouteParameters();
          $entityId = $routeParameter['mini_node'] ?? NULL;
          $result = $this->entityTypeManager->getStorage('mini_node')->getQuery()
            ->accessCheck(TRUE)
            ->condition('type', 'student_details')
            ->condition('field_academic_year', _rte_mis_core_get_current_academic_year())
            ->condition('field_mobile_number', $number)
            ->execute();
          if (empty($result) || !in_array($entityId, $result)) {
            $successRedirect = FALSE;
            $this->messenger()->addError($this->t('No application found with the provided phone number.'));
            $url = Url::fromRoute('rte_mis_student.login.form', [], [
              'query' => [
                'phone' => $queryPhoneNumber,
                'destination' => $destination,
              ],
            ]);
          }
        }
        else {
          // Redirect to the student application controller.
          $url = Url::fromRoute('rte_mis_student.controller.student_application', [], [
            'query' => [
              'code' => $hashedCode,
            ],
          ]);
        }
        $command = new RedirectCommand($url->toString());
        $response->addCommand($command);
        if ($successRedirect) {
          $response->headers->setCookie($tokenCookie);
          $response->headers->setCookie($phoneNumberCookie);
          $this->messenger()->addStatus($this->t('OTP successfully verified'));
        }
        return $response;
      }
      else {
        $response->addCommand(new MessageCommand($this->t('Invalid OTP, Please enter the valid OTP'), '#message-wrapper', ['type' => 'error'], TRUE));
      }
    }
    else {
      $messages = $this->messenger()->all();
      $this->messenger()->deleteAll();
      foreach ($messages as $type => $message) {
        $response->addCommand(new MessageCommand($message[0]->__toString(), '#message-wrapper', ['type' => $type], TRUE));
      }
    }
    $response->addCommand(new ReplaceCommand(NULL, $form['otp_wrapper']));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
