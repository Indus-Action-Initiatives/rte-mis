<?php

namespace Drupal\rte_mis_core\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This class create form that will be used to import location in bulk.
 */
class BulkUploadMultipleLocationForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * Constructs the service objects.
   *
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ModuleExtensionList $extension_list_module, FileUrlGeneratorInterface $file_url_generator) {
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleExtensionList = $extension_list_module;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('extension.list.module'),
      $container->get('file_url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bulk_upload_multiple_location_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Generate URL for sample csv.
    $modulePath = $this->moduleExtensionList->getPath('rte_mis_core');
    $samplePath = $modulePath . '/asset/multi_location_sample.xlsx';
    $uri = $this->fileUrlGenerator->generateAbsoluteString($samplePath);
    $realPath = Url::fromUri($uri, ['https' => TRUE]);

    $form['description'] = [
      '#markup' => $this->t('Add multiple location by uploading the template file provided below the field. Data should be in same format as in template while uploading the locations.'),
    ];
    $form['file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload file'),
      '#upload_validators' => [
        'file_validate_extensions' => ['csv xls xlsx'],
        'file_validate_size' => [5000000],
      ],
      '#upload_location' => 'public://bulk-import/location/',
      '#multiple' => FALSE,
      '#required' => TRUE,
      '#description' => $this->t('Download the <b><a href="@link">Template</a></b> file. Max 5 MB allowed</br>(Only .csv, .xlsx, .xls files are allowed).', [
        '@link' => $realPath->toString(),
      ]),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $fileUploaded = $form_state->getValue(['file', 0]) ?? NULL;
    // Prepare the data for batch process.
    $details = [
      'userId' => $this->currentUser()->id(),
    ];
    if (isset($fileUploaded)) {
      $file = $this->entityTypeManager->getStorage('file')->load($fileUploaded);
      if ($file instanceof FileInterface) {
        // Create batch.
        $batch = [
          'title'      => $this->t('Importing location.'),
          'operations' => [
          [
            '\Drupal\rte_mis_core\Batch\LocationTermBatch::importMultiple',
            [$fileUploaded, $details],
          ],
          ],
          'progressive' => TRUE,
          'progress_message' => '@percentage% complete. Time elapsed: @elapsed, estimated time remaining: @estimate.',
          'finished' => '\Drupal\rte_mis_core\Batch\LocationTermBatch::multipleLocationFinishedCallback',
        ];

        batch_set($batch);
      }
    }
  }

}
