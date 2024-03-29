<?php

namespace Drupal\rte_mis_school\Batch;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
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
        // Get the aid status from third column.
        $aidStatus = $sheetData->getCell([3, $rowNumber])->getValue();
        // Get the minority status name from fourth column.
        $minorityStatus = $sheetData->getCell([4, $rowNumber])->getValue();
        // Get the type of area from fifth column.
        $typeOfArea = $sheetData->getCell([5, $rowNumber])->getValue();
        // Get the type of area from sixth column.
        $district = $sheetData->getCell([6, $rowNumber])->getValue();
        // Get the type of area from seventh column.
        $block = $sheetData->getCell([7, $rowNumber])->getValue();
        // Validate parameter before creating school udise code.
        $validAidStatus = static::getValidListValue($aidStatus, 'field_aid_status');
        $validMinorityStatus = static::getValidListValue($minorityStatus, 'field_minority_status');
        $validTypeOfArea = static::getValidListValue($typeOfArea, 'field_type_of_area');
        $blockTid = static::getBlockIdLocation($district, $block);
        $errors = [];
        if (strlen($udiseCode) > 11) {
          $errors[] = t('UDISE code should not be more than 11 digits.');
        }
        if (!is_numeric($udiseCode)) {
          $errors[] = t('UDISE code should be numeric.');
        }
        if (empty(trim($schoolName))) {
          $errors[] = t('School name is empty.');
        }
        if (!$validAidStatus) {
          $errors[] = t('Invalid aid status.');
        }
        if (!$validTypeOfArea) {
          $errors[] = t('Invalid type of area.');
        }
        if (!$validMinorityStatus) {
          $errors[] = t('Invalid minority status.');
        }
        if (!$blockTid) {
          $errors[] = t('Invalid district or block.');
        }
        if (!empty(trim($udiseCode)) && !empty(trim($schoolName)) && is_numeric($udiseCode) && $validAidStatus && $validTypeOfArea && $validMinorityStatus && $blockTid) {
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
                'name' => trim($udiseCode),
                'field_school_name' => trim($schoolName),
                'vid' => 'school_udise_code',
                'field_ip_address' => \Drupal::request()->getClientIp(),
                'field_workflow' => 'school_udise_code_workflow_approved',
                'field_upload_type' => 'bulk_upload',
                'field_minority_status' => $validMinorityStatus,
                'field_type_of_area' => $validTypeOfArea,
                'field_aid_status' => $validAidStatus,
                'field_location' => $blockTid,
              ]);
              $term->setRevisionUser(User::load($userId));
              $term->save();
              if ($term) {
                $context['results']['passed'][] = $udiseCode;
              }
              else {
                $context['results']['failed'][$udiseCode][] = t("Issue while creating School Udise Code");
              }
            }
            catch (\Exception $e) {
              $context['results']['failed'][$udiseCode][] = t("Issue while creating School Udise Code");
              \Drupal::logger('rte_mis_school')
                ->warning(t('@udiseCode school code failed to import. Error: @error', [
                  '@udiseCode' => $udiseCode,
                  '@error' => $e,
                ]));
            }
          }
          else {
            $context['results']['failed'][$udiseCode][] = t("This School Udise Code already exist.");
          }
        }
        else {
          $context['results']['failed'][$udiseCode] = $errors;
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
   * Validate the input for field type list.
   *
   * @param string $str
   *   Input String.
   * @param string $fieldName
   *   Machine name of field.
   */
  protected static function getValidListValue($str, $fieldName = NULL) {
    if (!empty($str) && !empty($fieldName)) {
      // Load the field storage definition.
      $fieldStorage = \Drupal::entityTypeManager()
        ->getStorage('field_storage_config')
        ->loadByProperties(['field_name' => $fieldName]);
      if (!empty($fieldStorage)) {
        $fieldStorage = reset($fieldStorage);
        // Get the callback defined in the field storage.
        $function = $fieldStorage->getSetting('allowed_values_function');
        // Get the option defined in the field storage.
        $values = $fieldStorage->getSetting('allowed_values');
        // Get the option from callback defined.
        if (!empty($function)) {
          $values = $function($fieldStorage);
        }
        foreach ($values as $key => $value) {
          $result = Unicode::strcasecmp(trim($str), $value);
          if ($result === 0) {
            return $key;
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * Validate district and block received.
   *
   * @param string $district
   *   District Name.
   * @param string $block
   *   Block name.
   */
  protected static function getBlockIdLocation($district, $block) {
    if (!empty($district) && !empty($block)) {
      $query = \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', 'location')
        ->condition('name', trim($district), 'LIKE')
        ->condition('parent', 0)
        ->accessCheck(FALSE);
      $locationTid = $query->execute();
      if (!empty($locationTid)) {
        $locationTid = reset($locationTid);
        $query = \Drupal::entityQuery('taxonomy_term')
          ->condition('vid', 'location')
          ->condition('name', trim($block), 'LIKE')
          ->condition('parent', $locationTid)
          ->accessCheck(FALSE);
        $blockTid = $query->execute();
        if (!empty($blockTid)) {
          return reset($blockTid);
        }
      }
    }
    return FALSE;
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
      if (isset($results['passed'])) {
        $passCount = count($results['passed']);
        // Listed down the passed udise seperated by commas.
        $passed = implode(', ', $results['passed']);
        \Drupal::logger('rte_mis_school')
          ->notice(t('@successCount school code imported successfully. Here are the codes @code', [
            '@successCount' => $passCount,
            '@code' => $passed,
          ]));
        \Drupal::messenger()
          ->addStatus(t('@count school code imported successfully.', [
            '@count' => $passCount,
          ]));
      }
      // @todo List down the udise code based on distrint errors.
      if (isset($results['failed'])) {
        $failCount = count($results['failed']);

        $title = '';
        $final_error_messages = [];
        foreach ($results['failed'] as $udiseCode => $errors) {
          $markup = [
            '#type' => 'markup',
            '#markup' => "$failCount school code failed to import. Here are the list of Udise Code with error messages:",
          ];

          $list_items = [
            '#theme' => 'item_list',
            '#title' => 'Udise Code - ' . $udiseCode,
            '#items' => $errors,
          ];
          // Render the list of error messages.
          $renderer = \Drupal::service('renderer');
          $title = $renderer->render($markup);
          $final_error_messages[] = $list_items;

        }

        $url = Url::fromUri('internal:/failed-bulk-udise-upload');
        $link = Link::fromTextAndUrl('here', $url)->toString();

        $markup = [
          '#type' => 'markup',
          '#markup' => t("Bulk Upload Failed. Click @link to see the list of errors.", ['@link' => $link]),
        ];
        \Drupal::logger('Bulk Udise Upload Failed')->notice($title . "\n" . $renderer->render($final_error_messages));
        \Drupal::messenger()->addWarning($renderer->render($markup));

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
