<?php

namespace Drupal\rte_mis_student\Services;

use libphonenumber\PhoneNumber;

/**
 * Provides an interface for Otp Service.
 */
interface MobileOtpServiceInterface {

  /**
   * Number of email that can be sent to verify.
   */
  const NUMBER_VERIFY_ATTEMPTS_COUNT = 5;

  /**
   * Time interval for email to verify.
   */
  const NUMBER_VERIFY_ATTEMPTS_INTERVAL = 3600;

  /**
   * Number of attempts to verify the otp.
   */
  const OTP_VERIFY_ATTEMPTS_COUNT = 5;

  /**
   * Time interval for OTP to verify.
   */
  const OTP_VERIFY_ATTEMPTS_INTERVAL = 3600;

  /**
   * Insert data in the table.
   *
   * @param array $data
   *   The Otp details of the user.
   * @param string $token
   *   Unique token.
   */
  public function insertData(array $data, $token);

  /**
   * Fetching the otp from the database.
   *
   * @param \libphonenumber\PhoneNumber $mobile_number
   *   Phone number object.
   * @param string $token
   *   Unique token.
   * @param string $otp
   *   OTP to be verified.
   *
   * @return string|bool
   *   The hashed code or FALSE if no record is found.
   */
  public function fetchOtpFromDb(PhoneNumber $mobile_number, $token, $otp);

  /**
   * Checks whether there were too many verifications attempted with the mail.
   *
   * @param \libphonenumber\PhoneNumber $mobile_number
   *   Phone number object.
   * @param string $type
   *   Flood type, 'phone-number' or 'otp'.
   */
  public function checkFlood(PhoneNumber $mobile_number, $type = 'email');

  /**
   * Send mail containing the OTP to email address.
   *
   * @param \libphonenumber\PhoneNumber $mobile_number
   *   Phone number object.
   * @param string $otp
   *   OTP generated.
   */
  public function sendOtp(PhoneNumber $mobile_number, $otp);

  /**
   * Create unique token.
   *
   * @param \libphonenumber\PhoneNumber $mobile_number
   *   Phone number object.
   */
  public function createToken(PhoneNumber $mobile_number);

  /**
   * Generates a random numeric string.
   *
   * @param int $length
   *   Number of digits.
   *
   * @return string
   *   Code in length of $length.
   */
  public function generateOtp($length = 4);

  /**
   * Get international number.
   *
   * @param \libphonenumber\PhoneNumber $mobile_number
   *   Phone number object.
   *
   * @return string
   *   E.164 formatted number.
   */
  public function getCallableNumber(PhoneNumber $mobile_number);

  /**
   * Test mobile number validity.
   *
   * @param string $number
   *   Number.
   * @param null|string $country
   *   Country.
   * @param array $types
   *   Mobile number types to verify as defined in
   *   \libphonenumber\PhoneNumberType.
   *
   * @throws \Drupal\mobile_number\Exception\MobileNumberException
   *   Thrown if mobile number is not valid.
   *
   * @return \libphonenumber\PhoneNumber
   *   Libphonenumber Phone number object.
   */
  public function testMobileNumber($number, $country = NULL, array $types = [1 => 1, 2 => 2]);

  /**
   * Validate if student is successfully logged-in.
   *
   * @param string $hashed_code
   *   The hashed code.
   * @param string $token
   *   The unique token.
   */
  public function validateUser($hashed_code, $token);

  /**
   * Delete the old token from table.
   */
  public function garbageCollection();

}
