<?php

namespace Drupal\rte_mis_reimbursement\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\eck\EckEntityInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Provides Reimbursement Balance Amount field handler.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("rte_mis_reimbursement_balance_amount")
 */
class ReimbursementBalanceAmountField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query(): void {
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): string|MarkupInterface {
    // Balance.
    $balance = 0;
    // Get entity from the view result.
    $reimbursement_claim_entity = $values->_entity;

    // If it is a valid school claim entity.
    if ($reimbursement_claim_entity instanceof EckEntityInterface
      && $reimbursement_claim_entity->bundle() == 'school_claim') {
      $claim_status = $reimbursement_claim_entity->get('field_reimbursement_claim_status')->getString();
      // The balance amount should be calculated only if the reimbursement
      // is approved and payment has been processed.
      if (in_array($claim_status, [
        'reimbursement_claim_workflow_payment_completed',
        'reimbursement_claim_workflow_payment_pending',
      ])) {
        $claim_amount = $reimbursement_claim_entity->get('field_total_fees')->getString() ?? 0;
        $amount_received = $reimbursement_claim_entity->get('field_amount_received')->getString() ?? 0;
        // Don't calculate the balance amount if the reimbursement is
        // approved but amount received is not added.
        if ((float) $amount_received > 0) {
          // Calculate balance amount.
          $balance = (float) $claim_amount - (float) $amount_received;
        }
      }
    }

    // Format the balance amount to contain two decimal points.
    $balance = number_format($balance, 2, '.', '');

    return $balance;
  }

}
