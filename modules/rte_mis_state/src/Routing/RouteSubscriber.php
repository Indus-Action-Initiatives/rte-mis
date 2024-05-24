<?php

namespace Drupal\rte_mis_state\Routing;

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
    $route = $collection->get('user.register');
    if ($route) {
      $route->setRequirements([
        '_user_register_access_check' => 'TRUE',
      ]);

      // Update the no cache to TRUE.
      $options = $route->getOptions();
      $options['no_cache'] = TRUE;
      $route->setOptions($options);

      // Update the title for the route.
      $defaults = $route->getDefaults();
      $defaults['_title'] = 'Create new School Account';
      $route->setDefaults($defaults);
    }

    $userEditPage = $collection->get('entity.user.edit_form');
    if ($userEditPage instanceof Route) {
      $requirements = $userEditPage->getRequirements();
      $requirements['_user_edit_access_check'] = TRUE;
      $userEditPage->setRequirements($requirements);
    }
  }

}
