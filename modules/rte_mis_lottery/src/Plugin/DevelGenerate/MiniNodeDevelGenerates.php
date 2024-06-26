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
 *     "num" = 50,
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
    if ($form_state->getValue('num') <= 0) {
      $form_state->setErrorByName('num', $this->t('Please enter a positive number of mini nodes to generate.'));
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
      $this->createMiniNode();
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
    $num_per_batch = 1000;
    $remaining = $values['num'] - ($context['sandbox']['progress'] ?? 0);

    $count = min($num_per_batch, $remaining);
    for ($i = 0; $i < $count; $i++) {
      $this->createMiniNode();
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
  private function createMiniNode(): void {
    $values = [
      'type' => 'student_details',
      'field_student_name' => $this->generateRandomString(6),
      'field_mobile_number' => $this->generateRandomMobileNumber(),
      'field_academic_year' => _rte_mis_core_get_current_academic_year(),
      'field_student_verification' => 'student_workflow_approved',
      'field_student_application_number' => $this->generateRandomString(11),
      'field_location' => $this->generateRandomSelectValue('field_location'),
    ];

    $mini_node = $this->miniNodeStorage->create($values);
    $mini_node->save();
  }

  /**
   * Batch wrapper for creating mini nodes.
   */
  public function batchCreateMiniNode(array $values, array &$context): void {
    $this->createMiniNode();
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
        $options = [22, 23, 26, 25, 12, 14, 15, 16, 19, 20, 29, 30, 32, 33, 43, 44];
        break;

      default:
        break;
    }
    return $options[array_rand($options)];
  }

}
