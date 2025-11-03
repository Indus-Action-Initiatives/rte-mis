<?php

namespace Drupal\rte_mis_school\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Notifications Board' block.
 *
 * @Block(
 *   id = "notifications_board_block",
 *   admin_label = @Translation("Notifications Board Block")
 * )
 */
class NotificationsBoardBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected $entityTypeManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  public function build() {
    $notifications = $this->getNotifications();

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['notifications-board']],
      '#attached' => [
        'library' => [
          'rte_mis_school/dashboard',
        ],
      ],
    ];

    $build['title'] = ['#markup' => '<h2>' . $this->t('Notifications Board') . '</h2>'];

    if (!empty($notifications)) {
      $build['list'] = [
        '#theme' => 'item_list',
        '#items' => $notifications,
        '#list_type' => 'ul',
      ];
    } else {
      $build['empty'] = ['#markup' => '<p class="no-notifications">' . $this->t('No recent notifications.') . '</p>'];
    }

    return $build;
  }

  protected function getNotifications() {
    $query = $this->entityTypeManager->getStorage('mini_node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'notification')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range(0, 5);
    $ids = $query->execute();
    $entities = $this->entityTypeManager->getStorage('mini_node')->loadMultiple($ids);
    $notifications = [];
    foreach ($entities as $entity) {
      $notifications[] = $entity->label();
    }
    return $notifications;
  }

}