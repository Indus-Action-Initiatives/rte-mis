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
final class BannerSettings extends ConfigFormBase
{

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
  public static function create(ContainerInterface $container)
  {
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
  public function __construct($config_factory, FileUrlGeneratorInterface $file_url_generator, EntityTypeManagerInterface $entity_type_manager)
  {
    parent::__construct($config_factory);
    $this->fileUrlGenerator = $file_url_generator;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'rte_mis_home_banner_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array
  {
    return ['rte_mis_home.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $config = $this->config('rte_mis_home.settings');

    $banner_image_fid = $config->get('banner_image') ?: NULL;
    $banner_image_url = NULL;

    if ($banner_image_fid) {
      $file = $this->entityTypeManager->getStorage('file')->load($banner_image_fid);
      if ($file) {
        $banner_image_url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
      }
    }

    $form['banner_image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Banner Image'),
      '#upload_location' => 'public://banner_images/',
      '#default_value' => $banner_image_fid ? [$banner_image_fid] : [],
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'],
        'file_validate_size' => [5 * 1024 * 1024],
      ],
      '#description' => $this->t('Upload a PNG, JPG, or JPEG file only, with a maximum size of 5MB.'),
    ];

    if ($banner_image_url) {
      $form['banner_image_preview'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['banner-image-preview']],
        '#markup' => '<img src="' . $banner_image_url . '" alt="Banner Image">',
      ];
    }

    $content = $config->get('banner_content');

    $form['banner_content'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Content'),
      '#format' => $content['format'] ?? 'full_html',
      '#allowed_formats' => ['full_html'],
      '#default_value' => $content['value'] ?? '',
    ];

    $links = $config->get('links') ?: [];
    $num_links = $form_state->get('num_links');
    if ($num_links === NULL) {
      $num_links = max(1, count($links));
      $form_state->set('num_links', $num_links);
    }

    $form['links_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'links-wrapper'],
    ];

    $form['links_wrapper']['links'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Banner Links'),
      '#tree' => TRUE,
    ];

    for ($i = 0; $i < $num_links; $i++) {
      $form['links_wrapper']['links'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('Link @num', ['@num' => $i + 1]),
        '#open' => TRUE,
      ];
      $form['links_wrapper']['links'][$i]['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Link Title'),
        '#default_value' => $links[$i]['title'] ?? '',
      ];
      $form['links_wrapper']['links'][$i]['url'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Link URL'),
        '#description' => $this->t('Example: /node/1 or https://example.com'),
        '#default_value' => $links[$i]['url'] ?? '',
      ];
    }

    $form['links_wrapper']['add_link'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another link'),
      '#submit' => ['::addLinkSubmit'],
      '#ajax' => [
        'callback' => '::addLinkCallback',
        'wrapper' => 'links-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Submit handler for the "Add another link" button.
   */
  public function addLinkSubmit(array &$form, FormStateInterface $form_state)
  {
    $num_links = $form_state->get('num_links') + 1;
    $form_state->set('num_links', $num_links);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for the "Add another link" button.
   */
  public function addLinkCallback(array &$form, FormStateInterface $form_state)
  {
    return $form['links_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    $config = $this->configFactory->getEditable('rte_mis_home.settings');

    // Handle the file upload.
    $file_ids = array_filter($form_state->getValue('banner_image'));
    $saved_file_id = NULL;

    if (!empty($file_ids)) {
      $file_id = reset($file_ids);
      $file = $this->entityTypeManager->getStorage('file')->load($file_id);
      if ($file) {
        $file->setPermanent();
        $file->save();
        $saved_file_id = $file_id;
      }
    }

    $config->set('banner_image', $saved_file_id);
    $config->set('banner_content', $form_state->getValue('banner_content'));

    $links = $form_state->getValue('links') ?: [];
    $valid_links = [];
    foreach ($links as $link) {
      if (!empty($link['title']) && !empty($link['url'])) {
        $valid_links[] = $link;
      }
    }
    $config->set('links', $valid_links);

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
