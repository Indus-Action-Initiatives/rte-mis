<?php

namespace Drupal\rte_mis_lottery\Controller;

use Drupal\Core\Controller\ControllerBase;

class TestController {

  public function test() {
    $kv_store = \Drupal::service('keyvalue.expirable')->get('rte_mis_lottery');
    $results = $kv_store->get('student-list', []);
    $queueFactory = \Drupal::service('queue');
    $queue = $queueFactory->get('test_queue_processor');
    $batch_size = 100;
    // Split the result into smaller batches.
    $chunks = array_chunk($results , $batch_size);
    foreach ($chunks as $chunk) {
      $queue->createItem($chunk);
    }
    return [
      '#markup' => '<h1>Hello World</h1>'
    ];
  }
}
