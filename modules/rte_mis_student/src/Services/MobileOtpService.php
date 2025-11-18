<?php

namespace Drupal\rte_mis_student\Services;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mobile_number\Exception\MobileNumberException;
use Drupal\sms\Direction;
use Drupal\sms\Message\SmsMessage;
use Drupal\sms\Provider\SmsProviderInterface;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Service for mobile otp for student registration.
 */
class MobileOtpService implements MobileOtpServiceInterface {
  use StringTranslationTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The sms service.
   *
   * @var \Drupal\sms\Provider\SmsProviderInterface
   */
  protected $smsService;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The flood service.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected $flood;

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $tokenGenerator;

  /**
   * The PhoneNumberUtil object.
   *
   * @var \libphonenumber\PhoneNumberUtil
   */
  public $libUtil;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The MSG91 service handler.
   *
   * @var \Drupal\rte_mis_smsgateway_msg91\Service\MSG91SMSService
   */
  protected $msg91Service;

  /**
   * Constructs a new OtpService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\sms\Provider\SmsProviderInterface $sms_service
   *   The sms service.
   * @param Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Defines the interface for a configuration object factory.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   Flood manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $token_generator
   *   The CSRF token generator.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time.
   * @param \Drupal\rte_mis_smsgateway_msg91\Service\MSG91SMSService $msg91_service
   *   The MSG91 service handler.
   */
  public function __construct(
    Connection $database,
    SmsProviderInterface $sms_service,
    ConfigFactoryInterface $config_factory,
    FloodInterface $flood,
    LoggerChannelFactoryInterface $logger,
    CsrfTokenGenerator $token_generator,
    TimeInterface $time,
    $msg91_service,
  ) {
    $this->database = $database;
    $this->smsService = $sms_service;
    $this->configFactory = $config_factory;
    $this->flood = $flood;
    $this->logger = $logger->get('rte_mis_student');
    $this->tokenGenerator = $token_generator;
    $this->libUtil = PhoneNumberUtil::getInstance();
    $this->time = $time;
    $this->msg91Service = $msg91_service;
  }

