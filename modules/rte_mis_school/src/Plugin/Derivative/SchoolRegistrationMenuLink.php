<?php

namespace Drupal\rte_mis_school\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Create sub-menu for school registration.
 */
class SchoolRegistrationMenuLink extends DeriverBase {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives['rte_mis_school.school_registration.edit'] = [
      'title' => $this->t('Register'),
      'parent' => 'rte_mis_school.school_registration',
      'route_name' => 'rte_mis_school.school_registration.edit',
      'weight' => 0,
    ] + $base_plugin_definition;

    $this->derivatives['rte_mis_school.school_registration.view'] = [
      'title' => $this->t('View Registration'),
      'parent' => 'rte_mis_school.school_registration',
      'route_name' => 'rte_mis_school.school_registration.view',
      'weight' => 1,
    ] + $base_plugin_definition;

    $this->derivatives['rte_mis_school.school_registration.print'] = [
      'title' => $this->t('Print Registration'),
      'parent' => 'rte_mis_school.school_registration',
      'route_name' => 'entity_print.view',
      'weight' => 2,
      'route_parameters' => [
        'export_type' => 'pdf',
        'entity_type' => 'mini_node',
      ],
    ] + $base_plugin_definition;

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
