<?php

namespace Drupal\rte_mis_school\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This class create form for bulk import of school UDISE code.
 */
class BulkUploadUdiseCodeForm extends FormBase {


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
    return 'bulk_upload_school_udise_code';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Generate URL for sample csv.
    $modulePath = $this->moduleExtensionList->getPath('rte_mis_school');
    $samplePath = $modulePath . '/asset/sample.csv';
    $realPath = $this->fileUrlGenerator->generateAbsoluteString($samplePath);

    $form['file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload file'),
      '#description' => $this->t('Download the <b><a href="@link">Template</a></b> file. Max 5 MB allowed</br>(Only .csv, .xlsx, .xls files are allowed).', [
        '@link' => $realPath,
      ]),
      '#upload_validators' => [
        'file_validate_extensions' => ['csv xls xlsx'],
        'file_validate_size' => [5000000],
      ],
      '#upload_location' => 'public://bulk-import/school-udise-code/',
      '#multiple' => FALSE,
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $fileUploaded = $form_state->getValue('file');
    if (empty($fileUploaded)) {
      $form_state->setErrorByName('file', $this->t('File is required'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $fileUploaded = $form_state->getValue(['file', 0]) ?? NULL;
    if (isset($fileUploaded)) {
      $file = $this->entityTypeManager->getStorage('file')->load($fileUploaded);
      if ($file instanceof FileInterface) {
        // Create batch.
        $batch = [
          'title'      => $this->t('Importing Jobs.'),
          'operations' => [
          [
            '\Drupal\rte_mis_school\Batch\SchoolUdiseCodeBatch::import',
            [$fileUploaded],
          ],
          ],
          'progressive' => TRUE,
          'progress_message' => '@percentage% complete. Time elapsed: @elapsed, estimated time remaining: @estimate.',
          'finished' => '\Drupal\rte_mis_school\Batch\SchoolUdiseCodeBatch::finishedCallback',
        ];

        batch_set($batch);
      }
    }

  }

}
