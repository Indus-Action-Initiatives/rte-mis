<?php

declare(strict_types=1);

namespace Drupal\rte_mis_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a tasks status block.
 *
 * @Block(
 *   id = "rte_mis_core_tasks_status",
 *   admin_label = @Translation("Tasks Status"),
 *   category = @Translation("Custom"),
 * )
 */
final class TasksStatusBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    // Attach the custom library to the block.
    $build = [
      '#theme' => 'tasks_status_block',
      '#attached' => [
        'library' => [
          'rte_mis_core/rte_mis_tasks_status_block',
        ],
      ],
      '#content' => $this->t('It works!'),
    ];

    return $build;
  }

}
