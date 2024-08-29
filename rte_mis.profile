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

/**
 * Implements hook_update_N().
 */
function rte_mis_update_8002() {
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

  // Update gin_toolbar_custom_menu.settings.yml configuration.
  $toolbar_config = \Drupal::configFactory()->getEditable('gin_toolbar_custom_menu.settings');
  $toolbar_settings = $toolbar_config->get('settings');

  foreach ($toolbar_settings as &$setting) {
    if ($setting['menu'] === 'state-admin-menu') {
      $setting['icons']['menu_link_content:04d25c39-0a20-4c0e-8229-c58f7856162f'] = 'info-view';
    }
  }

  $toolbar_config->set('settings', $toolbar_settings)->save();
  \Drupal::messenger()->addStatus(t('The gin_toolbar_custom_menu.settings.yml configuration has been updated.'));

  // Update user.role.state_admin.yml configuration.
  $state_admin_config = \Drupal::configFactory()->getEditable('user.role.state_admin');

  $dependencies = $state_admin_config->get('dependencies.module');
  if (!in_array('rte_mis_logs', $dependencies)) {
    $dependencies[] = 'rte_mis_logs';
    $state_admin_config->set('dependencies.module', $dependencies);
  }

  $permissions = $state_admin_config->get('permissions');
  if (!in_array('access rte_mis_logs logs', $permissions)) {
    $permissions[] = 'access rte_mis_logs logs';
    $state_admin_config->set('permissions', $permissions);
  }

  $state_admin_config->save();
  \Drupal::messenger()->addStatus(t('The user.role.state_admin configuration has been updated.'));
}
