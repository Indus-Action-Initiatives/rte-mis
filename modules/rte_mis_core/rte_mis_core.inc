<?php

/**
 * @file
 * Contains rte_mis_core.inc.
 */

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Callback to get the list of allowed values.
 *
 * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $definition
 *   The field storage definition.
 * @param \Drupal\Core\Entity\FieldableEntityInterface|null $entity
 *   The entity the field belongs to.
 *
 * @return array
 *   An associative array of allowed values.
 */
function rte_mis_core_get_allowed_values(FieldStorageDefinitionInterface $definition, ?FieldableEntityInterface $entity = NULL) {
  $field_name = $definition->getName();

  // Load configurations.
  $school_config = \Drupal::config('rte_mis_school.settings') ?? NULL;
  $tracking_config = \Drupal::config('rte_mis_student_tracking.settings') ?? NULL;
  $allocation_config = \Drupal::config('rte_mis_allocation.settings') ?? NULL;
  $reimbursement_config = \Drupal::config('rte_mis_reimbursement.settings') ?? NULL;

  $options = [];

  switch ($field_name) {
    case 'field_academic_session':
    case 'field_academic_year_allocation':
      $currentAcademicYear = _rte_mis_core_get_current_academic_year();
      $options[$currentAcademicYear] = str_replace('_', '-', $currentAcademicYear);
      break;

    case 'field_entry_year':
    case 'field_academic_session_tracking':
      $currentAcademicYear = _rte_mis_core_get_current_academic_year();
      [$previous_year, $current_year] = explode('_', $currentAcademicYear);
      for ($i = 0; $i < 15; $i++) {
        $options["{$previous_year}_{$current_year}"] = "{$previous_year}-{$current_year}";
        $previous_year--;
        $current_year--;
      }
      return $options;

    case 'field_medium':
      if ($school_config) {
        $options = $school_config->get('field_default_options.field_medium') ?? [];
      }
      break;

    case 'field_current_class':
      $options = rte_mis_school_get_education_level_options();
      break;

    case 'field_entry_class_for_allocation':
      if ($tracking_config && $school_config) {
        $selected_class = $tracking_config->get('allowed_class_list');
        $entry_class = $school_config->get('field_default_options.class_level') ?? [];
        sort($selected_class);
        foreach ($selected_class as $class) {
          if (isset($entry_class[$class])) {
            $options[] = $entry_class[$class];
          }
        }
      }
      break;

    case 'field_final_grade':
      if ($tracking_config) {
        $options = $tracking_config->get('final_grade') ?? [];
      }
      break;

    case 'field_dropout_reason':
    case 'field_is_migrating':
      if ($allocation_config) {
        $options = $allocation_config->get('field_default_options')[$field_name] ?? [];
      }
      break;

    case 'field_fees_type':
    case 'field_additional_fees':
      if ($reimbursement_config) {
        $options = $reimbursement_config->get('default_fees_options') ?? [];
      }
      break;

    case 'field_academic_session_claim':
      if ($reimbursement_config) {
        $previousAcademicYear = _rte_mis_core_get_previous_academic_year();
        $year = $reimbursement_config->get('allowed_time') ?? [];
        [$previous_year_back, $previous_year] = explode('_', $previousAcademicYear);
        for ($i = 0; $i < $year; $i++) {
          $options["{$previous_year_back}_{$previous_year}"] = "{$previous_year_back}-{$previous_year}";
          $previous_year_back--;
          $previous_year--;
        }
        return $options;

      }
      break;
  }

  return $options;
}
