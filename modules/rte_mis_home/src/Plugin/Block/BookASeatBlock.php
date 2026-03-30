<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Book A Seat' block.
 *
 * @Block(
 *   id = "rte_mis_home_book_a_seat",
 *   admin_label = @Translation("Book A Seat Block"),
 *   category = @Translation("RTE MIS")
 * )
 */
final class BookASeatBlock extends BlockBase implements ContainerFactoryPluginInterface
{
  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * Constructs a new BookASeatBlock instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, FileUrlGeneratorInterface $file_url_generator)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('file_url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array
  {
    $config = $this->configFactory->get('rte_mis_home.settings');
    $values = $config->get('book_a_seat') ?: [];

    $image_url = '/profiles/contrib/rte-mis/themes/custom/rte_mis_theme/src/components/book-a-seat-block/default_bg.png'; // Placeholder or actual default path
    if (!empty($values['background_image'])) {
      $file = $this->entityTypeManager->getStorage('file')->load($values['background_image']);
      if ($file) {
        $image_url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
      }
    }

    return [
      '#theme' => 'book_a_seat_block',
      '#title' => $values['title'] ?? $this->t('Book A Seat Now'),
      '#description' => $values['description'] ?? $this->t('Join thousands of families who have transformed their children\'s future through quality education'),
      '#button_text' => $values['button_text'] ?? $this->t('Check Documents & Guidelines'),
      '#button_link' => $values['button_link'] ?? '#',
      '#background_image' => $image_url,
      '#cache' => [
        'tags' => $config->getCacheTags(),
      ],
    ];
  }
}
