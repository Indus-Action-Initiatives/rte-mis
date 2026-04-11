<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure rte_mis_home two-column block settings.
 */
final class TwoColumnBlockSettings extends ConfigFormBase
{
  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

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
   * Constructs a TwoColumnBlockSettings object.
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
    return 'rte_mis_home_two_column_block_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array
  {
    return ['rte_mis_home.two_column_block_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $config = $this->config('rte_mis_home.two_column_block_settings');

    $form['two_column_block_image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Two Column Block image'),
      '#upload_location' => 'public://two_column_block/',
      '#default_value' => $config->get('two_column_block_image') ? [$config->get('two_column_block_image')] : [],
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'],
      ],
    ];

    $form['two_column_block_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $config->get('two_column_block_title'),
      '#required' => TRUE,
    ];

    $form['two_column_block_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $config->get('two_column_block_description'),
      '#required' => TRUE,
    ];

    $form['two_column_block_link'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link URL'),
      '#default_value' => $config->get('two_column_block_link'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    $config = $this->configFactory->getEditable('rte_mis_home.two_column_block_settings');

    $image_values = $form_state->getValue('two_column_block_image');
    $file_ids = is_array($image_values) ? array_filter($image_values) : [];
    
    if (!empty($file_ids)) {
      $file_id = reset($file_ids);
      /** @var \Drupal\file\FileInterface $file */
      $file = $this->entityTypeManager->getStorage('file')->load($file_id);
      if ($file) {
        $file->setPermanent();
        $file->save();
        $config->set('two_column_block_image', $file_id);
      }
    }
    else {
      $config->set('two_column_block_image', NULL);
    }

    $config
      ->set('two_column_block_title', $form_state->getValue('two_column_block_title'))
      ->set('two_column_block_description', $form_state->getValue('two_column_block_description'))
      ->set('two_column_block_link', $form_state->getValue('two_column_block_link'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
