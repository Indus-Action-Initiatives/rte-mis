<?php

namespace Drupal\rte_mis_mail\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Service for otp registration.
 */
class OtpService {

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
   * The messenger servvice.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

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
   * The translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $translation;

  /**
   * Constructs a new OtpService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The translation service.
   * @param Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Defines the interface for a configuration object factory.
   */
  public function __construct(
    Connection $database,
    MailManagerInterface $mailManager,
    MessengerInterface $messenger,
    AccountInterface $currentUser,
    TranslationInterface $translation,
    ConfigFactoryInterface $config_factory,
    ) {
    $this->database = $database;
    $this->messenger = $messenger;
    $this->mailManager = $mailManager;
    $this->currentUser = $currentUser;
    $this->translation = $translation;
    $this->configFactory = $config_factory;
  }

  /**
   * Verifying the user entered email.
   *
   * @return bool|array
   *   Returns FALSE if no email errors are found,
   *   otherwise an array of email errors.
   */
  public function verifyEmail() {
    $all_errors = $this->messenger->messagesByType(MessengerInterface::TYPE_ERROR);
    $email_errors = [];
    foreach ($all_errors as $individual_error) {
      $individual_error = strtolower($individual_error->__toString());
      if (str_contains($individual_error, 'username')) {
        // Stores in title case.
        $email_errors[] = ucwords($individual_error);
      }
    }
    return empty($email_errors) ? FALSE : $email_errors;
  }

  /**
   * Function for generating the otp and creation time.
   *
   * @return array
   *   Returns the otp and its created time.
   */
  public function generateOtp() {
    $otp = (string) (rand(1000, 9999));
    $time = time();
    return [$otp, $time];
  }

  /**
   * Check if any data existed for an email in the database.
   *
   * @param string $email
   *   User email.
   *
   * @return bool
   *   Returns TRUE or FALSE.
   */
  public function checkDatabaseRecord(string $email) {
    if (!empty($email)) {
      $query = $this->database->select('rte_mis_otp', 'ot');
      $results = $query->fields('ot', ['created'])
        ->condition('email', $email)
        ->execute()
        ->fetchAll();
    }
    if ($results != NULL) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Insert data in the table.
   *
   * @param array $rte_mis_otp_values
   *   The Otp details of the user.
   */
  public function insertData($rte_mis_otp_values) {
    try {
      $this->database->insert('rte_mis_otp')
        ->fields($rte_mis_otp_values)
        ->execute();
    }
    catch (\Exception $e) {
      $e->getMessage();
    }
  }

  /**
   * Update data in the table.
   *
   * @param array $rte_mis_otp_values
   *   The Otp details of the user.
   */
  public function updateData(array $rte_mis_otp_values) {
    // Ensure all required values are present.
    try {
      if (
      !empty($rte_mis_otp_values['otp']) &&
      !empty($rte_mis_otp_values['created']) &&
      !empty($rte_mis_otp_values['email']) &&
      !empty($rte_mis_otp_values['context'])
      ) {
        // Use a single fields() call for simplicity.
        $this->database->update('rte_mis_otp')
          ->fields([
            'otp' => $rte_mis_otp_values['otp'],
            'created' => $rte_mis_otp_values['created'],
            'context' => $rte_mis_otp_values['context'],
          ])
          ->condition('email', $rte_mis_otp_values['email'])
          ->execute();
      }
    }
    catch (\Exception $e) {
      $e->getMessage();
    }
  }

  /**
   * Generate mail.
   *
   * @param string $to
   *   The email address of the receiver.
   * @param string $module_name
   *   The custom module name.
   * @param string $key
   *   The key for the hook_mail()
   * @param int $otp
   *   The $otp to be sent.
   */
  public function generateMail(string $to, string $module_name, string $key, int $otp) {
    try {
      $mail_config = $this->configFactory->get('email_form.settings');
      $site_config = $this->configFactory->get('system.site');
      // Default Values.
      $default_message = "Your OTP for email validation is: " . $otp;
      // Fetching value from config form.
      $message = trim($mail_config->get('message'));

      $params['subject'] = 'Email Validation OTP for ' . $site_config->get('name');
      $params['message'] = strpos($message, '!code') !== FALSE ? str_replace("!code", $otp, $message) : $default_message;
      $langcode = $this->currentUser->getPreferredLangcode();
      $result = $this->mailManager->mail($module_name, $key, $to, $langcode, $params, NULL, TRUE);
      if ($result['result'] !== TRUE) {
        $this->messenger->addMessage($this->translation->translate('There was a problem sending your message and it was not sent.'), 'error');
        return FALSE;
      }
      else {
        $this->messenger->addMessage($this->translation->translate('Your OTP has been sent via email.'));
        return TRUE;
      }
    }
    catch (\Exception $e) {
      $e->getMessage();
    }
  }

  /**
   * Fetching the otp from the database.
   *
   * @param string $email
   *   The email id of the user.
   *
   * @return int
   *   The otp stored in the database.
   */
  public function fetchOtpFromDb(string $email) {
    $query = $this->database->select('rte_mis_otp', 'ot')
      ->fields('ot', ['otp'])
      ->condition('email', $email);
    $result = $query->execute()->fetchCol();
    $otp = reset($result);
    return $otp;
  }

  /**
   * Marks the email as verified in the database.
   *
   * @param string $email
   *   The user's email.
   */
  public function markEmailAsVerified(string $email) {
    try {
      $updateData = ['verified' => 1];
      $this->database->update('rte_mis_otp')
        ->fields($updateData)
        ->condition('email', $email)
        ->execute();
    }
    catch (\Exception $e) {
      $e->getMessage();
    }
  }

  /**
   * For fetching the required rows from the database.
   *
   * @param array $fields
   *   The fields required.
   *
   * @return array
   *   The filtered email ids.
   */
  public function filterRows(array $fields) {
    $email_list = [];
    $results = $this->database->select('rte_mis_otp', 'ot')
      ->fields('ot', $fields)
      ->execute()
      ->fetchAll();
    // Storing all the emails whose OTP is not matched and created within 30min.
    foreach ($results as $result) {
      $time_now = time();
      $otp_sent_time = $result->created;
      if (($time_now - $otp_sent_time < (30 * 60)) && ($result->verified == 0)) {
        $email_list[] = $result->email;
      }
    }
    return $email_list;
  }

  /**
   * Clean up the uncessary emails in the table.
   *
   * @param array|null $emails_to_preserve
   *   The list of emails.
   */
  public function cleanUpOtps(?array $emails_to_preserve = NULL) {
    try {
      if (!empty($emails_to_preserve)) {
        $this->database->delete('rte_mis_otp')
          ->condition('email', $emails_to_preserve, 'NOT IN')
          ->execute();
      }
      else {
        $this->database->truncate('rte_mis_otp')->execute();
      }
    }
    catch (\Exception $e) {
      $e->getMessage();
    }
  }

}
