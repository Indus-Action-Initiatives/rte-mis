<?php

/**
 * @file
 * Contains install-time code for the RTE-MIS profile.
 */

use Drupal\rte_mis\Form\SiteConfigureForm;

/**
 * Implements hook_install_tasks_alter().
 */
function rte_mis_install_tasks_alter(array &$tasks) {
  // Decorate the site configuration form to allow the user to configure their
  // site settings.
  $tasks['install_configure_form']['function'] = SiteConfigureForm::class;
}
