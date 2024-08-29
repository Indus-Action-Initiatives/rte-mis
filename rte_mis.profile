<?php

/**
 * @file
 * Contains install-time code for the RTE-MIS profile.
 */

use Drupal\rte_mis\Form\SiteConfigureForm;
use Drupal\rte_mis_core\Helper\ConfigManager;

/**
 * Implements hook_install_tasks_alter().
 */
function rte_mis_install_tasks_alter(array &$tasks) {
  // Decorate the site configuration form to allow the user to configure their
  // site settings.
  $tasks['install_configure_form']['function'] = SiteConfigureForm::class;
}

/**
 * Implements hook_update_N().
 */
function rte_mis_update_10006() {
  $manager = \Drupal::service('rte_mis_core.manager');
  $manager->updateConfigs(
    ['block.block.gin_twocolumnblock'],
    'rte_mis',
    'install',
    ConfigManager::MODE_REPLACE,
  );
}
