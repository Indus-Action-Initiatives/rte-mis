<?php

namespace Drupal\rte_mis_student_tracking\Routing;

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
      $requirements['_performance_node_edit_access_check'] = TRUE;
      $route->setRequirements($requirements);
    }
    // Add the access check in mini_node entity view.
    $trackingCanonicalRoute = $collection->get('entity.mini_node.canonical');
    if ($trackingCanonicalRoute instanceof Route) {
      $requirements = $trackingCanonicalRoute->getRequirements();
      $requirements['_performance_node_view_access_check'] = TRUE;
      $trackingCanonicalRoute->setRequirements($requirements);
    }
  }

}
