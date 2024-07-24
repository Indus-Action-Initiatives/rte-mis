<?php

namespace Drupal\rte_mis_allocation\Routing;

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
      $requirements['_allocation_node_edit_access_check'] = TRUE;
      $route->setRequirements($requirements);
    }
  }

}