  /**
   * {@inheritdoc}
   */
  public function insertData($data, $token) {
    try {
      $this->database->merge('student_login')
        ->fields($data)
        ->key('token', $token)
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->error($this->t('Error in insertData method. Error: @e', [
        '@e' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fetchOtpFromDb(PhoneNumber $mobile_number, $token, $otp) {
    if (!empty($mobile_number) && !empty($token) && !empty($otp)) {
      $number = $this->getCallableNumber($mobile_number);
      $hashedCode = $this->codeHash($mobile_number, $token, $otp);
      $this->flood->register('otp_verification', MobileOtpServiceInterface::NUMBER_VERIFY_ATTEMPTS_INTERVAL, $number);
      $query = $this->database->select('student_login', 'ot')
        ->fields('ot', ['token', 'timestamp'])
        ->condition('verification_code', $hashedCode)
        ->condition('token', $token);
      $result = $query->execute()->fetch();
      if ($result) {
        // Clear the `otp_verification` flood attempts after successful.
        $this->flood->clear('otp_verification', $number);
        return $hashedCode;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    try {
      $time = $this->time->getRequestTime();
      $result = $this->database->delete('student_login')
        ->condition('timestamp', $time - 432000)
        ->execute();
      if ($result) {
        $this->logger->notice($this->t('Deleted @count OTPs from `student_login` table', [
          '@count' => $result,
        ]));
      }
    }
    catch (\Exception $e) {
      $this->logger->error($this->t('Error in garbageCollection method. Error: @e', [
        '@e' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkFlood(PhoneNumber $mobile_number, $type = 'phone-number') {
    switch ($type) {
      case 'phone-number':
        return $this->flood->isAllowed('mobile_number_verification', $this::NUMBER_VERIFY_ATTEMPTS_COUNT, $this::NUMBER_VERIFY_ATTEMPTS_INTERVAL, $this->getCallableNumber($mobile_number));

      case 'otp':
        return $this->flood->isAllowed('otp_verification', $this::OTP_VERIFY_ATTEMPTS_COUNT, $this::OTP_VERIFY_ATTEMPTS_INTERVAL, $this->getCallableNumber($mobile_number));

      default:
        return TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function sendOtp(PhoneNumber $mobile_number, $otp) {
    try {
      // Load configs once.
      $config = $this->configFactory->get('rte_mis_mail.sms_settings');
      $template_id = $config->get('student_login.template_id') ?? NULL;
      $enable_verification = (bool) $config->get('student_login.enable_student_mobile_verification');

      // Prepare mobile number.
      $number = $this->getCallableNumber($mobile_number);
      $number = preg_replace('/^(\+91|91)/', '', $number);

      // Default fallback message.
      $fallback_message = "Your OTP for login is $otp";

      // Send using MSG91 service.
      if ($enable_verification) {
        $msg91 = $this->msg91Service;

        if (empty($template_id)) {
          // Template missing → send fallback text message.
          $response = $msg91->sendMessage($number, $fallback_message, NULL, ['OTP' => $otp]);
        }
        else {
          // Template-based message.
          $response = $msg91->sendMessage($number, '', $template_id, ['OTP' => $otp]);
        }

        // Register flood entry.
        $this->flood->register('mobile_number_verification', $this::NUMBER_VERIFY_ATTEMPTS_INTERVAL, $number);

        // Success handling.
        if (!empty($response['type']) && $response['type'] === 'success') {
          $this->logger->info('OTP sent to @num using template @tid', [
            '@num' => $number,
            '@tid' => $template_id ?? 'fallback',
          ]);
          return TRUE;
        }
        // Failure handling.
        $this->logger->warning('OTP send failed via MSG91. Response: @res', [
          '@res' => print_r($response, TRUE),
        ]);

        return FALSE;
      }
      // Fallback → Send using generic SMS service.
      $message = $fallback_message;
      $sms = (new SmsMessage())
        ->setMessage($message)
        ->addRecipient($number)
        ->setDirection(Direction::OUTGOING);
      $report = $this->smsService->send($sms)[0];
      $this->flood->register('mobile_number_verification', $this::NUMBER_VERIFY_ATTEMPTS_INTERVAL, $number);
      return $report->getResult()->getReport($number)->getStatus();
    }
    catch (\Exception $e) {
      $this->logger->error($this->t('Error in sendOtp method. Error: @e', [
        '@e' => $e->getMessage(),
      ]));
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function generateOtp($length = 4) {
    return str_pad((string) rand(0, pow(10, $length)), $length, '0', STR_PAD_LEFT);
  }

  /**
   * {@inheritdoc}
   */
  public function createToken(PhoneNumber $mobile_number) {
    $number = $this->getCallableNumber($mobile_number);
    $time = time();
    return $this->tokenGenerator->get(rand(0, 999999999) . $time . 'mobile number token' . $number);
  }

  /**
   * {@inheritdoc}
   */
  public function codeHash(PhoneNumber $mobile_number, $token, $code) {
    $number = $this->getCallableNumber($mobile_number);
    $secret = $this->configFactory->getEditable('rte_mis_student.settings')
      ->get('verification_secret');
    return sha1("$number$secret$token$code");
  }

  /**
   * {@inheritdoc}
   */
  public function validateUser($hashed_code, $token) {
    try {
      $query = $this->database->select('student_login', 'ot')
        ->fields('ot', ['timestamp'])
        ->condition('verification_code', $hashed_code)
        ->condition('token', $token);
      $result = $query->execute()->fetch();
      if (!empty($result)) {
        return TRUE;
      }
      return FALSE;
    }
    catch (\Exception $e) {
      $this->logger->error($this->t('Error in validateUser method. Error: @e', [
        '@e' => $e->getMessage(),
      ]));
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function testMobileNumber($number, $country = NULL, $types = [1 => 1, 2 => 2]) {

    if (!$number) {
      throw new MobileNumberException('Empty number', MobileNumberException::ERROR_NO_NUMBER);
    }

    try {
      /** @var \libphonenumber\PhoneNumber $phone_number */
      $phone_number = $this->libUtil->parse($number, $country);
    }
    catch (NumberParseException $e) {
      throw new MobileNumberException('Invalid number or unknown country', MobileNumberException::ERROR_INVALID_NUMBER);
    }

    if ($types) {
      if (!in_array($this->libUtil->getNumberType($phone_number), $types)) {
        throw new MobileNumberException('Not a mobile number', MobileNumberException::ERROR_WRONG_TYPE);
      }
    }

    $mcountry = $this->libUtil->getRegionCodeForNumber($phone_number);

    if ($country && ($mcountry != $country)) {
      throw new MobileNumberException('Mismatch country with the number\'s prefix', MobileNumberException::ERROR_WRONG_COUNTRY);
    }

    return $phone_number;
  }

  /**
   * {@inheritdoc}
   */
  public function getCallableNumber(PhoneNumber $mobile_number) {
    return $mobile_number ? $this->libUtil->format($mobile_number, PhoneNumberFormat::E164) : NULL;
  }

}
