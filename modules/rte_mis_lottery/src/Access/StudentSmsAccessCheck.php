<?php

namespace Drupal\rte_mis_lottery\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Check the access for sending sms to student.
 */
class StudentSmsAccessCheck implements AccessInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs StudentSmsAccessCheck object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Checks access to the SMS form based on config value.
   */
  public function access(AccountInterface $account, RouteMatchInterface $routeMatch) {
    $enable_sms = $this->configFactory->get('rte_mis_lottery.settings')->get('notify_student.enable_sms') ?? 0;
    if ($enable_sms) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden('Student SMS service is disabled.');

  }

}
