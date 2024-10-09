<?php

namespace Drupal\rte_mis_reimbursement\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\rte_mis_reimbursement\Services\RteReimbursementHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A queue worker to reset reimbursement claims.
 *
 * @QueueWorker(
 *   id = "reset_reimbursement_claim_queue",
 *   title = @Translation("Reset Reimbursement Claim Queue")
 * )
 */
class ResetReimbursementClaimQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The rte mis helper service.
   *
   * @var \Drupal\rte_mis_reimbursement\Services\RteReimbursementHelper
   */
  protected $rteReimbursementHelper;

  /**
   * Constructs a new StudentTrackingQueue object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\rte_mis_reimbursement\Services\RteReimbursementHelper $rte_reimbursement_helper
   *   The reimbursement helper service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    RteReimbursementHelper $rte_reimbursement_helper,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->rteReimbursementHelper = $rte_reimbursement_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('rte_mis_reimbursement.reimbursement_helper'),
    );
  }

  /**
   * Processes single queue item.
   *
   * @param array $data
   *   The data contains an array of items containing
   *   school claim mini node ids, message and user ids.
   */
  public function processItem($data) {
    $uid = $data['uid'];
    $message = $data['message'];
    $school_claim_id = $data['id'];
    $this->rteReimbursementHelper->resetReimbursementClaim($school_claim_id, $message, $uid);
  }

}
