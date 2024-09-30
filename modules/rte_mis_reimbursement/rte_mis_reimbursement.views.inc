<?php

/**
 * @file
 * Contains rte_mis_reimbursement.views.inc.
 */

/**
 * Provide a custom views field data for reimbursement balance amount field.
 */

/**
 * Implements hook_views_data().
 */
function rte_mis_reimbursement_views_data() {
  $data['views']['table']['group'] = t('RTE MIS Reimbursement');
  $data['views']['table']['join'] = [
    '#global' => [],
  ];

  $data['views']['rte_mis_reimbursement_balance_amount'] = [
    'title' => t('Balance'),
    'help' => t('Add reimbursement balance amount field to show balance amount.'),
    'field' => [
      'id' => 'rte_mis_reimbursement_balance_amount',
    ],
  ];

  return $data;
}
