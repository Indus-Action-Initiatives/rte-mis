<?php

namespace Drupal\rte_mis_lottery\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\rte_mis_core\Helper\RteCoreHelper;

/**
 * Check the access for lottery timeline.
 */
class LotteryAccessCheck implements AccessInterface {

  /**
   * Core helper.
   *
   * @var \Drupal\rte_mis_Core\Helper\RteCoreHelper
   */
  protected $rteCoreHelper;

  /**
   * Constructs an LotteryAccessCheck object.
   *
   * @param \Drupal\rte_mis_core\Helper\RteCoreHelper $rte_core_helper
   *   The rte core helper.
   */
  public function __construct(RteCoreHelper $rte_core_helper) {
    $this->rteCoreHelper = $rte_core_helper;
  }

  /**
   * Checks access to the lottery page based on campaign.
   */
  public function access(AccountInterface $account, RouteMatchInterface $routeMatch) {
    $lottery_timeline_status = $this->rteCoreHelper->isAcademicSessionValid('lottery');
    if ($lottery_timeline_status) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden('Lottery window is either closed or not open.');
  }

}
