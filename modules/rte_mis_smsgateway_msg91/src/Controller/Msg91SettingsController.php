<?php

namespace Drupal\rte_mis_smsgateway_msg91\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns instructions for configuring the MSG91 Gateway.
 */
class Msg91SettingsController extends ControllerBase {

  /**
   * Displays a message linking to the SMS Framework Gateway configuration page.
   *
   * @return array
   *   A render array containing the configuration link.
   */
  public function content(): array {
    return [
      '#markup' => $this->t(
        'You can configure the MSG91 Gateway under <a href=":url">SMS Framework Gateways</a>.',
        [':url' => '/admin/config/smsframework/gateways']
      ),
    ];
  }

}
