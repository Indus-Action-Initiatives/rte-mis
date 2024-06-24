<?php

namespace Drupal\rte_mis_lottery\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * A queue worker for testing cron exception handling.
 *
 * @QueueWorker(
 *   id = "student_data_lottery_queue_cron",
 *   title = @Translation("Student Data Lottery Queue"),
 *   cron = {"time" = 1}
 * )
 */
class StudentDataQueue extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // @todo Process the data.
  }

}
