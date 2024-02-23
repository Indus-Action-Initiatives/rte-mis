<?php

namespace Drupal\rte_mis_school\Batch;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * This class handle batch process for school UDISE code.
 */
class SchoolUdiseCodeBatch {
  use StringTranslationTrait;

  /**
   * Import school UDISE code in batch.
   *
   * @param int $fileId
   *   File id.
   * @param int $userId
   *   User id.
   * @param array $context
   *   The batch context.
   */
  public static function import(int $fileId, int $userId, array &$context) {
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
      // Process 50 or item remaining.
      $count = min(50, count($context['sandbox']['objects']));
      for ($i = 1; $i <= $count; $i++) {
        $rowNumber = array_shift($context['sandbox']['objects']);
        // Get the UDISE code from first column.
        $udiseCode = $sheetData->getCell([1, $rowNumber])->getValue();
        // Get the school name from second column.
        $schoolName = $sheetData->getCell([2, $rowNumber])->getValue();
        if (!empty(trim($udiseCode)) && !empty($schoolName) && is_numeric($udiseCode)) {
          // Check if UDISE code exist or not.
          $existingTerm = \Drupal::entityQuery('taxonomy_term')
            ->accessCheck(FALSE)
            ->condition('vid', 'school_udise_code')
            ->condition('name', $udiseCode)
            ->execute();
          if (empty($existingTerm)) {
            try {
              // Create new UDISE code if it does not exist.
              // Also store user ip, mark upload_type as `bulk_upload` and
              // set workflow status to `approved`.
              $term = Term::create([
                'name' => $udiseCode,
                'field_school_name' => $schoolName,
                'vid' => 'school_udise_code',
                'field_ip_address' => \Drupal::request()->getClientIp(),
                'field_workflow' => 'school_udise_code_workflow_approved',
                'field_upload_type' => 'bulk_upload',
              ]);
              $term->setRevisionUser(User::load($userId));
              $term->save();
              if ($term) {
                $context['results']['passed'][] = $udiseCode;
              }
              else {
                $context['results']['failed'][] = $udiseCode;
              }
            }
            catch (\Exception $e) {
              $context['results']['failed'][] = $udiseCode;
              \Drupal::logger('rte_mis_school')
                ->notice(t('@udiseCode school code failed to import. Error: @error', [
                  '@udiseCode' => $udiseCode,
                  '@error' => $e,
                ]));
            }
          }
          else {
            $context['results']['failed'][] = $udiseCode;
          }
        }
        else {
          $context['results']['failed'][] = $udiseCode;
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
        $failCount = count($results['failed']);
        \Drupal::logger('rte_mis_school')
          ->notice(t('@failedCount school code failed to import.', [
            '@failedCount' => $failCount,
          ]));
        \Drupal::messenger()->addWarning(t('@count school code failed to import. Here are the codes @code', [
          '@count' => $failCount,
          '@code' => implode(', ', $results['failed']),
        ]));
      }
      if (isset($results['passed'])) {
        $passCount = count($results['passed']);
        \Drupal::logger('rte_mis_school')
          ->notice(t('@successCount school code imported successfully.', [
            '@successCount' => $passCount,
          ]));
        \Drupal::messenger()
          ->addStatus(t('@count school code imported successfully.', [
            '@count' => $passCount,
          ]));
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
