<?php

namespace Drupal\rte_mis_core\Batch;

use Drupal\Core\Config\Config;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
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

  /**
   * Import multiple location field in batch.
   *
   * @param int $fileId
   *   File id.
   * @param array $details
   *   The addition detail required for batch process.
   * @param array $context
   *   The batch context.
   */
  public static function importMultiple(int $fileId, array $details, array &$context) {
    $file = File::load($fileId);
    $userId = $details['userId'] ?? 0;
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
      // Initialize with 0s for all levels.
      $lastNonEmptyParents = array_fill(0, 7, 0);
      for ($i = 1; $i <= $count; $i++) {
        $rowNumber = array_shift($context['sandbox']['objects']);
        // Iterate 7 level and import the location from the file.
        for ($j = 1; $j < 7; $j++) {
          // If value exist at index 6 and categorization is rural, break loop.
          if ($j == 6 && $lastNonEmptyParents[$j - 3] == 'rural') {
            break;
          }
          // Get the value from sheet.
          $value = $sheetData->getCell([$j, $rowNumber])->getValue() ?? '';
          if (!empty($value)) {
            $value = trim($value);
          }
          // Check if location exist or not.
          $existingTerms = LocationTermBatch::checkIfLocationExist($value);
          if (!empty($existingTerms) && $j > 4) {
            // If location exists and the value from 5th and further column is
            // being imported then load the parent of existing term and store in
            // array.
            $parentTermId = [];
            foreach ($existingTerms as $existingTerm) {
              $parentsTerms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadParents($existingTerm);
              $parentsTerm = reset($parentsTerms);
              if ($parentsTerm instanceof TermInterface) {
                $parentTermId[] = $parentsTerm->id();
              }
            }
          }
          // This condition is used to created term based on several conditions.
          // 1. Existing term is empty `or` there exists term `and` current term
          // is 5th and greater `and` parents from existing term is not in the
          // list of previously added parent.
          // 2. Value is not empty.
          // 3. Current index is not 3. It will contain categorization element.
          // 4. Last added element is not FALSE `and` current index is not 4
          // `or`  Current index is 4 `and` Last added element is not FALSE
          // `and` Last second added element is not FALSE(verify category and
          // last element).
          if ((empty($existingTerms) || (!empty($existingTerms) && $j > 4 && !in_array($lastNonEmptyParents[$j - 1], $parentTermId))) && !empty($value) && $j != 3 && (($lastNonEmptyParents[$j - 1] !== FALSE && $j != 4) || ($j == 4 && $lastNonEmptyParents[$j - 2] !== FALSE && $lastNonEmptyParents[$j - 1]))) {
            $data = [
              'name' => $value,
              'vid' => 'location',
              'parent' => [$lastNonEmptyParents[$j - 1]],
            ];

            if ($j == 4) {
              $data += [
                'field_type_of_area' => $lastNonEmptyParents[$j - 1],
              ];
              $data['parent'] = [$lastNonEmptyParents[$j - 2]];
            }
            $term = Term::create($data);
            $term->setRevisionUser(User::load($userId));
            $term->save();
            if ($term) {
              $context['results']['passed'][] = $value;
            }
            else {
              $context['results']['failed'][$rowNumber] .= !isset($context['results']['failed'][$rowNumber]) ? $value : ", $value";
            }
          }
          else {
            $existingTermId = reset($existingTerms);
            $parentsTerm = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadParents($existingTermId);
            if (!empty($parentsTerm)) {
              $parentsTerm = reset($parentsTerm);
              if ($parentsTerm instanceof TermInterface) {
                if ($j == 4 && $parentsTerm->id() == $lastNonEmptyParents[$j - 2]) {
                  $context['results']['passed'][] = $value;
                }
                elseif ($j != 4 && $parentsTerm->id() == $lastNonEmptyParents[$j - 1]) {
                  $context['results']['passed'][] = $value;
                }
                else {
                  if (!isset($context['results']['failed'][$rowNumber]['general'])) {
                    $context['results']['failed'][$rowNumber]['general'] = $value;
                  }
                  else {
                    $context['results']['failed'][$rowNumber]['general'] .= ", $value";
                  }
                  $term = $existingTerms  = NULL;
                  $lastNonEmptyParents[$j] = FALSE;
                }
              }
            }
            // Create error for duplicate term.
            elseif ($j > 1 && $j != 3 && !empty($value)) {
              $lastNonEmptyParents[$j] = FALSE;
              $term = $existingTerms  = NULL;
              if (!isset($context['results']['failed'][$rowNumber]['general'])) {
                $context['results']['failed'][$rowNumber]['general'] = $value;
              }
              else {
                $context['results']['failed'][$rowNumber]['general'] .= ", $value";
              }
            }
            // Create error for invalid category.
            elseif ($j == 3 && ((!empty($value) && !in_array(strtolower($value), [
              'urban', 'rural',
            ])) || $lastNonEmptyParents[$j] === FALSE)) {
              $context['results']['failed'][$rowNumber]['categorization'] = $value;
            }
            else {
              // For 1st level ie district if it is already added.
              $context['results']['passed'][] = $value;
            }
          }
          // Update lastNonEmptyParents based on current and previous level.
          if (!empty($value) && (!empty($existingTerms) || $term instanceof TermInterface || $j == 3)) {
            $lastNonEmptyParents[$j] = isset($term) ? $term->id() : (!empty($existingTerms) ? reset($existingTerms) : ($j == 3 && in_array(strtolower($value), [
              'rural',
              'urban',
            ]) ? strtolower($value) : FALSE));
            $term = NULL;
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
        $context['results']['fileId'] = $fileId;
      }
      // Inform the batch engine that we are not finished,
      // and provide an estimation of the completion level we reached.
      if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
        $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
      }
    }

  }

  /**
   * Check if location term exist or not in location vocabulary.
   *
   * @param string $name
   *   The name of location to look for.
   */
  protected static function checkIfLocationExist(string $name = '') {
    $results = [];
    if (!empty($name)) {
      $results = \Drupal::entityQuery('taxonomy_term')
        ->accessCheck(FALSE)
        ->condition('vid', 'location')
        ->condition('name', $name, 'LIKE')
        ->execute();
    }
    return $results;
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
  public static function multipleLocationFinishedCallback(bool $success, array $results, array $operations) {
    if ($success) {
      if (isset($results['failed'])) {
        $tempstore = \Drupal::service('tempstore.private');
        // Get the store collection.
        $store = $tempstore->get('rte_mis_school');
        // Set the logs in user private storage for next 1 hour.
        $store->set('location_logs', $results['failed'], 3600);

        $failCount = count($results['failed']);
        \Drupal::logger('rte_mis_core')
          ->notice(t('@term failed to import.', [
            '@term' => $failCount,
          ]));
        if (isset($results['fileId'])) {
          $link = Link::createFromRoute(t('here'), 'rte_mis_core.download_location_logs', ['fid' => $results['fileId']]);
          \Drupal::messenger()->addWarning(t('Some of the location failed to import. Click @link to download the logs', [
            '@link' => $link->toString(),
          ]));
        }

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
