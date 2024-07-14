<?php

namespace Drupal\rte_mis_lottery\Batch;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\sms\Direction;
use Drupal\sms\Exception\RecipientRouteException;
use Drupal\sms\Message\SmsMessage;

/**
 * Process the batch for Send SMS for lottery.
 */
class SendSmsBatch {
  use StringTranslationTrait;

  /**
   * Undocumented function.
   */
  public static function sendSms(array $data, array &$context) {
    if (!isset($context['results']['rows'])) {
      $context['results']['rows'] = [];
    }
    $sms_provider = \Drupal::service('sms.provider');
    $student_sms_config = \Drupal::config('rte_mis_lottery.settings')->get('notify_student');
    $logger_service = \Drupal::logger('rte_mis_lottery');
    if ($student_sms_config['enable_sms'] ?? FALSE) {
      foreach ($data as $record) {
        try {
          $message = '';
          if (strtolower($record->allocation_status ?? '') == 'allotted') {
            $message = $student_sms_config['alloted_message'] ?? '';
            $message = str_replace(['!application_number', '!udise_code'],
            [$record->student_application_number, $record->school_udise_code],
            $message);
          }
          elseif (strtolower($record->allocation_status ?? '') == 'un-alloted') {
            $message = $student_sms_config['un_alloted_message'] ?? '';
            $message = str_replace('!application_number', $record->student_application_number, $message);
          }
          if (!empty($message) && !empty($record->mobile_number)) {
            $sms = (new SmsMessage())
            // Set the message.
              ->setMessage($message)
            // Set recipient phone number.
              ->addRecipient($record->mobile_number)
              ->setDirection(Direction::OUTGOING);
            $result = $sms_provider->send($sms)[0];
            if ($result->getResult()->getReport($record->mobile_number)->getStatus() == 'delivered') {
              $context['results']['rows']['passed'][] = $record->student_id;
              $logger_service->info('SMS sent successfully. Student Name: @student_name, Mobile Number: @mobile_number and ID: @id ', [
                '@id' => $record->student_id,
                '@student_name' => $record->student_name,
                '@mobile_number' => $record->mobile_number,
              ]);
            }
            else {
              $context['results']['rows']['failed'][] = $record->student_id;
              $logger_service->info('SMS failed to sent. Student Name: @student_name, Mobile Number: @mobile_number and ID: @id ', [
                '@id' => $record->student_id,
                '@student_name' => $record->student_name,
                '@mobile_number' => $record->mobile_number,
              ]);
            }
          }
          else {
            $context['results']['rows']['failed'][] = $record->student_id;
            $logger_service->info('SMS failed to sent. Student Name: @student_name, Mobile Number: @mobile_number and ID: @id ', [
              '@id' => $record->student_id,
              '@student_name' => $record->student_name,
              '@mobile_number' => $record->mobile_number,
            ]);
          }
        }
        catch (RecipientRouteException $e) {
          $logger_service->info('SMS failed to sent. Student Name: @student_name, Mobile Number: @mobile_number and ID: @id ', [
            '@id' => $record->student_id,
            '@student_name' => $record->student_name,
            '@mobile_number' => $record->mobile_number,
          ]);
        }
      }
    }

  }

  /**
   * Callback function for when the batch process finishes.
   */
  public static function rteMisLotteryBatchFinished($success, $results, array $operations) {
    if ($success) {
      \Drupal::messenger()->addMessage(t('Sending SMS to student completed successfully.'));
    }
    else {
      \Drupal::messenger()->addMessage(t('An error occurred while sending the sms.'));
    }
  }

}
