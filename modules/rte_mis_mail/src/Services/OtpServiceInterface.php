<?php

namespace Drupal\rte_mis_mail\Services;

/**
 * Provides an interface for Otp Service.
 */
interface OtpServiceInterface {

  /**
   * Number of email that can be sent to verify.
   */
  const EMAIL_VERIFY_ATTEMPTS_COUNT = 5;

  /**
   * Time interval for email to verify.
   */
  const EMAIL_VERIFY_ATTEMPTS_INTERVAL = 3600;

  /**
   * Number of attempts to verify the otp.
   */
  const OTP_VERIFY_ATTEMPTS_COUNT = 5;

  /**
   * Time interval for OTP to verify.
   */
  const OTP_VERIFY_ATTEMPTS_INTERVAL = 3600;

  /**
   * Check if any data existed for an email in the database.
   *
   * @param string $email
   *   User email.
   *
   * @return bool
   *   Returns TRUE or FALSE.
   */
  public function isVerified($email);

  /**
   * Insert data in the table.
   *
   * @param array $data
   *   The Otp details of the user.
   * @param string $email
   *   Email of user.
   */
  public function insertData(array $data, $email);

  /**
   * Fetching the otp from the database.
   *
   * @param int $otp
   *   The OTP to verify.
   * @param string $email
   *   The email id of the user.
   * @param string $token
   *   CSRF Token.
   *
   * @return int
   *   The otp stored in the database.
   */
  public function fetchOtpFromDb(int $otp, string $email, $token);

  /**
   * For fetching the required rows from the database.
   *
   * @param array $fields
   *   The fields required.
   *
   * @return array
   *   The filtered email ids.
   */
  public function filterRows(array $fields);

  /**
   * Clean up the unnecessary emails in the table.
   *
   * @param array|null $emails_to_preserve
   *   The list of emails.
   */
  public function cleanUpOtps(?array $emails_to_preserve = NULL);

  /**
   * Checks whether there were too many verifications attempted with the mail.
   *
   * @param string $mail
   *   Email to check flood.
   * @param string $type
   *   Flood type, 'email' or 'otp'.
   */
  public function checkFlood($mail, $type = 'email');

  /**
   * Send mail containing the OTP to email address.
   *
   * @param string $email
   *   Email to check flood.
   * @param string $token
   *   CSRF Token.
   */
  public function sendVerificationMail(string $email, $token);

}
