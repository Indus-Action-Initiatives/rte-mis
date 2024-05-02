<?php

namespace Drupal\rte_mis_student\Routing;

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
      $requirements['_student_details_edit_access_check'] = TRUE;
      $route->setRequirements($requirements);
    }
    // Add the access check in mini_node view page.
    $studentProfileView = $collection->get('entity.mini_node.canonical');
    if ($studentProfileView instanceof Route) {
      $requirements = $studentProfileView->getRequirements();
      $requirements['_student_details_edit_access_check'] = TRUE;
      $studentProfileView->setRequirements($requirements);
    }
    // Add the access check in student pdf download.
    $studentPdfDownload = $collection->get('entity_print.view');
    if ($studentPdfDownload instanceof Route) {
      $requirements = $studentPdfDownload->getRequirements();
      $requirements['_student_pdf_download_access_check'] = TRUE;
      $studentPdfDownload->setRequirements($requirements);
    }

  }

}
