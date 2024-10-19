<?php

namespace Drupal\rte_mis_report\Form;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\cshs\Component\CshsOption;
use Drupal\cshs\Element\CshsElement;
use Drupal\eck\EckEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Custom form to submit a search value.
 */
class HabitationReportForm extends FormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs the CustomForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   *
   * Dependency injection through the container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   The current instance of the form.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * Returns the unique ID for the form.
   */
  public function getFormId() {
    return 'habitation_mapping_form';
  }

  /**
   * {@inheritdoc}
   *
   * Builds the form with a text field and a submit button.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The form structure array.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Text field for input.
    // Radio buttons for selecting options.
    $form['field_selector'] = [
      '#type' => 'radios',
      '#title' => NULL,
      '#required' => TRUE,
      '#options' => [
        'location' => $this->t('By Location'),
        'udise' => $this->t('By Udise Code'),
      ],
      '#required' => TRUE,
      '#default_value' => $this->getRequest()->query->get('field_selector') ?? 'location',
    ];

    $configSettings = $this->configFactory()->get('rte_mis_core.settings');
    // Get the categorization setting from config.
    $categorizationDepth = $configSettings->get('location_schema.depth');
    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
    $locationTerms = $termStorage->loadTree('location', 0, $categorizationDepth, TRUE);
    $locationParentTerms = $termStorage->loadTree('location_schema', 0, $categorizationDepth, TRUE);
    $labels = [];
    foreach ($locationParentTerms as $term) {
      $term = $this->entityRepository->getTranslationFromContext($term);
      $labels[] = $term->label();
    }

    $options = [];
    foreach ($locationTerms as $term) {
      $term = $this->entityRepository->getTranslationFromContext($term);
      $options[(int) $term->id()] = new CshsOption($term->label(), (int) $term->parent->target_id == 0 ? NULL : $term->parent->target_id);
    }

    // Get the rte_mis_school settings.
    $schoolConfigSettings = $this->configFactory()->get('rte_mis_school.settings');

    $form['location_wrapper'] = [
      '#type' => 'fieldset',
      '#attributes' => [
        'id' => ['location-wrapper'],
      ],
      '#tree' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="field_selector"]' => ['value' => 'location'],
        ],
      ],
    ];

    // Urban or Rural select list.
    $form['location_wrapper']['type_of_area'] = [
      '#type' => 'select',
      '#title' => $this->t('Type of Area'),
      '#options' => $schoolConfigSettings->get('field_default_options.field_type_of_area') ?? [],
      '#states' => [
        'visible' => [
          ':input[name="field_selector"]' => ['value' => 'location'],
        ],
      ],
    ];
    if ($this->getRequest()->query->get('field_selector') == 'location') {
      $form['location_wrapper']['type_of_area']['#default_value'] = $this->getRequest()->query->get('additional') ?? '';
    }

    $form['location_wrapper']['location'] = [
      '#type' => CshsElement::ID,
      '#title' => $this->t('Location'),
      '#description' => $this->t('Select location for the school list.'),
      '#labels' => $labels,
      '#options' => $this->getLocation(),
      '#no_first_level_none' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="field_selector"]' => ['value' => 'location'],
        ],
        'required' => [
          ':input[name="field_selector"]' => ['value' => 'location'],
        ],
      ],
      '#default_value' => !empty($location) ? $location : NULL,
    ];
    if ($this->getRequest()->query->get('field_selector') == 'location') {
      $form['location_wrapper']['location']['#default_value'] = $this->getRequest()->query->get('key') ?? '';
    }

    $form['udise_wrapper'] = [
      '#type' => 'fieldset',
      '#attributes' => [
        'id' => ['udise-wrapper'],
      ],
      '#tree' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="field_selector"]' => ['value' => 'udise'],
        ],
      ],
    ];
    // Text field for udise code option.
    $form['udise_wrapper']['udise_code'] = [
      '#type' => 'select2',
      '#title' => $this->t('School Udise Code'),
      '#select2' => [
        'allowClear' => FALSE,
      ],
      '#options' => $this->getSchoolList(),
      '#states' => [
        'visible' => [
          ':input[name="field_selector"]' => ['value' => 'udise'],
        ],
        'required' => [
          ':input[name="field_selector"]' => ['value' => 'udise'],
        ],
      ],
    ];
    if ($this->getRequest()->query->get('field_selector') == 'udise') {
      $form['udise_wrapper']['udise_code']['#default_value'] = $this->getRequest()->query->get('key') ?? '';
    }

    // Submit button.
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
    // Get the selected field selector value.
    $field_selector = $form_state->getValue('field_selector') ?? NULL;

    // Validate location field if location is selected.
    if ($field_selector === 'location') {
      $location = $form_state->getValue('location');
      if (empty($location)) {
        $form_state->setErrorByName('location', $this->t('The location field cannot be empty when "By Location" is selected.'));
      }
    }

    // Validate udise field if udise is selected.
    if ($field_selector === 'udise') {
      $udise = $form_state->getValue('udise_code');
      if (empty($udise)) {
        $form_state->setErrorByName('udise_code', $this->t('The Udise code field cannot be empty when "By Udise Code" is selected.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Handles the form submission by redirecting to a page with query parameters.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get the submitted value.
    $field_selector = $form_state->getValue('field_selector') ?? NULL;
    if ($field_selector == 'location') {
      $value = $form_state->getValue('location');
      $additional = $form_state->getValue('type_of_area');
    }
    elseif ($field_selector == 'udise') {
      $value = $form_state->getValue('udise_code');
    }

    $route_parameters = [
      'field_selector' => $field_selector,
      'key' => $value,
    ];

    // Check if $additional is set and not empty, and add it to the parameters.
    if (!empty($additional)) {
      $route_parameters['additional'] = $additional;
    }

    // Generate the URL for redirection with the form value
    // as a query parameter.
    $url = Url::fromRoute('rte_mis_report.controller.habitation_mapping', $route_parameters)->toString();

    // Redirect to the table page with the form value in the URL.
    $response = new RedirectResponse($url);
    $response->send();
  }

  /**
   * Get the list of school.
   */
  private function getSchoolList() {
    $options = [];
    $schools = $this->entityTypeManager->getStorage('mini_node')->getQuery()
      ->condition('type', 'school_details')
      ->condition('field_academic_year', _rte_mis_core_get_current_academic_year())
      ->condition('field_school_verification', 'school_registration_verification_approved_by_deo')
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->execute();
    // Load school in batches.
    $school_chunks = array_chunk($schools, 100);
    foreach ($school_chunks as $chunk) {
      $school_mini_nodes = $this->entityTypeManager->getStorage('mini_node')->loadMultiple($chunk);
      foreach ($school_mini_nodes as $school_mini_node) {
        if ($school_mini_node instanceof EckEntityInterface) {
          $options[$school_mini_node->get('field_udise_code')->getString()] = $this->entityTypeManager->getStorage('taxonomy_term')->load($school_mini_node->get('field_udise_code')->getString())->label();
        }
      }
    }
    return $options;
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

}
