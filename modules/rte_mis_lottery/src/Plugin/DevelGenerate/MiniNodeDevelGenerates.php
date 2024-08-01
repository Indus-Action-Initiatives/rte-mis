<?php

namespace Drupal\rte_mis_lottery\Plugin\DevelGenerate;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\cshs\Component\CshsOption;
use Drupal\cshs\Element\CshsElement;
use Drupal\devel_generate\DevelGenerateBase;
use Drupal\eck\EckEntityInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a MiniNodeDevelGenerate plugin.
 *
 * @DevelGenerate(
 *   id = "mini_node_student_details",
 *   label = @Translation("Mini Node Student Details Custom"),
 *   description = @Translation("Generate a given number of mini nodes with student details. Optionally delete current content."),
 *   url = "mini_node_student_details",
 *   permission = "administer devel_generate",
 *   settings = {
 *     "num" = 1,
 *     "kill" = FALSE,
 *   },
 *   dependencies = {
 *     "eck",
 *   },
 * )
 */
class MiniNodeDevelGenerates extends DevelGenerateBase implements ContainerFactoryPluginInterface {

  /**
   * The entity storage for mini_node.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $miniNodeStorage;

  /**
   * The url generator service.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Provides system time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The extension path resolver service.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected $extensionPathResolver;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  public $configFactory;

  /**
   * Constructs a new MiniNodeDevelGenerate instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The translation manager.
   * @param \Drupal\Core\Extension\ExtensionPathResolver $extension_path_resolver
   *   The extension path resolver service.
   * @param Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    MessengerInterface $messenger,
    LanguageManagerInterface $language_manager,
    ModuleHandlerInterface $module_handler,
    TranslationInterface $string_translation,
    ExtensionPathResolver $extension_path_resolver,
    ConfigFactoryInterface $config_factory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $messenger, $language_manager, $module_handler, $string_translation);
    $this->miniNodeStorage = $entity_type_manager->getStorage('mini_node');
    $this->extensionPathResolver = $extension_path_resolver;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration, $plugin_id, $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('language_manager'),
      $container->get('module_handler'),
      $container->get('string_translation'),
      $container->get('extension.path.resolver'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validateDrushParams(array $args, array $options = []): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $school_config = $this->configFactory->get('rte_mis_school.settings')->get('field_default_options');
    $student_config = $this->configFactory->get('rte_mis_student.settings')->get('field_default_options');
    // Get the class_type set in 'rte_mis_core.settings' configuration.
    $entry_class_options = [];
    $entry_class_type = $this->configFactory->get('rte_mis_core.settings')->get('entry_class.class_type') ?? NULL;
    $class_range = $school_config['field_default_entry_class'] ?? [];
    $entry_class_options += rte_mis_school_get_education_level_options($class_range['from'] ?? NULL, $class_range['to'] ?? NULL);
    if ($entry_class_type === 'dual') {
      $class_range = $school_config['field_optional_entry_class'] ?? [];
      $entry_class_options += rte_mis_school_get_education_level_options($class_range['from'] ?? NULL, $class_range['to'] ?? NULL);
    }

    $form['container'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Generate Students'),
    ];
    $form['container']['description'] = [
      '#type' => 'item',
      '#title' => $this->t("To create student. Follow the below points
      <li>Select the location(This should match the school habitation mapping)</li>
      <li>Select the schools you want the student to have preference(If school's mapped location does not matches with the location selected above, That school preferences will be discarded).</li>
      <li>Medium, Gender and Date Of Birth fields are optional. If not selected, default value will be submitted</li>
      "),
    ];
    $form['container']['locations'] = [
      '#type' => CshsElement::ID,
      '#title' => $this->t('Location'),
      '#description' => $this->t('Select the habitation which is mapped to school.'),
      '#options' => $this->getLocation(),
      '#required' => TRUE,
      '#no_first_level_none' => TRUE,
    ];
    $form['container']['schools'] = [
      '#type' => 'select2',
      '#title' => $this->t('School'),
      '#description' => $this->t('Select schools that are <b>Approved By Deo</b> and ready to be selected as preference.'),
      '#multiple' => TRUE,
      '#options' => $this->getSchoolList(),
      '#required' => TRUE,
    ];

    $form['container']['randomize_preference'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Randomize Preferences'),
      '#description' => $this->t('This will randomize the preferences for the selected school and medium'),
    ];

    $form['container']['medium'] = [
      '#type' => 'select2',
      '#title' => $this->t('Medium'),
      '#description' => $this->t('If medium is not selected. It is set to <b>All</b>'),
      '#multiple' => TRUE,
      '#options' => $school_config['field_medium'] ?? [],
    ];

    $form['container']['entry_class'] = [
      '#type' => 'radios',
      '#title' => $this->t('Entry Class'),
      '#description' => $this->t('Default class is 1st'),
      '#options' => $entry_class_options ?? [],
      '#default_value' => 3,
      '#required' => TRUE,
    ];

    $form['container']['gender'] = [
      '#type' => 'select2',
      '#title' => $this->t('Gender'),
      '#description' => $this->t('If gender is not selected. It is set to <b>Transgender</b>'),
      '#options' => $student_config['field_gender'] ?? [],
    ];

    $form['container']['dob'] = [
      '#type' => 'date',
      '#title' => $this->t('Date of Birth'),
      '#description' => $this->t('If dob is not provided, It is set to default value as <b>@date</b>', [
        '@date' => date('d-M-Y', strtotime('2019-01-01')),
      ]),
      '#attributes' => [
        'max' => date('Y-m-d'),
      ],
    ];

    $form['container']['num'] = [
      '#type' => 'number',
      '#title' => $this->t('How many student mini nodes would you like to generate?'),
      '#default_value' => $this->getSetting('num'),
      '#required' => TRUE,
      '#min' => 0,
    ];

    $form['container']['kill'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<strong>Delete all mini nodes</strong> before generating new content.'),
      '#default_value' => $this->getSetting('kill'),
    ];

    $form['#redirect'] = FALSE;

    return $form;
  }

  /**
   * Get the list of location.
   */
  private function getLocation() {
    $options = [];
    $locations = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', 0, NULL, FALSE);
    foreach ($locations as $value) {
      $options[$value->tid] = new CshsOption($value->name, (int) $value->parents[0] == 0 ? NULL : $value->parents[0]);
    }
    return $options;
  }

