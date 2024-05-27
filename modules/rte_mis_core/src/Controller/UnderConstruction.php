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
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['under-construction-wrapper']],
      '#markup' => 'This page is under construction!',
    ];
  }

}
