<?php

namespace Drupal\rte_mis_reimbursement\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Add the access check in mini_node edit form.
    $route = $collection->get('entity.mini_node.edit_form');
    if ($route instanceof Route) {
      $requirements = $route->getRequirements();
      $requirements['_school_claim_edit_access_check'] = TRUE;
      $route->setRequirements($requirements);
    }
    // Add the access check in mini_node entity view.
    $schoolMiniCanonicalRoute = $collection->get('entity.mini_node.canonical');
    if ($schoolMiniCanonicalRoute instanceof Route) {
      $requirements = $schoolMiniCanonicalRoute->getRequirements();
      $requirements['_school_claim_view_access_check'] = TRUE;
      $schoolMiniCanonicalRoute->setRequirements($requirements);
    }
    // Add the access check in school pdf download.
    $claimPdfPrintRoute = $collection->get('entity_print.view');
    if ($claimPdfPrintRoute instanceof Route) {
      $requirements = $claimPdfPrintRoute->getRequirements();
      $requirements['_school_claim_view_access_check'] = TRUE;
      $claimPdfPrintRoute->setRequirements($requirements);
    }
  }

}
