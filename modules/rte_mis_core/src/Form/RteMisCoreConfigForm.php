<?php

namespace Drupal\rte_mis_core\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * RTE MIS Core config form.
 */
class RteMisCoreConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'rte_mis_core.settings';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs the service objects.
   *
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rte_mis_core_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['#tree'] = TRUE;
    $locationSchemaOptions = $this->getLocationSchemaOptions();
    $form['location_schema'] = [
      '#type' => 'details',
      '#title' => $this->t('Location Settings'),
      '#open' => TRUE,
      '#attributes' => [
        'id' => ['form-ajax-wrapper'],
      ],
    ];
    $form['location_schema']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Categories location into <b>Rural/Urban</b>.'),
      '#default_value' => $config->get('location_schema.enable') ?? 0,
    ];

    $form['location_schema']['rural'] = [
      '#type' => 'select2',
      '#title' => $this->t('Select schema that should marked as <b>Rural</b> while creating location.'),
      '#options' => $locationSchemaOptions,
      '#default_value' => $config->get('location_schema.rural') ?? NULL,
      '#required' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="location_schema[enable]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['location_schema']['urban'] = [
      '#type' => 'select2',
      '#title' => $this->t('Select schema that should marked as <b>Urban</b> while creating location.'),
      '#options' => $locationSchemaOptions,
      '#default_value' => $config->get('location_schema.urban') ?? NULL,
      '#required' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="location_schema[enable]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * Get the option for `location_schema` vocabulary.
   */
  protected function getLocationSchemaOptions() {
    $options = [];
    $locationSchemaTerms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
      'vid' => 'location_schema',
      'status' => '1',
    ]);
    foreach ($locationSchemaTerms as $term) {
      $options[$term->id()] = $term->label();
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $ruralCategory = $form_state->getValue(['location_schema', 'rural']) ?? NULL;
    $urbanCategory = $form_state->getValue(['location_schema', 'urban']) ?? NULL;
    $enableCategorizing = $form_state->getValue(['location_schema', 'enable']);
    if ($ruralCategory == $urbanCategory && $enableCategorizing) {
      $form_state->setErrorByName('location_schema', $this->t('Common schema selected in rural and urban.'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = $this->configFactory()->getEditable(static::SETTINGS);
    $config->set('location_schema.enable', $values['location_schema']['enable']);
    $config->set('location_schema.rural', $values['location_schema']['rural'] ?? NULL);
    $config->set('location_schema.urban', $values['location_schema']['urban'] ?? NULL);
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
