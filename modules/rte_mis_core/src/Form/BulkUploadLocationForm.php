<?php

namespace Drupal\rte_mis_core\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\cshs\Component\CshsOption;
use Drupal\cshs\Element\CshsElement;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for uploading terms to `location` vocabulary in bulk.
 */
class BulkUploadLocationForm extends FormBase {

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
    return 'bulk_upload_location_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Generate URL for sample csv.
    $modulePath = $this->moduleExtensionList->getPath('rte_mis_core');
    $samplePath = $modulePath . '/asset/location_sample.xlsx';
    $realPath = $this->fileUrlGenerator->generateAbsoluteString($samplePath);

    $option = $form_state->get('location_schema_option');
    if (empty($option)) {
      $form_state->set('location_schema_option', $this->getLocationSchemaOptions());
      $option = $form_state->get('location_schema_option');
    }

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
        '@link' => $realPath,
      ]),
    ];

    $form['fieldset'] = [
      '#type' => 'container',
      '#prefix' => '<div id="fieldset-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['fieldset']['location_schema'] = [
      '#type' => 'select',
      '#title' => $this->t('What do you want to add?'),
      '#options' => $option,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'wrapperCallback'],
        'wrapper' => 'fieldset-wrapper',
      ],
    ];
    $location_schema = $form_state->getValue('location_schema') ?? NULL;
    if (!empty($location_schema)) {
      $depth = $option['custom_options']->details[$location_schema]['depth'] ?? NULL;
      if ($depth > 0) {
        $locationOptions = $this->getLocationOptions($form_state);
        $locationOptionsLabels = $locationOptions['custom_options']->labels ?? [];
        $form['fieldset']['location_parent'] = [
          '#type' => CshsElement::ID,
          '#label' => $this->t('Location'),
          '#required' => TRUE,
          '#labels' => $locationOptionsLabels,
          '#no_first_level_none' => TRUE,
          '#options' => $locationOptions['custom_options']->option ?? [],
          '#default_value' => $form_state->getValue('location_parent'),
        ];
      }
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * Get the `location` vocabulary as option.
   */
  protected function getLocationOptions(FormStateInterface $form_state) {
    $locationSchemaOption = $form_state->get('location_schema_option') ?? NULL;
    $locationSchema = $form_state->getValue('location_schema') ?? NULL;
    $options = $labels = [];
    // Get the depth of location_schema selected.
    $depth = $locationSchemaOption['custom_options']->details[$locationSchema]['depth'] ?? NULL;
    // Load `location` vocabulary.
    $locationTerms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', 0, NULL, TRUE);
    // Create cshs element according to `location_schema` selected.
    for ($i = 0; $i < $depth; $i++) {
      foreach ($locationTerms as $key => $term) {
        if ($term->depth == $i) {
          $options[(int) $term->id()] = new CshsOption($term->label(), (int) $term->parent->target_id == 0 ? NULL : $term->parent->target_id);
          unset($locationTerms[$key]);
        }
      }
    }
    // Create label that need to displayed above cshs element.
    $locationSchemaTermParent = $this->entityTypeManager->getStorage('taxonomy_term')->loadAllParents($locationSchema);
    foreach (array_slice($locationSchemaTermParent, 1) as $term) {
      $labels[] = $this->t('Select @label', ['@label' => $term->label()]);
    }

    return [
      'custom_options' => (object) [
        'option' => $options,
        'labels' => array_reverse($labels),
      ],
    ];
  }

  /**
   * Get the `location_schema` vocabulary as option.
   */
  protected function getLocationSchemaOptions() {
    $options = $terms = [];
    // Load `location` vocabulary.
    $locationSchemaTree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location_schema');
    foreach ($locationSchemaTree as $term) {
      // Create option.
      $options[$term->tid] = str_repeat('-', $term->depth) . $term->name;
      // Also store the depth in the same array, will be used in location.
      $terms[$term->tid] = [
        'depth' => $term->depth,
      ];
    }

    return [
      'custom_options' => (object) [
        'option' => $options,
        'details' => $terms,
      ],
    ];
  }

  /**
   * Ajax Callback.
   */
  public function wrapperCallback(array &$form, FormStateInterface $form_state) {
    return $form['fieldset'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $userInput = $form_state->getUserInput();
    $locationSchemaOption = $form_state->get('location_schema_option') ?? NULL;
    $locationSchema = $form_state->getValue('location_schema') ?? NULL;
    $location_parent = $userInput['location_parent'] ?? NULL;
    $fileUploaded = $form_state->getValue('file') ?? NULL;
    $depth = $locationSchemaOption['custom_options']->details[$locationSchema]['depth'] ?? NULL;
    // Validation for location_schema.
    if (empty($locationSchema)) {
      $form_state->setErrorByName('location_schema', $this->t('Please select schema.'));
    }
    // If depth > 0, we need to check the option selected in cshs element
    // (`location) and `location_schema` matches the same depth, if not we need
    // to show error message.
    if ($depth > 0) {
      if (isset($location_parent)) {
        $locationTermParent = $this->entityTypeManager->getStorage('taxonomy_term')->loadAllParents($location_parent);
        $numberOfParent = count($locationTermParent);
        if ($numberOfParent != $depth) {
          $form_state->setErrorByName('location_schema', $this->t('Please select the proper options in location'));
        }
      }
      else {
        $form_state->setErrorByName('location_parent', $this->t('Something went wrong, Please reload and try again.'));
      }
    }
    // Validation for file upload.
    if (empty($fileUploaded)) {
      $form_state->setErrorByName('file', $this->t('File is required'));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $userInput = $form_state->getUserInput();
    $termId = $userInput['location_parent'] ?? NULL;
    $fileUploaded = $form_state->getValue(['file', 0]) ?? NULL;
    $locationSchemaTerm = $form_state->getValue('location_schema') ?? NULL;
    // Prepare the data for batch process.
    $details = [
      'parentTermId' => $termId,
      'locationSchemaTerm' => $locationSchemaTerm,
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
            '\Drupal\rte_mis_core\Batch\LocationTermBatch::import',
            [$fileUploaded, $details],
          ],
          ],
          'progressive' => TRUE,
          'progress_message' => '@percentage% complete. Time elapsed: @elapsed, estimated time remaining: @estimate.',
          'finished' => '\Drupal\rte_mis_core\Batch\LocationTermBatch::finishedCallback',
        ];

        batch_set($batch);
      }
    }
  }

}
