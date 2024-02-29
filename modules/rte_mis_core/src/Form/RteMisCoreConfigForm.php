<?php

namespace Drupal\rte_mis_core\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
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
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs the service objects.
   *
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, AccountInterface $current_user) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user')
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

    // Define the permission based on the current logged in user.
    $permission = [
      'location_schema' => TRUE,
      'entry_class' => TRUE,
    ];

    if (!$this->currentUser->hasPermission('administer site configuration')
      && $this->currentUser->hasPermission('define entry class')) {
      $permission['location_schema'] = FALSE;
    }

    $form['#tree'] = TRUE;
    $locationSchemaOptions = $form_state->get('location_schema');
    if (empty($locationSchemaOptions)) {
      $locationSchemaOptions = $this->getLocationSchemaOptions();
      $form_state->set('location_schema', $locationSchemaOptions);
    }
    $locationSchemaOptions = $this->getLocationSchemaOptions();
    $form['location_schema'] = [
      '#type' => 'details',
      '#title' => $this->t('Location Settings'),
      '#open' => TRUE,
      '#attributes' => [
        'id' => ['form-ajax-wrapper'],
      ],
      '#access' => $permission['location_schema'],
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
    $form['entry_class'] = [
      '#type' => 'details',
      '#title' => $this->t('Entry Class Settings'),
      '#open' => TRUE,
      '#description' => $this->t('If `Single` is selected as entry class then Only Class-1 will be shown in entry class.
        If `Dual` is selected then School will get the option to select the entry class between KG-1 & Nursery. Class-1 will be selected by default as entry class.'),
      '#access' => $permission['entry_class'],
    ];
    $form['entry_class']['class_type'] = [
      '#type' => 'select2',
      '#required' => TRUE,
      '#title' => $this->t('Select the type of entry class'),
      '#options' => [
        'single' => $this->t('Single'),
        'dual' => $this->t('Dual'),
      ],
      '#default_value' => $config->get('entry_class.class_type') ?? NULL,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Get the option for `location_schema` vocabulary.
   */
  protected function getLocationSchemaOptions() {
    $options = [];
    $locationSchemaTerms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location_schema', 0, NULL, TRUE);
    foreach ($locationSchemaTerms as $term) {
      $options['option'][$term->id()] = $term->label();
      $options['depth'][$term->id()] = $term->depth;
    }
    return [
      'custom_options' => (object) $options,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $locationSchemaOptions = $form_state->get('location_schema');
    $ruralCategory = $form_state->getValue(['location_schema', 'rural']) ?? NULL;
    $urbanCategory = $form_state->getValue(['location_schema', 'urban']) ?? NULL;
    $enableCategorizing = $form_state->getValue(['location_schema', 'enable']);
    if ($ruralCategory == $urbanCategory && $enableCategorizing) {
      $form_state->setErrorByName('location_schema', $this->t('Common schema selected in rural and urban.'));
    }
    // Validate the depth, categorization should happen at same level.
    if (!empty($locationSchemaOptions)) {
      $ruralDepth = $locationSchemaOptions['custom_options']->depth[$ruralCategory] ?? NULL;
      $urbanDepth = $locationSchemaOptions['custom_options']->depth[$urbanCategory] ?? NULL;
      if ($ruralDepth != $urbanDepth && $enableCategorizing) {
        $form_state->setErrorByName('location_schema', $this->t('Please select location schema at same level.'));
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $locationSchemaOptions = $form_state->get('location_schema');
    $depth = $locationSchemaOptions["custom_options"]->depth[$values['location_schema']['urban']] ?? NULL;
    $config = $this->configFactory()->getEditable(static::SETTINGS);
    $config->set('location_schema.enable', $values['location_schema']['enable']);
    $config->set('location_schema.rural', $values['location_schema']['rural'] ?? NULL);
    $config->set('location_schema.urban', $values['location_schema']['urban'] ?? NULL);
    $config->set('entry_class.class_type', $values['entry_class']['class_type'] ?? NULL);
    $config->set('location_schema.depth', $depth);
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
