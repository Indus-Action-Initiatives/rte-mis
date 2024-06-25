<?php

namespace Drupa\rte_mis_lottery\Plugins\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;


/**
 * Processes domain delete tasks.
 *
 * @QueueWorkersss(
 *   id = "test_queue_processor",
 *   title = @Translation("Test Processor"),
 *   cron = {"time" = 240}
 * )
 */
class TestQueueWorker extends QueueWorkerBase {

  /**
   * {@inheritDoc}
   */
  public function processItem($data) {

  }


}
