<?php

namespace Drupal\rte_mis_lottery\Batch;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eck\EckEntityInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\taxonomy\TermInterface;

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
    $students = $mini_node_storage->loadMultiple($entity_ids);
    foreach ($students as $student) {
      if ($student instanceof EckEntityInterface) {
        // Get the school preference from student.
        $school_preferences = [];
        foreach ($student->get('field_school_preferences')->referencedEntities() as $school_preference) {
          $school_preferences[] = [
            'school_id' => $school_preference->get('field_school_id')->getString(),
            'medium' => $school_preference->get('field_medium')->getString(),
            'entry_class' => $school_preference->get('field_entry_class')->getString(),
          ];
        }
        // Get the parent details.
        $parent_type = $student->get('field_parent_type')->getString();
        $parent_name = NULL;
        switch ($parent_type) {
          case 'father_mother':
            $parent_name = $student->get('field_father_name')->getString();
            break;

          case 'single_parent':
            $single_parent_type = $student->get('field_single_parent_type')->getString();
            if ($single_parent_type == 'father') {
              $parent_name = $student->get('field_father_name')->getString();
            }
            elseif ($single_parent_type == 'mother') {
              $parent_name = $student->get('field_mother_name')->getString();
            }
            break;

          case 'guardian':
            $parent_name = $student->get('field_guardian_name')->getString();
            break;

          default:
            break;
        }
        // Prepare the student data.
        $context['results']['rows']['students'][$student->id()] = [
          'name' => $student->get('field_student_name')->getString(),
          'mobile' => $student->get('field_mobile_number')->local_number,
          'application_id' => $student->get('field_student_application_number')->getString(),
          'location' => $student->get('field_location')->getString(),
          'parent_name' => $parent_name,
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
    $schools = $mini_node_storage->loadMultiple($entity_ids);
    foreach ($schools as $school) {
      if ($school instanceof EckEntityInterface) {
        $rte_seats = $mapped_habitations = [];
        foreach ($school->get('field_entry_class')->referencedEntities() as $entry_class) {
          foreach ($languages as $key => $language) {
            $rte_seats[$entry_class->get('field_entry_class')->getString()]['rte_seat'][$key] = $entry_class->get('field_rte_student_for_' . $key)->getString();
          }
        }
        foreach ($school->get('field_habitations')->referencedEntities() as $term) {
          if ($term instanceof TermInterface) {
            $mapped_habitations[] = $term->id();
          }
        }
        $field_udise_option = [];
        $field_udise_definition = $school->get('field_udise_code')->getFieldDefinition()->getFieldStorageDefinition();
        if ($field_udise_definition instanceof FieldStorageConfig) {
          $field_udise_option = options_allowed_values($field_udise_definition, $school);
        }
        $context['results']['rows']['schools'][$school->id()] = [
          'name' => $school->get('field_school_name')->getString(),
          'udise_code' => $field_udise_option[$school->get('field_udise_code')->getString()],
          'location' => $mapped_habitations,
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
