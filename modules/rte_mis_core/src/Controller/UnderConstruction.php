<?php

declare(strict_types=1);

namespace Drupal\rte_mis_core\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for RTE-MIS Core routes.
 */
final class UnderConstruction extends ControllerBase {

  /**
   * Builds the response.
   */
  public function __invoke(): array {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('The site is under construction!'),
    ];

    return $build;
  }

}
