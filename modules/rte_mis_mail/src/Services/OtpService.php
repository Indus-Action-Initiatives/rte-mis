<?php

namespace Drupal\rte_mis_mail\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service for otp registration.
 */
class OtpService implements OtpServiceInterface {
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
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

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
  public $flood;

  /**
   * Constructs a new OtpService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Defines the interface for a configuration object factory.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   Flood manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   */
  public function __construct(
    Connection $database,
    MailManagerInterface $mailManager,
    AccountInterface $currentUser,
    ConfigFactoryInterface $config_factory,
    FloodInterface $flood,
    LoggerChannelFactoryInterface $logger,
  ) {
    $this->database = $database;
    $this->mailManager = $mailManager;
    $this->currentUser = $currentUser;
    $this->configFactory = $config_factory;
    $this->flood = $flood;
    $this->logger = $logger->get('rte_mis_mail');
  }

  /**
   * {@inheritdoc}
   */
  public function isVerified($email) {
    return !empty($_SESSION['mail_verification'][$email]['verified']);
  }

  /**
   * {@inheritdoc}
   */
  public function insertData($data, $email) {
    try {
      $this->database->merge('rte_mis_otp')
        ->fields($data)
        ->key('mail', $email)
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
  public function fetchOtpFromDb(int $otp, string $email, $token) {
    $query = $this->database->select('rte_mis_otp', 'ot')
      ->fields('ot', ['otp', 'created'])
      ->condition('mail', $email)
      ->condition('otp', $otp)
      ->condition('token', $token)
      ->condition('verified', 0);
    $result = $query->execute()->fetch();
    if ($result) {
      $otpCreated = $result->created ?? NULL;
      // Getting Current Time.
      $currentTime = time();
      $timeDiff = $currentTime - $otpCreated;
      // Checking is Time is expired or not.
      if (($timeDiff / 60) > 10) {
        // OTP Expired.
        return 0;
      }
      else {
        // OTP validated.
        $_SESSION['mail_verification'][$email]['verified'] = TRUE;
        return 1;
      }
    }
    else {
      // Invalid OTP.
      return -1;
    }

  }

  /**
   * {@inheritdoc}
   */
  public function filterRows(array $fields) {
    $email_list = [];
    $results = $this->database->select('rte_mis_otp', 'ot')
      ->fields('ot', $fields)
      ->execute()
      ->fetchAll();
    // Storing all the emails whose OTP is not matched and created within 2mins.
    foreach ($results as $result) {
      $time_now = time();
      $otp_sent_time = $result->created;
      if (($time_now - $otp_sent_time < (2 * 60)) && ($result->verified == 0)) {
        $email_list[] = $result->email;
      }
    }
    return $email_list;
  }

  /**
   * {@inheritdoc}
   */
  public function cleanUpOtps(?array $emails_to_preserve = NULL) {
    try {
      if (!empty($emails_to_preserve)) {
        $this->database->delete('rte_mis_otp')
          ->condition('mail', $emails_to_preserve, 'NOT IN')
          ->execute();
      }
      else {
        $this->database->truncate('rte_mis_otp')->execute();
      }
    }
    catch (\Exception $e) {
      $this->logger->error($this->t('Error in cleanUpOtps method. Error: @e', [
        '@e' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkFlood($mail, $type = 'email') {
    switch ($type) {
      case 'email':
        return $this->flood->isAllowed('email_verification', $this::EMAIL_VERIFY_ATTEMPTS_COUNT, $this::EMAIL_VERIFY_ATTEMPTS_INTERVAL, $mail);

      case 'otp':
        return $this->flood->isAllowed('email_otp_verification', $this::OTP_VERIFY_ATTEMPTS_COUNT, $this::OTP_VERIFY_ATTEMPTS_INTERVAL, $mail);

      default:
        return TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function sendVerificationMail(string $email, $token) {
    try {
      $otp = rand(1000, 9999);
      $otp_table_values = [
        'otp' => $otp,
        'created' => time(),
        'context' => 'user_registration',
        'verified' => 0,
        'token' => $token,
      ];

      $this->insertData($otp_table_values, $email);
      $mailConfig = $this->configFactory->get('rte_mis_mail.settings');
      $body = $mailConfig->get('email_verification.email_verification_message') ?? '';
      $params['subject'] = $mailConfig->get('email_verification.email_verification_subject') ?? '';
      $params['message'] = str_replace('!code', $otp, $body);
      $langCode = $this->currentUser->getPreferredLangcode();
      $result = $this->mailManager->mail('rte_mis_mail', 'otp', $email, $langCode, $params, NULL, TRUE);
      if ($result['result']) {
        return TRUE;
      }
    }
    catch (\Throwable $e) {
      $this->logger->error($this->t('Error in sendVerificationMail method. Error: @e', [
        '@e' => $e->getMessage(),
      ]));
    }
    return FALSE;

  }

}
