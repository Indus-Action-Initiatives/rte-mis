<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a two column block block.
 *
 * @Block(
 *   id = "rte_mis_home_two_column_block",
 *   admin_label = @Translation("Two Column Block"),
 *   category = @Translation("Custom"),
 * )
 */
final class TwoColumnBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private FileSystemInterface $fileSystem;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
    FileUrlGeneratorInterface $file_url_generator,
    ConfigFactoryInterface $config_factory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->fileUrlGenerator = $file_url_generator;
    $this->configFactory = $config_factory;
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
      $container->get('file_url_generator'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'image' => '',
      'title' => '',
      'description' => '',
      'link' => '',
    ];
  }

  public function build(): array {
    $global_config = $this->configFactory->get('rte_mis_home.two_column_block_settings');

    $image_fid = $global_config->get('two_column_block_image');
    $title = $global_config->get('two_column_block_title');
    $description = $global_config->get('two_column_block_description');
    $link = $global_config->get('two_column_block_link');

    $image_uri = NULL;
    if ($image_fid) {
      /** @var \Drupal\file\FileInterface $file */
      $file = $this->entityTypeManager->getStorage('file')->load($image_fid);
      if ($file) {
        $image_uri = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
      }
    }

    return [
      '#theme' => 'two_column_block',
      '#image' => $image_uri,
      '#title' => $title,
      '#description' => $description,
      '#link' => $link,
      '#attached' => [
        'library' => [
          'rte_mis_gin/rte_mis_two_column_block',
        ],
      ],
      '#cache' => [
        'tags' => ['config:rte_mis_home.two_column_block_settings'],
      ],
    ];
  }

}
