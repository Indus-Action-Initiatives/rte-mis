<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'School Search' block.
 *
 * @Block(
 *   id = "rte_mis_home_school_search",
 *   admin_label = @Translation("School Search Block"),
 *   category = @Translation("RTE MIS Home")
 * )
 */
class SchoolSearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new SchoolSearchBlock.
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
    $config = $this->configFactory->get('rte_mis_home.school_search_settings');

    $title_prefix = $config->get('title_prefix') ?: 'Search by';
    $title_highlight = $config->get('title_highlight') ?: 'School Name, PIN Code or Location';
    $search_placeholder = $config->get('search_placeholder') ?: 'Find Schools';
    $search_action = $config->get('search_action') ?: '/school-search';
    $show_filters = $config->get('show_filters') ?? TRUE;
    $filters_config = $config->get('filters') ?: [];

    // Build filter columns from config.
    $filters = [];
    foreach ($filters_config as $filter) {
      if (empty($filter['label'])) {
        continue;
      }
      $options = [];
      if (!empty($filter['options'])) {
        foreach ($filter['options'] as $option) {
          if (!empty(trim($option['label'] ?? ''))) {
            $options[] = [
              'value' => $option['value'] ?? $option['label'],
              'label' => $option['label'],
            ];
          }
        }
      }
      $filters[] = [
        'label' => $filter['label'],
        'placeholder' => $filter['placeholder'] ?? 'Select...',
        'name' => $filter['name'] ?? strtolower(str_replace(' ', '_', $filter['label'])),
        'options' => $options,
      ];
    }

    return [
      '#theme' => 'school_search_block',
      '#title_prefix' => $title_prefix,
      '#title_highlight' => $title_highlight,
      '#search_placeholder' => $search_placeholder,
      '#search_action' => $search_action,
      '#show_filters' => (bool) $show_filters,
      '#filters' => $filters,
      '#cache' => [
        'tags' => $config->getCacheTags(),
      ],
    ];
  }

}
