<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure rte_mis_home settings for this site.
 */
final class BannerSettings extends ConfigFormBase {

  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('file_url_generator'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Constructs a BannerSettings object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct($config_factory, FileUrlGeneratorInterface $file_url_generator, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);
    $this->fileUrlGenerator = $file_url_generator;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'rte_mis_home_banner_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['rte_mis_home.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('rte_mis_home.settings');

    $banner_image_fids = $config->get('banner_images') ?: [];
    $banner_image_urls = [];

    foreach ($banner_image_fids as $fid) {
      $file = $this->entityTypeManager->getStorage('file')->load($fid);
      if ($file) {
        $banner_image_urls[] = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
      }
    }

    $form['banner_images'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Banner Images'),
      '#required' => TRUE,
      '#upload_location' => 'public://banner_images/',
      '#default_value' => $banner_image_fids,
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'],
        'file_validate_size' => [5 * 1024 * 1024],
      ],
      '#multiple' => TRUE,
      '#description' => $this->t('Upload PNG, JPG, or JPEG files only, with a maximum size of 5MB.'),
    ];

    if ($banner_image_urls) {
      $form['banner_images_preview'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['banner-images-preview']],
      ];

      foreach ($banner_image_urls as $url) {
        $form['banner_images_preview'][] = [
          '#type' => 'html_tag',
          '#tag' => 'img',
          '#attributes' => [
            'src' => $url,
          ],
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->configFactory->getEditable('rte_mis_home.settings');

    // Handle the file uploads.
    $file_ids = array_filter($form_state->getValue('banner_images'));
    $saved_file_ids = [];

    foreach ($file_ids as $file_id) {
      $file = $this->entityTypeManager->getStorage('file')->load($file_id);
      if ($file) {
        $file->setPermanent();
        $file->save();
        $saved_file_ids[] = $file_id;
      }
    }

    $config->set('banner_images', $saved_file_ids);
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
