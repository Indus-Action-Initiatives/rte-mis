<?php

namespace Drupal\rte_mis_school\Routing;

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
      $requirements['_school_details_edit_access_check'] = TRUE;
      $route->setRequirements($requirements);
    }
    // Add the access check in school_registration_verification view.
    $schoolRegistrationVerification = $collection->get('view.school_registration_verification.page_1');
    if ($schoolRegistrationVerification instanceof Route) {
      $requirements = $schoolRegistrationVerification->getRequirements();
      $requirements['_school_verification_check'] = TRUE;
      $schoolRegistrationVerification->setRequirements($requirements);
    }
  }

}
