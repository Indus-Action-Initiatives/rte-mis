<?php

namespace Drupal\rte_mis_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Site log Section' block with image and text fields.
 *
 * @Block(
 *   id = "site_logo_section_block",
 *   admin_label = @Translation("Site Logo Section"),
 * )
 */
class SiteLogoSectionBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\Utility\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, ModuleHandlerInterface $moduleHandler, FileUrlGeneratorInterface $fileUrlGenerator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleHandler = $moduleHandler;
    $this->fileUrlGenerator = $fileUrlGenerator;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('file_url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $build = [];

    $heading = isset($config['site_logo_heading']) && $config['site_logo_heading'] !== '' ? $config['site_logo_heading'] : $this->t('Government of India');
    $subtext = isset($config['site_logo_subtext']) && $config['site_logo_subtext'] !== '' ? $config['site_logo_subtext'] : $this->t('School Education Department Right to Education (RTE) Portal');
    $logoImg = $config['site_logo_image'] ?? '';

    if (empty($logoImg[0])) {
      // Load and display the default image from the assets folder.
      $modulePath = $this->moduleHandler->getModule('rte_mis_core')->getPath();
      $imagePath = $modulePath . '/asset/img/Emblem_of_India.svg';

      // // Generate the URL for the image.
      $file_generator = $this->fileUrlGenerator->generateAbsoluteString($imagePath);

      $build['image'] = [
        '#theme' => 'image',
        '#width' => 50,
        '#height' => 100,
        '#uri' => $file_generator,
      ];
    }
    else {
      // Display the user-uploaded image.
      $file_entity = $this->entityTypeManager->getStorage('file');
      if ($file = $file_entity->load($logoImg[0])) {
        $build['image'] = [
          '#theme' => 'image',
          '#width' => 50,
          '#height' => 100,
          '#uri' => $file->getFileUri(),
        ];
      }
    }
    $build['heading']['#markup'] = '<div class="logo-content-wrapper"><h2>' . $heading . '</h2>';
    $build['sub_heading']['#markup'] = '<p>' . $subtext . '</p></div>';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    $modulePath = $this->moduleHandler->getModule('rte_mis_core')->getPath();
    $imagePath = $modulePath . '/asset/img/Emblem_of_India.svg';
    // Generate the URL for the image.
    $file_generator = $this->fileUrlGenerator->generateAbsoluteString($imagePath);
    $default_image = Link::fromTextAndUrl('here', Url::fromUri($file_generator, ['attributes' => ['target' => '_blank']]))->toString();

    $form['site_logo_image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Site Logo'),
      '#default_value' => $config['site_logo_image'] ?? '',
      '#upload_location' => 'public://site_logo/asset/',
      '#upload_validators' => [
        'file_validate_extensions' => ['jpg jpeg png svg'],
      ],
      '#description' => $this->t("If no image is selected, the Default Image will be displayed. Click @here to see the default image.", ['@here' => $default_image]),
    ];

    $form['site_logo_heading'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site Logo Heading'),
      '#default_value' => isset($config['site_logo_heading']) && $config['site_logo_heading'] !== '' ? $config['site_logo_heading'] : $this->t('Government of India'),
      '#description' => $this->t('Site Logo Heading Text'),
    ];

    $form['site_logo_subtext'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site Subtext'),
      '#default_value' => isset($config['site_logo_subtext']) && $config['site_logo_subtext'] !== '' ? $config['site_logo_subtext'] : $this->t('School Education Department Right to Education (RTE) Portal'),
      '#description' => $this->t('Site Logo Body Text'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Save image as permanent.
    $image = $values['site_logo_image'];
    if (isset($this->configuration['site_logo_image'])) {
      if ($image != $this->configuration['site_logo_image']) {
        $file_entity = $this->entityTypeManager->getStorage('file');
        if (!empty($image[0]) && $file = $file_entity->load($image[0])) {
          $file->setPermanent();
          $file->save();
        }
      }
    }

    $this->configuration['site_logo_heading'] = $values['site_logo_heading'];
    $this->configuration['site_logo_image'] = $values['site_logo_image'];
    $this->configuration['site_logo_subtext'] = $values['site_logo_subtext'];
  }

}
