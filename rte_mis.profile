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
function rte_mis_update_10007() {
  // The name of the module to install.
  $module_name = 'rte_mis_logs';

  // Check if the module is already installed.
  if (!\Drupal::moduleHandler()->moduleExists($module_name)) {
    // Enable the module.
    \Drupal::service('module_installer')->install([$module_name]);

    \Drupal::messenger()->addStatus(t('The %module module has been installed.', ['%module' => $module_name]));
  }
  else {
    \Drupal::messenger()->addWarning(t('The %module module is already installed.', ['%module' => $module_name]));
  }

  $manager = \Drupal::service('rte_mis_core.manager');
  $manager->updateConfigs(
    [
      'gin_toolbar_custom_menu.settings',
      'user.role.state_admin',
    ],
    'rte_mis',
    'install',
    ConfigManager::MODE_REPLACE,
  );

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
