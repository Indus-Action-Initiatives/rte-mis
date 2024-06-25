<?php

namespace Drupal\rte_mis_lottery\Batch;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eck\EckEntityInterface;

/**
 * Batch process to fetch student details.
 */
class RandomizeStudent {
  use StringTranslationTrait;

  /**
   * Processes a single batch of entities.
   */
  public static function rteMisLotteryProcessStudent(array $entity_ids, array &$context) {
    $mini_node_storage = \Drupal::entityTypeManager()->getStorage('mini_node');
    if (!isset($context['results']['rows'])) {
      $context['results']['rows'] = [];
    }
    foreach ($entity_ids as $entity_id) {
      $student = $mini_node_storage->load($entity_id);
      if ($student instanceof EckEntityInterface) {
        $context['results']['rows'][] = [
          'student_name' => $student->get('field_student_name')->getString(),
          'application_number' => $student->get('field_student_application_number')->getString(),
          'mobile_number' => $student->get('field_mobile_number')->local_number,
        ];
      }
    }
    $context['message'] = t('Randomizing Students...');
  }

  /**
   * Callback function for when the batch process finishes.
   */
  public static function rteMisLotteryBatchFinished($success, $results, array $operations) {
    if ($success) {
      \Drupal::messenger()->addMessage(t('Randomizing student completed successfully.'));
      // Process $results['rows'] as needed or store it for later use.
      if (!empty($results['rows'])) {
        $kv_store = \Drupal::service('keyvalue.expirable')->get('rte_mis_lottery');
        $kv_store->setWithExpire('student-list', $results['rows'], 1800);
      }

    }
    else {
      \Drupal::messenger()->addMessage(t('An error occurred during randomizing student.'));
    }
  }

}
