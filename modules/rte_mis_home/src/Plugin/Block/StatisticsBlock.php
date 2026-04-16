<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a statistics block.
 *
 * @Block(
 *   id = "rte_mis_home_statistics_block",
 *   admin_label = @Translation("Statistics Block"),
 *   category = @Translation("Custom"),
 * )
 */
final class StatisticsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new StatisticsBlock instance.
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
    $config = $this->configFactory->get('rte_mis_home.settings');
    $statistics = $config->get('statistics');

    if (empty($statistics)) {
      $statistics = [
        [
          'icon' => 'school',
          'total_count' => '10',
          'label' => $this->t('Total Schools'),
        ],
        [
          'icon' => 'student',
          'total_count' => '5000',
          'label' => $this->t('Total Students'),
        ],
        [
          'icon' => 'seats',
          'total_count' => '300',
          'label' => $this->t('Total Seats'),
        ],
        [
          'icon' => 'district',
          'total_count' => '20',
          'label' => $this->t('Total Districts'),
        ],
        [
          'icon' => 'reimbursement',
          'total_count' => '1000',
          'label' => $this->t('Total Reimbursement'),
        ],
      ];
    }

    return [
      '#theme' => 'statistics_block',
      '#statistics' => $statistics,
      '#attached' => [
        'library' => [
          'rte_mis_gin/rte_mis_statistics_block',
        ],
      ],
      '#cache' => [
        'tags' => $this->configFactory->get('rte_mis_home.settings')->getCacheTags(),
      ],
    ];
  }

}
