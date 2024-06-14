<?php

namespace Drupal\rte_mis_core;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\rte_mis_core\Menu\OverrideSiteMap;

/**
 * Modifies the language manager service.
 */
class RteMisCoreServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Overrides sitemap.menu.link_tree service.
    if ($container->hasDefinition('sitemap.menu.link_tree')) {
      $definition = $container->getDefinition('sitemap.menu.link_tree');
      $definition->setClass(OverrideSiteMap::class);
    }
  }

}
