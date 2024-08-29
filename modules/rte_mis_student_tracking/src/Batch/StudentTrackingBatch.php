<?php

namespace Drupal\rte_mis_student_tracking\Batch;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\mobile_number\Exception\MobileNumberException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Class to handle batch processing for stundent tracking.
 */
class StudentTrackingBatch {
  use StringTranslationTrait;

  /**
   * Import interdependent field in batch.
   *
   * @param int $file_id
   *   File id.
   * @param array $context
   *   The batch context.
   */
  public static function import(int $file_id, array &$context) {
    $file = File::load($file_id);
    if ($file instanceof FileInterface) {
      $input_file_name = \Drupal::service('file_system')->realpath($file->getFileUri());
      // Identify the type of uploaded file.
      $input_file_type = IOFactory::identify($input_file_name);
      // Create a new Reader of the type that has been identified.
      $reader = IOFactory::createReader($input_file_type);
      // Load $input_file_name to a Spreadsheet Object.
      $reader->setReadDataOnly(TRUE);
      $reader->setReadEmptyCells(FALSE);
      $spreadsheet = $reader->load($input_file_name);
      // Get the first sheet.
      $sheet_data = $spreadsheet->getSheet(0);
      // Get the maximum number of row with data.
      $max_row = $sheet_data->getHighestDataRow();
      // Initialize batch process if already not in-progress.
      if (!isset($context['sandbox']['progress'])) {
        $context['sandbox']['progress'] = 0;
        $context['sandbox']['max'] = $max_row - 1;
        $context['sandbox']['objects'] = $max_row == 1 ? [] : range(2, $max_row);
      }
      $student_default_options = \Drupal::config('rte_mis_student.settings')->get('field_default_options') ?? [];
      $school_default_options = \Drupal::config('rte_mis_school.settings')->get('field_default_options') ?? [];
      $student_tracking_config = \Drupal::config('rte_mis_student_tracking.settings');
      $class_level = $school_default_options['class_level'] ?? [];
      $allowed_class_list = $student_tracking_config->get('allowed_class_list') ?? [];
      $util = \Drupal::service('mobile_number.util');
      $mobile_otp_service = \Drupal::service('rte_mis_student.mobile_otp_service');
      $mini_node_storage = \Drupal::entityTypeManager()->getStorage('mini_node');

      // Process 50 or item remaining.
      $count = min(50, count($context['sandbox']['objects']));

      // Store required columns mapping, used to get the column name
      // to show those in error messages.
      $columns = [
        'Student name',
        'DOB',
        'Gender',
        'Caste',
        'Parent name',
        'Mobile number',
        'Address',
        'UDISE code',
        'Entry class',
        'Current class',
        'Entry year',
        'Medium',
        'Application number',
      ];

      // Iterate over the items.
      for ($row = 1; $row <= $count; $row++) {
        $row_number = array_shift($context['sandbox']['objects']);
        $missing_values = [];
        $errors = [];
        // Declaring variables to store field values for student
        // performance mini node.
        $student_name = $dob = $religion = $gender = $caste = $parent_name = $mobile = $address = $udise_code = $entry_class = $entry_year = $current_class = $medium = '';
        for ($col = 1; $col <= 14; $col++) {
          $value = $sheet_data->getCell([$col, $row_number])->getValue();
          // Check if the value for this field is empty or not.
          // Skip this check for optional field religion.
          if (empty(trim($value)) && $col != 14) {
            $missing_values[] = $columns[$col - 1];
          }
          else {
            switch ($col) {
              // Student's name.
              case 1:
                $student_name = $value;
                break;

              // Student's date of birth.
              case 2:
                $date = \DateTime::createFromFormat('d/m/Y', $value);
                // In xlsx or excel format the date is read as excel
                // timestamp format to process that we need to convert
                // it to Unix timestamp and then proceed.
                if (!$date) {
                  $timestamp = Date::excelToTimestamp($value);
                  $date = DrupalDateTime::createFromTimestamp($timestamp);
                }
                $dob = $date->format('Y-m-d');
                break;

              // Student's gender.
              case 3:
                $value = strtolower($value);
                if (isset($student_default_options['field_gender'][$value])) {
                  $gender = $value;
                }
                else {
                  $errors[] = t('Invalid gender for the student.');
                }
                break;

              // Student's caste.
              case 4:
                $value = strtolower($value);
                if (isset($student_default_options['field_caste'][$value])) {
                  $caste = $value;
                }
                else {
                  $errors[] = t('Invalid caste for the student.');
                }
                break;

              // Student's parent name.
              case 5:
                $parent_name = $value;
                break;

              // Student's mobile number.
              case 6:
                try {
                  $mobile_number = $util->testMobileNumber($value, 'IN');
                  $mobile = $mobile_otp_service->getCallableNumber($mobile_number);
                }
                catch (MobileNumberException $e) {
                  $errors[] = t('Invalid mobile number for the student.');
                }
                break;

              // Student's address.
              case 7:
                $address = $value;
                break;

              // Student's school UDISE code.
              case 8:
                // Check if school UDISE code is numeric.
                if (!is_numeric($value)) {
                  $errors[] = t('UDISE code must be numeric.');
                }
                elseif (strlen($value) != 11) {
                  $errors[] = t('UDISE code must contain exactly 11 digits.');
                }
                else {
                  $school = [];
                  // Check if school with given UDISE code exists or not.
                  $term = \Drupal::entityQuery('taxonomy_term')
                    ->accessCheck(FALSE)
                    ->condition('vid', 'school')
                    ->condition('name', $value)
                    ->condition('field_workflow', 'school_workflow_approved')
                    ->execute();
                  // Check if school approved by deo exists for the given
                  // academic year.
                  if (!empty($term)) {
                    $term_id = reset($term);
                    $school = $mini_node_storage->getQuery()
                      ->accessCheck(FALSE)
                      ->condition('type', 'school_details')
                      ->condition('field_academic_year', _rte_mis_core_get_previous_academic_year())
                      ->condition('field_school_verification', 'school_registration_verification_approved_by_deo')
                      ->condition('field_udise_code', $term_id)
                      ->condition('status', 1)
                      ->execute();
                  }

                  // Check if current user is school admin.
                  $current_user = \Drupal::currentUser();
                  if (!empty($school) && in_array('school_admin', $current_user->getRoles())) {
                    $user_entity = \Drupal::entityTypeManager()->getStorage('user')->load($current_user->id());
                    $school_entity_id = $user_entity->get('field_school_details')->getValue()[0]['target_id'];
                    // Show error if current user tries to import students
                    // for other schools.
                    if ($school_entity_id != reset($school)) {
                      $errors[] = t('School with the UDISE code @code is not accessible.', [
                        '@code' => $value,
                      ]);
                      break;
                    }
                  }

                  // Save udise code if school exists else show error.
                  if (!empty($school)) {
                    $udise_code = $term_id;
                  }
                  else {
                    $errors[] = t('School with the UDISE code @code does not exist in the given academic year.', [
                      '@code' => $value,
                    ]);
                  }
                }
                break;

              // Student's entry class in the school.
              case 9:
                $index = array_search($value, $class_level);
                if (in_array($index, $allowed_class_list)) {
                  $entry_class = $index;
                }
                else {
                  $errors[] = t('Invalid value for entry class.');
                }
                break;

              // Student's current class in the school.
              case 10:
                $index = array_search($value, $class_level);
                if (in_array($index, $allowed_class_list)) {
                  $current_class = $index;
                }
                else {
                  $errors[] = t('Invalid value for current class.');
                }
                break;

              // Student's entry year in the school.
              case 11:
                $year_range = explode('-', $value);
                // Check if entry year is in correct format.
                // Example for entry year format is 2020-21.
                if (!empty($year_range) && count($year_range) == 2) {
                  if ($year_range[0] < date('Y')) {
                    $entry_year = str_replace('-', '_', $value);
                  }
                  else {
                    $errors[] = t('Entry year must be less than current year.');
                  }
                }
                else {
                  $errors[] = t('Incorrect format for entry year.');
                }
                break;

              // Student's medium.
              case 12:
                if (isset($school_default_options['field_medium'][strtolower($value)])) {
                  $medium = $value;
                }
                else {
                  $errors[] = t('Invalid value for medium.');
                }
                break;

              // Student's application number.
              case 13:
                $application_number = $value;
                break;

              // Student's religion.
              case 14:
                $value = strtolower($value);
                if (empty($value) || isset($student_default_options['field_religion'][$value])) {
                  $religion = $value;
                }
                else {
                  $errors[] = t('Invalid religion for the student.');
                }
                break;

              default:
                break;
            }
          }
        }

        // Abort processing for the current item if any of the required fields
        // is missing or invalid.
        if (!empty($missing_values) || !empty($errors)) {
          $context['results']['failed'][$row_number]['missing_values'] = $missing_values;
          $context['results']['failed'][$row_number]['errors'] = $errors;
        }
        else {
          // Get the school name and school details entity id to refer.
          $school_details = $mini_node_storage->load(reset($school));
          $school_name = $school_details->get('field_school_name')->getString();
          try {
            $mini_node = $mini_node_storage->create([
              'type' => 'student_performance',
              'field_academic_session' => _rte_mis_core_get_previous_academic_year(),
              'field_caste' => $caste,
              'field_current_class' => $current_class,
              'field_date_of_birth' => $dob,
              'field_entry_class_for_allocation' => $entry_class,
              'field_entry_year' => $entry_year,
              'field_gender' => $gender,
              'field_medium' => $medium,
              'field_mobile_number' => $mobile,
              'field_parent_name' => $parent_name,
              'field_residential_address' => $address,
              'field_school' => [
                'target_id' => $school_details->id(),
              ],
              'field_school_name' => $school_name,
              'field_udise_code' => $udise_code,
              'field_student_name' => $student_name,
              'field_religion' => $religion,
              'field_student_application_number' => $application_number,
            ])->save();
            $context['results']['passed'][] = $mini_node;
          }
          catch (\Exception $e) {
            $context['results']['failed'][] = $mini_node;
          }
        }

        // Update our progress information.
        $context['sandbox']['progress']++;
        $context['message'] = t(
          'Completed @current out of @max',
          [
            '@current' => $context['sandbox']['progress'],
            '@max' => $context['sandbox']['max'],
          ]
        );
      }

      if (isset($context['results']['failed'])) {
        $context['results']['file_id'] = $file_id;
      }
      // Inform the batch engine that we are not finished,
      // and provide an estimation of the completion level we reached.
      if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
        $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
      }
    }
  }

  /**
   * Finished callback for the batch processes.
   *
   * @param bool $success
   *   Indicates whether the batch has completed successfully.
   * @param array $results
   *   An array of results gathered by the batch process.
   * @param array $operations
   *   An array of operations that were executed.
   */
  public static function finishedCallback(bool $success, array $results, array $operations) {
    if ($success) {
      if (isset($results['failed'])) {
        $tempstore = \Drupal::service('tempstore.private');
        // Get the store collection.
        $store = $tempstore->get('rte_mis_student_tracking');
        // Set the logs in user private storage for next 1 hour.
        $store->set('students_import_logs', $results['failed'], 3600);

        $fail_count = count($results['failed']);
        \Drupal::logger('rte_mis_core')
          ->notice(t('@count students failed to import.', [
            '@count' => $fail_count,
          ]));
        if (isset($results['file_id'])) {
          $link = Link::createFromRoute(t('here'), 'rte_mis_student_tracking.download_students_import_logs', ['fid' => $results['file_id']]);
          \Drupal::messenger()->addWarning(t('Some of the students failed to import. Click @link to download the logs.', [
            '@link' => $link->toString(),
          ]));
        }

      }
      if (isset($results['passed'])) {
        $pass_count = count($results['passed']);
        \Drupal::logger('rte_mis_core')
          ->notice(t('@successCount students imported successfully.', [
            '@successCount' => $pass_count,
          ]));
        $message = \Drupal::translation()->formatPlural(
            $pass_count,
            '@count student imported successfully.', '@count students imported successfully.', [
              '@count' => $pass_count,
            ]
          );
        \Drupal::messenger()->addStatus($message);
      }
    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      \Drupal::messenger()->addMessage(t('An error occurred while processing %error_operation with arguments: @arguments', [
        '%error_operation' => $error_operation[0],
        '@arguments' => print_r($error_operation[1], TRUE
        ),
      ]));
    }
  }

}
