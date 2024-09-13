<?php

/**
 * @file
 * Contains rte_mis_lottery.views.inc.
 */

/**
 * Provide a custom views field data for student application status field.
 */

/**
 * Implements hook_views_data().
 */
function rte_mis_lottery_views_data() {
  $data['views']['table']['group'] = t('RTE MIS Student');
  $data['views']['table']['join'] = [
    '#global' => [],
  ];

  $data['views']['rte_mis_student_application_status'] = [
    'title' => t('Current Status'),
    'help' => t('Add student application status field to show student application status.'),
    'field' => [
      'id' => 'rte_mis_student_application_status',
    ],
  ];

  return $data;
}
