<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Site Footer Block.
 *
 * @Block(
 *   id = "rte_mis_home_site_footer",
 *   admin_label = @Translation("Site Footer"),
 *   category = @Translation("RTE MIS Home")
 * )
 */
class SiteFooterBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new SiteFooterBlock.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $config = $this->configFactory->get('rte_mis_home.site_footer_settings');

    $props = [
      'brand_subtitle' => $config->get('brand_subtitle'),
      'helpline' => $config->get('helpline'),
      'email' => $config->get('email'),
      'hours' => $config->get('hours'),
      'admin_login_url' => $config->get('admin_login_url'),
      'last_updated' => $config->get('last_updated'),
      'quick_links' => $this->buildMenuLinks($config->get('quick_links_menu')),
      'resources' => $this->buildMenuLinks($config->get('resources_menu')),
      'gov_links' => $this->buildMenuLinks($config->get('gov_links_menu')),
      'footer_links' => $this->buildMenuLinks($config->get('footer_links_menu')),
      'social_links' => $this->buildMenuLinks($config->get('social_links_menu')),
    ];

    // Filter out null or empty global properties to allow SDC defaults to load if necessary.
    $props = array_filter($props, function($value) {
      return $value !== NULL && $value !== '';
    });

    $build = [
      '#type' => 'component',
      '#component' => 'rte_mis_theme:site-footer',
      '#props' => $props,
      '#cache' => [
        'tags' => ['config:rte_mis_home.site_footer_settings'],
      ],
    ];

    return $build;
  }

  /**
   * Helper method to build an array of formatted links from a Drupal menu name.
   */
  protected function buildMenuLinks($menu_name) {
    if (empty($menu_name)) {
      return [];
    }
    
    /** @var \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree */
    $menu_tree = \Drupal::menuTree();
    $parameters = new \Drupal\Core\Menu\MenuTreeParameters();
    $parameters->onlyEnabledLinks();
    
    $tree = $menu_tree->load($menu_name, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $menu_tree->transform($tree, $manipulators);

    $links = [];
    foreach ($tree as $element) {
      if (!$element->access || !$element->access->isAllowed()) {
        continue;
      }
      $links[] = [
        'label' => $element->link->getTitle(),
        'url' => $element->link->getUrlObject()->toString(),
      ];
    }
    return $links;
  }

}
