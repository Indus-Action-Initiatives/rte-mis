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
    // Add the access check in mini_node entity view.
    $schoolMiniCanonicalRoute = $collection->get('entity.mini_node.canonical');
    if ($schoolMiniCanonicalRoute instanceof Route) {
      $requirements = $schoolMiniCanonicalRoute->getRequirements();
      $requirements['_school_details_view_access_check'] = TRUE;
      $schoolMiniCanonicalRoute->setRequirements($requirements);
    }
    // Add the access check in school pdf download.
    $schoolPdfPrintRoute = $collection->get('entity_print.view');
    if ($schoolPdfPrintRoute instanceof Route) {
      $requirements = $schoolPdfPrintRoute->getRequirements();
      $requirements['_school_pdf_download_access_check'] = TRUE;
      $schoolPdfPrintRoute->setRequirements($requirements);
    }
  }

}
