<?php

namespace Drupal\rte_mis_lottery\Batch;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eck\EckEntityInterface;

/**
 * Prepare the data for lottery.
 */
class PrepareLotteryData {
  use StringTranslationTrait;

  /**
   * Processes batch of student entries.
   */
  public static function rteMisLotteryProcessStudent(array $entity_ids, array &$context) {
    $mini_node_storage = \Drupal::entityTypeManager()->getStorage('mini_node');
    if (!isset($context['results']['rows']['students'])) {
      $context['results']['rows']['students'] = [];
    }
    foreach ($entity_ids as $entity_id) {
      $student = $mini_node_storage->load($entity_id);
      if ($student instanceof EckEntityInterface) {
        $school_preferences = [];
        foreach ($student->get('field_school_preferences')->referencedEntities() as $school_preference) {
          $school_preferences[] = [
            'school_id' => $school_preference->get('field_school_id')->getString(),
            'medium' => $school_preference->get('field_medium')->getString(),
            'entry_class' => $school_preference->get('field_entry_class')->getString(),
          ];
        }
        $context['results']['rows']['students'][] = [
          'id' => $student->id(),
          'student_name' => $student->get('field_student_name')->getString(),
          'mobile' => $student->get('field_mobile_number')->local_number,
          'application_number' => $student->get('field_student_application_number')->getString(),
          'location' => $student->get('field_location')->getString(),
          'preference' => $school_preferences,
        ];
      }
    }
    $context['message'] = t('Randomizing Students...');
  }

  /**
   * Processes batch of school entries.
   */
  public static function rteMisLotteryProcessSchool(array $entity_ids, array &$context) {
    // Get the language from default option config.
    $school_config = \Drupal::config('rte_mis_school.settings');
    $languages = $school_config->get('field_default_options.field_medium') ?? [];

    $mini_node_storage = \Drupal::entityTypeManager()->getStorage('mini_node');
    if (!isset($context['results']['rows']['schools'])) {
      $context['results']['rows']['schools'] = [];
    }
    foreach ($entity_ids as $entity_id) {
      $school = $mini_node_storage->load($entity_id);
      if ($school instanceof EckEntityInterface) {
        $rte_seats = [];
        foreach ($school->get('field_entry_class')->referencedEntities() as $entry_class) {
          foreach ($languages as $key => $language) {
            $rte_seats[$entry_class->get('field_entry_class')->getString()]['rte_seat'][$key] = $entry_class->get('field_rte_student_for_' . $key)->getString();
          }
        }
        $context['results']['rows']['schools'][] = [
          'school_name' => $school->get('field_school_name')->getString(),
          'udise_code' => $school->get('field_udise_code')->getString(),
          'id' => $school->id(),
          'location' => $school->get('field_location')->getString(),
          'entry_class' => $rte_seats,
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
      if (!empty($results['rows'])) {
        $kv_store = \Drupal::service('keyvalue.expirable')->get('rte_mis_lottery');
        $kv_store->setWithExpire('student-list', $results['rows']['students'], 3600);
        $kv_store->setWithExpire('school-list', $results['rows']['schools'], 3600);
      }

    }
    else {
      \Drupal::messenger()->addMessage(t('An error occurred during randomizing student.'));
    }
  }

}
