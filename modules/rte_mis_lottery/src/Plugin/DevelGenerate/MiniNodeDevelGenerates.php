<?php

namespace Drupal\rte_mis_lottery\Plugin\DevelGenerate;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\devel_generate\DevelGenerateBase;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a MiniNodeDevelGenerate plugin.
 *
 * @DevelGenerate(
 *   id = "mini_node_student_detailss",
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
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Provides system time.
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   * @param \Drupal\Core\Extension\ExtensionPathResolver $extension_path_resolver
   *   The extension path resolver service.
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
    UrlGeneratorInterface $url_generator,
    DateFormatterInterface $date_formatter,
    TimeInterface $time,
    Connection $database,
    ExtensionPathResolver $extension_path_resolver,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $messenger, $language_manager, $module_handler, $string_translation);
    $this->miniNodeStorage = $entity_type_manager->getStorage('mini_node');
    $this->urlGenerator = $url_generator;
    $this->dateFormatter = $date_formatter;
    $this->time = $time;
    $this->database = $database;
    $this->extensionPathResolver = $extension_path_resolver;
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
      $container->get('url_generator'),
      $container->get('date.formatter'),
      $container->get('datetime.time'),
      $container->get('database'),
      $container->get('extension.path.resolver')
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
    $form['mini_node_types'] = [
      '#type' => 'radios',
      '#title' => $this->t('Mini Node type'),
      '#options' => [
        'school_details' => $this->t('School Details'),
        'student_details' => $this->t('Student Details'),
      ],
    ];

    $form['kill'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<strong>Delete all mini nodes</strong> before generating new content.'),
      '#default_value' => $this->getSetting('kill'),
    ];
    $form['num'] = [
      '#type' => 'number',
      '#title' => $this->t('How many mini nodes would you like to generate?'),
      '#default_value' => $this->getSetting('num'),
      '#required' => TRUE,
      '#min' => 0,
    ];

    $form['#redirect'] = FALSE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsFormValidate(array $form, FormStateInterface $form_state): void {
    $mini_node_type = $form_state->getValue('mini_node_types') ?? NULL;
    if ($form_state->getValue('num') <= 0) {
      $form_state->setErrorByName('num', $this->t('Please enter a positive number of mini nodes to generate.'));
    }
    if (!isset($mini_node_type)) {
      $form_state->setErrorByName('mini_node_types', $this->t('Please select at least one mini node type'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function generateElements(array $values): void {
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
    if (!empty($values['kill'])) {
      $this->deleteExistingMiniNodes();
    }

    for ($i = 0; $i < $values['num']; $i++) {
      $this->createMiniNode($values['mini_node_types']);
    }
    $this->setMessage($this->formatPlural($values['num'], 'Created 1 mini node', 'Created @count mini nodes'));
  }

  /**
   * Generate mini nodes in batch mode.
   *
   * This method is used when the number of elements is 50 or more.
   */
  private function generateBatchMiniNodes(array $values): void {
    $operations = [];

    if (!empty($values['kill'])) {
      $operations[] = ['devel_generate_operation', [$this, 'batchDeleteExistingMiniNodes', $values]];
    }

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
  private function deleteExistingMiniNodes(): void {
    $entities = $this->miniNodeStorage->loadByProperties(['type' => 'student_details']);
    $this->miniNodeStorage->delete($entities);
  }

  /**
   * Batch wrapper for deleting existing mini nodes.
   */
  public function batchDeleteExistingMiniNodes(array $values, array &$context): void {
    $this->deleteExistingMiniNodes();
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
      $this->createMiniNode($values['mini_node_types']);
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
  private function createMiniNode($type = ''): void {
    $values = [];
    switch ($type) {
      case 'student_details':
        $parent_type_options = ['father_mother', 'single_parent', 'guardian'];
        $single_parent_options = ['father', 'mother'];
        // To add school preference in student.
        // 1. School taxonomy must be must be created first.
        // 2. School mini_node should be created.
        // 3. Student matching all above criteria should have school preference.
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
        $values = [
          'type' => 'student_details',
          'field_student_name' => $this->generateRandomString(6),
          'field_mobile_number' => $this->generateRandomMobileNumber(),
          'field_academic_year' => _rte_mis_core_get_current_academic_year(),
          'field_student_verification' => 'student_workflow_approved',
          'field_student_application_number' => $this->generateRandomString(11),
          'field_location' => $this->generateRandomSelectValue('field_location'),
          'field_date_of_birth' => date('2019-01-01'),
          'field_gender' => ['transgender', 'boy', 'girl'],
          'field_parent_type' => [$parent_type],
          'field_school_preferences' => [
            $this->generateParagraph('field_school_preferences', ['school_id' => 9]),
          ],
        ];
        $values = $values + $parent_values;
        break;

      case 'school_details':
        $location = $this->generateRandomSelectValue('field_location');
        $values = [
          'type' => 'school_details',
          'field_school_name' => $this->generateRandomString(6),
          'field_academic_year' => _rte_mis_core_get_current_academic_year(),
          'field_school_verification' => 'school_registration_verification_approved_by_deo',
          'field_location' => $location,
          'field_udise_code' => $this->generateRandomString(11),
          'field_entry_class' => [
            $this->generateParagraph('field_entry_class'),
          ],
          'field_habitations' => [
            'target_id' => $location,
          ],
        ];
        break;

      default:
        break;
    }
    if (!empty($values)) {
      $mini_node = $this->miniNodeStorage->create($values);
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
    return '+91' . substr(str_shuffle('6789'), 0, 2) . substr(str_shuffle('1234567890'), 0, 8);
  }

  /**
   * Generates a random select value.
   */
  private function generateRandomSelectValue($field_name): string {
    $options = [];
    switch ($field_name) {
      case 'field_location':
        $options = [22, 23];
        break;

      default:
        break;
    }
    return $options[array_rand($options)];
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
    switch ($paragraph_type) {
      case 'field_entry_class':
        $paragraph = Paragraph::create([
          'type' => 'entry_class',
          'field_education_type' => ['co-ed'],
          'field_entry_class' => [3],
          'field_total_student_for_english' => rand(0, 200),
          'field_rte_student_for_english' => rand(0, 200),
          'field_total_student_for_hindi' => rand(0, 200),
          'field_rte_student_for_hindi' => rand(0, 200),
        ]);
        break;

      case 'field_school_preferences':
        $medium_options = ['hindi', 'english'];
        $values = [
          'type' => 'school_preference',
          'field_school_id' => [
            'target_id' => $data['school_id'] ?? 1,
          ],
          'field_entry_class' => [3],
          // 'field_medium' => $medium_options[array_rand($medium_options)],
          'field_medium' => ['english'],
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

}
