<?php

namespace Drupal\rte_mis_report\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Form to build filters for school report.
 */
class SchoolReportFilterForm extends FormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs the CustomForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $route_match) {
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
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
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * Returns the unique ID for the form.
   */
  public function getFormId() {
    return 'school_report_filter_form';
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
    // Get the school settings.
    $school_config = $this->configFactory()->get('rte_mis_school.settings');

    // Get available education levels.
    $education_levels = $school_config->get('field_default_options.field_education_level') ?? [];
    // Get available board types.
    $boards = $school_config->get('field_default_options.field_board_type') ?? [];

    $form['filter_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Filter schools by education level and board type'),
      '#tree' => FALSE,
    ];

    if ($this->getRequest()->query->get('education_level') || $this->getRequest()->query->get('board')) {
      $form['filter_wrapper']['#open'] = TRUE;
    }

    // Education level filter.
    $form['filter_wrapper']['education_level'] = [
      '#type' => 'select',
      '#title' => $this->t('Education Level'),
      '#options' => ['' => $this->t('- Any -')] + $education_levels,
      '#default_value' => $this->getRequest()->query->get('education_level') ?? '',
    ];

    // Board type filter.
    $form['filter_wrapper']['board'] = [
      '#type' => 'select',
      '#title' => $this->t('Board Type'),
      '#options' => ['' => $this->t('- Any -')] + $boards,
      '#default_value' => $this->getRequest()->query->get('board') ?? '',
    ];

    // Submit button.
    $form['filter_wrapper']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
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
    // Get the values from form state.
    $education_level = $form_state->getValue('education_level');
    $board = $form_state->getValue('board');

    $filter_query = [];
    // Add education level in the query if education level filter is applied.
    if ($education_level) {
      $filter_query['education_level'] = $education_level;
    }

    // Add board in the query if board filter is applied.
    if ($board) {
      $filter_query['board'] = $board;
    }

    // Get the current route name and parameters.
    $route_name = $this->routeMatch->getRouteName();
    $route_parameters = $this->routeMatch->getParameters()->all();

    // Get all query parameters.
    $query_params = $this->getRequest()->query->all();

    if (isset($query_params['medium'])) {
      // Append query params if medium filter is applied
      // from main report page.
      $filter_query += ['medium' => $query_params['medium']];
    }

    // Build the filter URL with query parameters.
    $url = Url::fromRoute($route_name, $route_parameters, ['query' => $filter_query])->toString();

    // Redirect to the reports page on filter form submission.
    $response = new RedirectResponse($url);
    $response->send();
  }

}
