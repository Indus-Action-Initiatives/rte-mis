<?php

namespace Drupal\rte_mis_student_tracking\Batch;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\mobile_number\Exception\MobileNumberException;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 *
 */
class StudentTrackingBatch {
  use StringTranslationTrait;

  /**
   * Import interdependent field in batch.
   *
   * @param int $fileId
   *   File id.
   * @param array $details
   *   The addition detail required for batch process.
   * @param array $context
   *   The batch context.
   */
  public static function import(int $fileId, array &$context) {
    $file = File::load($fileId);
    if ($file instanceof FileInterface) {
      $inputFileName = \Drupal::service('file_system')->realpath($file->getFileUri());
      // Identify the type of $inputFileName.
      $inputFileType = IOFactory::identify($inputFileName);
      // Create a new Reader of the type that has been identified.
      $reader = IOFactory::createReader($inputFileType);
      // Load $inputFileName to a Spreadsheet Object.
      $reader->setReadDataOnly(TRUE);
      $reader->setReadEmptyCells(FALSE);
      $spreadsheet = $reader->load($inputFileName);
      $sheetData = $spreadsheet->getActiveSheet();
      // Get the maximum number of row with data.
      $maxRow = $sheetData->getHighestDataRow();
      // Initialize batch process if already not in-progress.
      if (!isset($context['sandbox']['progress'])) {
        $context['sandbox']['progress'] = 0;
        $context['sandbox']['max'] = ($maxRow - 1);
        $context['sandbox']['objects'] = $maxRow == 1 ? [] : range(2, $maxRow);
      }
      $student_default_options = \Drupal::config('rte_mis_student.settings')->get('field_default_options') ?? [];
      $school_default_options = \Drupal::config('rte_mis_school.settings')->get('field_default_options') ?? [];
      $student_tracking_config = \Drupal::config('rte_mis_student_tracking.settings');
      $class_level = $school_default_options['class_level'] ?? [];
      $allowed_class_list = $student_tracking_config->get('allowed_class_list') ?? [];
      $util = \Drupal::service('mobile_number.util');
      $mobile_otp_service = \Drupal::service('rte_mis_student.mobile_otp_service');
      // Process 50 or item remaining.
      $count = min(50, count($context['sandbox']['objects']));
      for ($i = 1; $i <= $count; $i++) {
        $rowNumber = array_shift($context['sandbox']['objects']);
        // $missing_values = [];
        for ($j = 1; $j <= 13; $j++) {
          $value = $sheetData->getCell([$j, $rowNumber])->getValue();
          if (empty(trim($value))) {
            // $missing_values[] =
          }
          else {
            switch ($j) {
              case 1:
                $student_name = $value;
                break;

              case 2:
                $date = \DateTime::createFromFormat('d/m/Y', $value);
                $dob = $date->format('Y-m-d');
                break;

              case 3:
                $value = strtolower($value);
                if (isset($student_default_options['field_gender'][$value])) {
                  $gender = $value;
                }
                else {

                }
                break;

              case 4:
                $value = strtolower($value);
                if (isset($student_default_options['field_religion'][$value])) {
                  $religion = $value;
                }
                else {

                }
                break;

              case 5:
                $value = strtolower($value);
                if (isset($student_default_options['field_caste'][$value])) {
                  $caste = $value;
                }
                else {

                }
                break;

              case 6:
                $parent_name = $value;
                break;

              case 7:
                try {
                  $phoneNumber = $util->testMobileNumber($value, 'IN');
                  $mobile = $mobile_otp_service->getCallableNumber($phoneNumber);
                }
                catch (MobileNumberException $e) {

                }

                break;

              case 8:
                $address = $value;
                break;

              case 9:
                $udise_code = $value;
                break;

              case 10:
                $index = array_search($value, $class_level);
                if (in_array($index, $allowed_class_list)) {
                  $entry_class = $value;
                }
                else {

                }

                break;

              case 11:
                $index = array_search($value, $class_level);
                if (in_array($index, $allowed_class_list)) {
                  $current_class = $value;
                }
                else {

                }

                break;

              case 12:
                $entry_year = $value;
                break;

              case 13:
                if (isset($school_default_options['field_medium'][strtolower($value)])) {
                  $medium = $value;
                }
                else {

                }
                break;

              default:
                break;
            }
          }
        }
        // $student_name = $sheetData->getCell([1, $rowNumber])->getValue();
        // $dob = $sheetData->getCell([2, $rowNumber])->getValue();
        // $gender = $sheetData->getCell([3, $rowNumber])->getValue();
        // $religion = $sheetData->getCell([4, $rowNumber])->getValue();
        // $caste = $sheetData->getCell([5, $rowNumber])->getValue();
        // $parent_name = $sheetData->getCell([6, $rowNumber])->getValue();
        // $mobile = $sheetData->getCell([7, $rowNumber])->getValue();
        // $address = $sheetData->getCell([8, $rowNumber])->getValue();
        // $udise_code = $sheetData->getCell([9, $rowNumber])->getValue();
        // $entry_class = $sheetData->getCell([10, $rowNumber])->getValue();
        // $current_class = $sheetData->getCell([11, $rowNumber])->getValue();
        // $entry_year = $sheetData->getCell([12, $rowNumber])->getValue();
        // $medium = $sheetData->getCell([13, $rowNumber])->getValue();
        if (empty($student_name) || empty($dob) || empty($gender) || empty($religion) || empty($caste) || empty($parent_name) || empty($mobile) || empty($address) || empty($udise_code) || empty($entry_class) || empty($current_class) || empty($entry_year)|| empty($medium)) {
          // FAILED CONDITION.
          $x = 34;
        }
        else {
          try {
            $mini_node = \Drupal::entityTypeManager()->getStorage('mini_node')->create([
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
              'field_promoted_class' => '',
              'field_religion' => $religion,
              'field_residential_address' => $address,
              'field_school' => '',
              'field_school_name' => '',
              'field_school_udise_code' => $udise_code,
              'field_student_name' => $student_name,
            ])->save();
            $context['results']['passed'][] = $mini_node;
          }
          catch (\Exception $e) {
            $context['results']['failed'][] = $mini_node;
          }

        }
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
      \Drupal::messenger()->addMessage(t('Success'));
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
