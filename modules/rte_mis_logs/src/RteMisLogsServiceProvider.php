<?php

namespace Drupal\rte_mis_logs;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

/**
 * Overwrites the logger.filelog service.
 */
class RteMisLogsServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $container->getDefinition('logger.filelog')
      ->setClass('Drupal\rte_mis_logs\Logger\CustomFileLog');
  }

}
