<?php

namespace Drupal\rte_mis_lottery\Plugin\DevelGenerate;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\devel_generate\DevelGenerateBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a LotteryDataDevelGenerates plugin.
 *
 * @DevelGenerate(
 *   id = "lottery_data",
 *   label = @Translation("Create lottery dummy data."),
 *   description = @Translation("Generate a given number of lottery data."),
 *   permission = "administer devel_generate",
 *   url = "lottery_data",
 *   settings = {
 *     "num" = 1,
 *   }
 * )
 */
class LotteryDataDevelGenerates extends DevelGenerateBase implements ContainerFactoryPluginInterface {

  /**
   * The extension path resolver service.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected $extensionPathResolver;

  /**
   * Constructs a new LotteryDataDevelGenerates instance.
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
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $messenger, $language_manager, $module_handler, $string_translation);
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
    $form['num'] = [
      '#type' => 'number',
      '#title' => $this->t('How many lottery records would you like to generate?'),
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
      $form_state->setErrorByName('num', $this->t('Please enter a positive number of lottery records to generate.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function generateElements(array $values): void {
    if ($values['num'] >= 50) {
      $this->generateLotteryDataBatch($values);
    }
    else {
      $this->generateLotteryData($values);
    }
  }

  /**
   * Generate records when not in batch mode.
   *
   * This method is used when the number of elements is under 50.
   */
  private function generateLotteryData(array $values): void {
    for ($i = 0; $i < $values['num']; $i++) {
      $this->createLotteryData();
    }
    $this->setMessage($this->formatPlural($values['num'], 'Created 1 record', 'Created @count records'));
  }

  /**
   * Generate records in batch mode.
   *
   * This method is used when the number of elements is 50 or more.
   */
  private function generateLotteryDataBatch(array $values): void {
    $operations = [];

    $operations[] = ['devel_generate_operation', [$this, 'batchCreateDatabaseRecords', $values]];

    $batch = [
      'title' => $this->t('Generating Records'),
      'operations' => $operations,
      'finished' => 'devel_generate_batch_finished',
      'file' => $this->extensionPathResolver->getPath('module', 'devel_generate') . '/devel_generate.batch.inc',
    ];

    batch_set($batch);
  }

  /**
   * Creates multiple records in batch mode.
   */
  public function batchCreateDatabaseRecords(array $values, array &$context): void {
    // Number of nodes to create per batch.
    $num_per_batch = 50;
    $remaining = $values['num'] - ($context['sandbox']['progress'] ?? 0);

    $count = min($num_per_batch, $remaining);
    for ($i = 0; $i < $count; $i++) {
      $this->createLotteryData();
    }

    // Update progress.
    $context['sandbox']['progress'] = ($context['sandbox']['progress'] ?? 0) + $count;
    $context['finished'] = $context['sandbox']['progress'] / $values['num'];

    if ($context['finished'] >= 1) {
      $this->setMessage($this->formatPlural($values['num'], 'Created 1 record', 'Created @count records'));
    }
  }

  /**
   * Creates a database record.
   */
  private function createLotteryData($type = ''): void {
    switch (array_rand(['Allotted', 'Un-alloted'])) {
      case 0:
        $medium = ['english', 'hindi'];
        $value = [
          'allotted_school_id' => rand(1, 9999),
          'school_udise_code' => rand(10000000000, 99999999999),
          'entry_class' => rand(1, 3),
          'medium' => $medium[array_rand($medium)],
          'allocation_status' => 'Allotted',
        ];
        break;

      case 1:
        $value = [
          'allocation_status' => 'Un-alloted',
        ];
        break;

      default:
        $value = [];
        break;
    }
    $data = [
      'student_id' => rand(1, 9999),
      'student_name	' => $this->generateRandomString(6),
      'student_application_number' => $this->generateRandomString(11),
      'mobile_number' => $this->generateRandomMobileNumber(),
      'lottery_type	' => 'internal',
      'academic_session' => _rte_mis_core_get_current_academic_year(),
    ] + $value;
    \Drupal::service('rte_mis_lottery.lottery_helper')->updateLotteryResult($data);
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
    return '0' . substr(str_shuffle('789'), 0, 2) . substr(str_shuffle('1234567890'), 0, 8);
  }

}