  /**
   * Get the list of school.
   */
  private function getSchoolList() {
    $options = [];
    $schools = $this->miniNodeStorage->getQuery()
      ->condition('type', 'school_details')
      ->condition('field_academic_year', _rte_mis_core_get_current_academic_year())
      ->condition('field_school_verification', 'school_registration_verification_approved_by_deo')
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->execute();
    // Load school in batches.
    $school_chunks = array_chunk($schools, 100);
    foreach ($school_chunks as $chunk) {
      $school_mini_nodes = $this->miniNodeStorage->loadMultiple($chunk);
      foreach ($school_mini_nodes as $school_mini_node) {
        if ($school_mini_node instanceof EckEntityInterface) {
          $options[$school_mini_node->id()] = $school_mini_node->get('field_school_name')->getString();
        }
      }
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsFormValidate(array $form, FormStateInterface $form_state): void {
    if (empty($form_state->getValue('locations'))) {
      $form_state->setErrorByName('locations', $this->t('Please select location.'));
    }
    if (empty($form_state->getValue('schools'))) {
      $form_state->setErrorByName('schools', $this->t('Please select at lease one school.'));
    }
    if ($form_state->getValue('num') <= 0) {
      $form_state->setErrorByName('num', $this->t('Please enter a positive number of mini nodes to generate.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function generateElements(array $values): void {
    if (!empty($values['kill'])) {
      $delete_student = $this->getStudentCount();
      if (count($delete_student) < 50) {
        $this->deleteExistingMiniNodes($delete_student);
      }
      else {
        $this->generateBatchMiniNodesDelete($delete_student);
      }
    }
    if ($values['num'] >= 50) {
      $this->generateBatchMiniNodes($values);
    }
    else {
      $this->generateMiniNodes($values);
    }
  }

  /**
   * Generate mini nodes when not in batch mode.
   *
   * This method is used when the number of elements is under 50.
   */
  private function generateMiniNodes(array $values): void {
    for ($i = 0; $i < $values['num']; $i++) {
      $this->createMiniNode($values);
    }
    $this->setMessage($this->formatPlural($values['num'], 'Created 1 mini node', 'Created @count mini nodes'));
  }

  /**
   * Create batch for deleting the student mini_node.
   */
  private function generateBatchMiniNodesDelete(array $values): void {
    $operations = [];
    $chunks = array_chunk($values, 50);
    foreach ($chunks as $data) {
      $operations[] = ['devel_generate_operation', [$this, 'batchDeleteExistingMiniNodes', $data]];
    }
    $batch = [
      'title' => $this->t('Deleting Student Mini Nodes'),
      'operations' => $operations,
      'finished' => 'devel_generate_batch_finished',
      'file' => $this->extensionPathResolver->getPath('module', 'devel_generate') . '/devel_generate.batch.inc',
    ];

    batch_set($batch);
  }

  /**
   * Generate mini nodes in batch mode.
   *
   * This method is used when the number of elements is 50 or more.
   */
  private function generateBatchMiniNodes(array $values): void {
    $operations = [];
    $operations[] = ['devel_generate_operation', [$this, 'batchCreateMiniNodes', $values]];
    $batch = [
      'title' => $this->t('Generating Mini Nodes'),
      'operations' => $operations,
      'finished' => 'devel_generate_batch_finished',
      'file' => $this->extensionPathResolver->getPath('module', 'devel_generate') . '/devel_generate.batch.inc',
    ];

    batch_set($batch);
  }

  /**
   * Deletes existing mini nodes.
   */
  private function deleteExistingMiniNodes(array $ids): void {
    if (!empty($ids)) {
      $entities = $this->miniNodeStorage->loadMultiple($ids);
      $this->miniNodeStorage->delete($entities);
    }
  }

  /**
   * Batch wrapper for deleting existing mini nodes.
   */
  public function batchDeleteExistingMiniNodes(array $ids, array &$context): void {
    $current_count = count($ids);
    $this->deleteExistingMiniNodes($ids);
    // Update progress.
    $context['sandbox']['progress'] = ($context['sandbox']['progress'] ?? 0) + $current_count;
    $context['finished'] = $context['sandbox']['progress'] / $current_count;
  }

  /**
   * Creates multiple mini nodes in batch mode.
   */
  public function batchCreateMiniNodes(array $values, array &$context): void {
    // Number of nodes to create per batch.
    $num_per_batch = 50;
    $remaining = $values['num'] - ($context['sandbox']['progress'] ?? 0);

    $count = min($num_per_batch, $remaining);
    for ($i = 0; $i < $count; $i++) {
      $this->createMiniNode($values);
    }
    // Update progress.
    $context['sandbox']['progress'] = ($context['sandbox']['progress'] ?? 0) + $count;
    $context['finished'] = $context['sandbox']['progress'] / $values['num'];
    if ($context['finished'] >= 1) {
      $this->setMessage($this->formatPlural($values['num'], 'Created 1 mini node', 'Created @count mini nodes'));
    }
  }

  /**
   * Creates a mini node.
   */
  private function createMiniNode($values): void {
    $school_config = $this->configFactory->get('rte_mis_school.settings')->get('field_default_options');
    $parent_type_options = ['father_mother', 'single_parent', 'guardian'];
    $single_parent_options = ['father', 'mother'];
    $parent_type = $parent_type_options[array_rand($parent_type_options)];
    $parent_values = [];
    switch ($parent_type) {
      case 'father_mother':
        $parent_values['field_father_name'] = $this->generateRandomString(6);
        $parent_values['field_mother_name'] = $this->generateRandomString(6);
        $parent_values['field_father_aadhar_number'] = rand(1000, 9999);
        $parent_values['field_mother_aadhar_number'] = rand(1000, 9999);
        break;

      case 'single_parent':
        $single_parent_type = $single_parent_options[array_rand($single_parent_options)];
        $parent_values['field_single_parent_type'] = $single_parent_type;
        if ($single_parent_type == 'father') {
          $parent_values['field_father_name'] = $this->generateRandomString(6);
          $parent_values['field_father_aadhar_number'] = rand(1000, 9999);
        }
        elseif ($single_parent_type == 'mother') {
          $parent_values['field_mother_name'] = $this->generateRandomString(6);
          $parent_values['field_mother_aadhar_number'] = rand(1000, 9999);
        }
        break;

      case 'guardian':
        $parent_values['field_guardian_name'] = $this->generateRandomString(6);
        $parent_values['field_gaurdian_aadhar_number'] = rand(1000, 9999);
        break;

      default:
        break;
    }
    $preferences = [];
    $medium_values = !empty($values['medium']) ? $values['medium'] : $school_config['field_medium'] ?? [];
    foreach ($medium_values as $medium_machine_name => $medium) {
      foreach ($values['schools'] ?? [] as $school) {
        $preferences[] = $this->generateParagraph('field_school_preferences', [
          'school_id' => $school,
          'field_medium' => $medium_machine_name,
          'field_entry_class' => $values['entry_class'],
        ]);
      }
    }
    if ($values['randomize_preference'] && !empty($preferences)) {
      $preferences = \Drupal::service('rte_mis_lottery.lottery_helper')->shuffleData($preferences);
    }
    $data = [
      'type' => 'student_details',
      'field_student_name' => $this->generateRandomString(6),
      'field_mobile_number' => $this->generateRandomMobileNumber(),
      'field_academic_year' => _rte_mis_core_get_current_academic_year(),
      'field_student_verification' => 'student_workflow_approved',
      'field_student_application_number' => $this->generateRandomString(11),
      'field_location' => $values['locations'] ?? '',
      'field_date_of_birth' => !empty($values['dob']) ? $values['dob'] : date('2019-01-01'),
      'field_gender' => !empty($values['gender']) ? $values['gender'] : 'transgender',
      'field_parent_type' => [$parent_type],
      'field_school_preferences' => $preferences,
    ];
    $data = $data + $parent_values;
    if (!empty($data)) {
      $mini_node = $this->miniNodeStorage->create($data);
      $mini_node->save();
    }
  }

  /**
   * Generates a random string.
   */
  private function generateRandomString(int $length = 12): string {
    return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
  }

  /**
   * Generates a random string.
   */
  private function generateRandomMobileNumber(): string {
    return '+919' . substr(str_shuffle('1234567890'), 0, 9);
  }

  /**
   * Create paragraph entity by providing the paragraph_type and data.
   *
   * @param string $paragraph_type
   *   The machine name of paragraph.
   * @param array $data
   *   The data that need to be created.
   */
  private function generateParagraph($paragraph_type = '', $data = []) {
    $paragraph = NULL;
    switch ($paragraph_type) {
      case 'field_school_preferences':
        // $medium_options = ['hindi', 'english'];
        $values = [
          'type' => 'school_preference',
          'field_school_id' => [
            'target_id' => $data['school_id'] ?? 1,
          ],
          'field_entry_class' => $data['field_entry_class'],
          // 'field_medium' => $medium_options[array_rand($medium_options)],
          'field_medium' => $data['field_medium'] ?? 'english',
        ];
        $paragraph = Paragraph::create($values);
        break;

      default:
        break;
    }

    if ($paragraph instanceof Paragraph) {
      $paragraph->save();
      return [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ];
    }
    return NULL;
  }

  /**
   * Get the count of student mini_node.
   */
  private function getStudentCount() {
    return $this->miniNodeStorage->getQuery()
      ->condition('field_academic_year', _rte_mis_core_get_current_academic_year())
      ->condition('field_student_verification', 'student_workflow_approved')
      ->condition('type', 'student_details')
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->execute();
  }

}
