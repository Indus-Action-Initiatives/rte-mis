<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a FAQ Block.
 *
 * @Block(
 *   id = "rte_mis_home_faq",
 *   admin_label = @Translation("FAQ Block"),
 *   category = @Translation("RTE MIS Home")
 * )
 */
class FaqBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new FaqBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
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
    $config = $this->configFactory->get('rte_mis_home.faq_settings');
    $items = $config->get('faq_items') ?: [];
    $title = $config->get('title') ?: 'Frequently Asked Questions';

    if (empty($items)) {
      return [];
    }

    // Cast the open flag to a strict boolean because SDC validation requires it
    // and Drupal Form API checkboxes save as integers (0 or 1).
    // Additionally, process HTML answers into plain HTML strings.
    foreach ($items as &$item) {
      $item['open'] = !empty($item['open']);
      
      if (isset($item['answer']) && is_array($item['answer'])) {
        $item['answer'] = (string) check_markup($item['answer']['value'], 'full_html');
      }
    }
    unset($item);

    $build = [
      '#type' => 'component',
      '#component' => 'rte_mis_theme:faq-section',
      '#props' => [
        'title' => $title,
        'items' => $items,
        'view_all_link' => [
          'url' => $config->get('view_all_link_url'),
          'text' => $config->get('view_all_link_text'),
        ],
      ],
      '#cache' => [
        'tags' => ['config:rte_mis_home.faq_settings'],
      ],
    ];

    return $build;
  }

}
