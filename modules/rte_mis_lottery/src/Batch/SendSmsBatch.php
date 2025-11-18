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
    $msg91Service = \Drupal::service('rte_mis_smsgateway_msg91.msg91_service');
    $sms_provider = \Drupal::service('sms.provider');
    $student_sms_config = \Drupal::config('rte_mis_lottery.settings')->get('notify_student');
    $logger_service = \Drupal::logger('rte_mis_lottery');
    if ($student_sms_config['enable_sms'] ?? FALSE) {
      foreach ($data as $record) {
        try {
          $message = '';
          if (strtolower($record->allocation_status ?? '') == 'allotted') {
            $message = $student_sms_config['alloted_message'] ?? '';
            $template_id = $student_sms_config['alloted_message_template_id'] ?? '';
            $message = str_replace(
              [
                '!application_number',
                '!udise_code',
                '!student_name',
                '!state',
              ],
              [
                $record->student_application_number,
                $record->school_udise_code,
                $record->student_name,
                $record->allocation_status,
              ],
              $message
            );
            $replacements = [
              'STUDENT_NAME' => $record->student_name,
              'STATE' => $record->allocation_status,
              'APPLICATION_ID' => $record->student_application_number,
              'UDISE_CODE' => $record->school_udise_code,
            ];
          }
          elseif (strtolower($record->allocation_status ?? '') == 'un-alloted') {
            $message = $student_sms_config['un_alloted_message'] ?? '';
            $template_id = $student_sms_config['unalloted_message_template_id'] ?? '';
            $message = str_replace(
              [
                '!application_number',
                '!student_name',
              ],
              [
                $record->student_application_number,
                $record->student_name,
              ],
              $message
            );
            $replacements = [
              'STUDENT_NAME' => $record->student_name,
              'STATE' => $record->allocation_status,
              'APPLICATION_ID' => $record->student_application_number,
              'UDISE_CODE' => $record->school_udise_code,
            ];
          }
          if (!empty($message) && !empty($record->mobile_number)) {
            if (!empty($template_id)) {
              $mobile_number = $record->mobile_number;
              $response = $msg91Service->sendMessage(
                $record->mobile_number,
                $message,
                $template_id,
                $replacements
              );
              if (!empty($response['type']) && $response['type'] === 'success') {
                $logger_service->info('SMS sent successfully to @num using template @tid', [
                  '@num' => $mobile_number,
                  '@tid' => $template_id ?? 'fallback',
                ]);
              }
              // Failure handling.
              $logger_service->warning('SMS failed to sent. via MSG91. Response: @res', [
                '@res' => print_r($response, TRUE),
              ]);
            }
            else {
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
