<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Plugin\Block;

use Drupal\Core\Block\BlockBase;
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
  protected $fileUrlGenerator;

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
    FileUrlGeneratorInterface $file_url_generator,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->fileUrlGenerator = $file_url_generator;
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

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Image'),
      '#upload_location' => 'public://two_column_block/',
      '#default_value' => $this->configuration['image'],
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'],
      ],
    ];
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $this->configuration['title'],
      '#required' => TRUE,
    ];
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->configuration['description'],
      '#required' => TRUE,
    ];
    $form['link'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link URL'),
      '#default_value' => $this->configuration['link'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $image = $form_state->getValue('image');
    if ($image) {
      $file = $this->entityTypeManager->getStorage('file')->load($image[0]);
      if ($file) {
        $file->setPermanent();
        $file->save();
      }
    }
    $this->configuration['image'] = $image;
    $this->configuration['title'] = $form_state->getValue('title');
    $this->configuration['description'] = $form_state->getValue('description');
    $this->configuration['link'] = $form_state->getValue('link');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $image_uri = NULL;
    if (!empty($this->configuration['image'])) {
      $file = $this->entityTypeManager->getStorage('file')->load($this->configuration['image'][0]);
      if ($file) {
        $image_uri = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
      }
    }
    return [
      '#theme' => 'two_column_block',
      '#image' => $image_uri,
      '#title' => $this->configuration['title'],
      '#description' => $this->configuration['description'],
      '#link' => $this->configuration['link'],
      '#attached' => [
        'library' => [
          'rte_mis_gin/rte_mis_two_column_block',
        ],
      ],
    ];
  }

}
