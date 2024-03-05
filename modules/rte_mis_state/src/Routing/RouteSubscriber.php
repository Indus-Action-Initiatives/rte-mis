<?php

namespace Drupal\rte_mis_state\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $route = $collection->get('user.register');
    if ($route) {
      $route->setRequirements([
        '_user_register_access_check' => 'TRUE',
      ]);

      // Update the no cache to TRUE.
      $options = $route->getOptions();
      $options['no_cache'] = TRUE;
      $route->setOptions($options);
    }
  }

}
