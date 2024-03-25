<?php

namespace Drupal\rte_mis_core\Batch;

use Drupal\Core\Config\Config;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * This class handle batch process for location vocabulary.
 */
class LocationTermBatch {
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
  public static function import(int $fileId, array $details, array &$context) {
    $file = File::load($fileId);
    $parentTermId = $details['parentTermId'] ?? NULL;
    $locationSchemaTerm = $details['locationSchemaTerm'] ?? NULL;
    $userId = $details['userId'] ?? 0;
    if ($file instanceof FileInterface) {
      $rteMisCoreConfig = \Drupal::config('rte_mis_core.settings');
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
        // Get the data from first column.
        $name = $sheetData->getCell([1, $rowNumber])->getValue();
        if (!empty(trim($name))) {
          // Check if term exist or not.
          $existingTerm = \Drupal::entityQuery('taxonomy_term')
            ->accessCheck(FALSE)
            ->condition('vid', 'location')
            ->condition('name', trim($name), 'LIKE')
            ->execute();
          if (empty($existingTerm)) {
            try {
              // Prepare data for term.
              $data = [
                'name' => trim($name),
                'vid' => 'location',
                'parent' => [$parentTermId],
              ];
              // Check the categorization feature store in config.
              if ($rteMisCoreConfig instanceof Config) {
                // Check if, this is enabled.
                $enableCategorization = $rteMisCoreConfig->get('location_schema.enable');
                if ($enableCategorization) {
                  // Get the list of location_schema need to be tagged as rural.
                  $rural = $rteMisCoreConfig->get('location_schema.rural') ?? NULL;
                  // Get the list of location_schema need to be tagged as urban.
                  $urban = $rteMisCoreConfig->get('location_schema.urban') ?? NULL;
                  if ($locationSchemaTerm == $rural) {
                    $data += [
                      'field_type_of_area' => 'rural',
                    ];
                  }
                  elseif ($locationSchemaTerm == $urban) {
                    $data += [
                      'field_type_of_area' => 'urban',
                    ];
                  }
                }
              }
              // Create new term if it does not exist.
              $term = Term::create($data);
              $term->setRevisionUser(User::load($userId));
              $term->save();
              if ($term) {
                $context['results']['passed'][] = $name;
              }
              else {
                $context['results']['failed'][] = $name;
              }
            }
            catch (\Exception $e) {
              $context['results']['failed'][] = $name;
              \Drupal::logger('rte_mis_core')
                ->notice(t('@term failed to import. Error: @error', [
                  '@term' => $name,
                  '@error' => $e,
                ]));
            }
          }
          else {
            $context['results']['failed'][] = $name;
          }
        }
        else {
          $context['results']['failed'][] = $name;
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
        \Drupal::logger('rte_mis_core')
          ->notice(t('@term failed to import.', [
            '@term' => $failCount,
          ]));
        $message = \Drupal::translation()->formatPlural(
          $failCount,
          'Failed to import @count location as it already exist. Here is the location: @code.', 'Failed to import @count locations as they already exist. Here are the locations @code.', [
            '@count' => $failCount,
            '@code' => implode(', ', $results['failed']),
          ]
        );
        \Drupal::messenger()->addWarning($message);
      }
      if (isset($results['passed'])) {
        $passCount = count($results['passed']);
        \Drupal::logger('rte_mis_core')
          ->notice(t('@successCount term imported successfully.', [
            '@successCount' => $passCount,
          ]));
        $message = \Drupal::translation()->formatPlural(
            $passCount,
            '@count location imported successfully.', '@count locations imported successfully.', [
              '@count' => $passCount,
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
