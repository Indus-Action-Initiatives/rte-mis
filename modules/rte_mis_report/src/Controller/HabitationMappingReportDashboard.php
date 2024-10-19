<?php

namespace Drupal\rte_mis_report\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\rte_mis_report\Services\RteReportHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for displaying a table with an attached form.
 */
class HabitationMappingReportDashboard extends ControllerBase {

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  public $configFactory;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The rte mis helper service.
   *
   * @var \Drupal\rte_mis_report\Services\RteReportHelper
   */
  protected $rteReportHelper;

  /**
   * Constructs the controller instance.
   */
  public function __construct(FormBuilderInterface $formBuilder, RequestStack $requestStack, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entityTypeManager, Connection $database, RteReportHelper $rte_report_helper,) {
    $this->formBuilder = $formBuilder;
    $this->request = $requestStack->getCurrentRequest();
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entityTypeManager;
    $this->database = $database;
    $this->rteReportHelper = $rte_report_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('rte_mis_report.report_helper'),
    );
  }

  /**
   * Builds the page with a form and table.
   */
  public function build() {
    // Fetch query parameters from the URL.
    $query_params = $this->request->query->all();

    // Attach the form.
    $form = $this->formBuilder->getForm('Drupal\rte_mis_report\Form\HabitationReportForm');

    // Build the table header.
    $header = $this->getHeaders();
    // Fetch table data using the submitted form value.
    $rows = $this->getData($query_params);

    $build['form'] = $form;

    if (!empty($rows[0])) {
      // Add the Export to Excel button.
      $build['export_button'] = [
        '#type' => 'link',
        '#title' => $this->t('Export to Excel'),
        '#attributes' => ['class' => ['export-data-cta']],
      ];

      // Generate the URL for redirection with the form value
      // as a query parameter.
      $url = Url::fromRoute('rte_mis_report.export_habitation_mapping_excel', [], ['query' => $query_params]);

      $build['export_button']['#url'] = $url;
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#prefix' => '<div class="school-report-wrapper">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['school-reports']],
      '#empty' => $this->t('No data to display.'),
      '#cache' => [
        'contexts' => ['user'],
        'tags' => [
          'user_list',
          'taxonomy_term_list',
          'mini_node_list',
        ],
      ],
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];

    return $build;
  }

  /**
   * Function to get the headers.
   *
   * @return array
   *   An array of headers.
   */
  protected function getHeaders() {
    // For block admin.
    $header = [
      $this->t('No.'), $this->t('School Udise Code'), $this->t('Schools'), $this->t('School Mobile Number'), $this->t('Total Habitations'), $this->t('Habitations List'), $this->t('Total RTE Seats'),
    ];

    return (array) $header;
  }

  /**
   * Fetches table rows based on the submitted form value.
   *
   * @param array $params
   *   Form submitted values.
   */
  protected function getData(array $params) {
    $rows = [];
    // Get the query params.
    $selector = $params['field_selector'] ?? NULL;
    $key = $params['key'] ?? NULL;
    $additional = $params['additional'] ?? NULL;

    // If the selector has value udise and type of area is defined,
    // return [].
    if ($additional && $selector == 'udise') {
      return $rows;
    }
    // Get the list of locations.
    if ($selector == 'location') {
      $location_tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('location', $key, NULL, FALSE) ?? NULL;
      $locations = [];

      $locations[] = $key;
      if ($location_tree) {
        foreach ($location_tree as $value) {
          $locations[] = $value->tid;
        }
      }
      $rows[] = $this->requestData(NULL, $locations, $additional);
    }
    elseif ($selector == 'udise') {
      $rows[] = $this->requestData($key);
    }

    return $rows;
  }

  /**
   * Schools List based on requested paramters.
   *
   * @param string $key
   *   School udise code key.
   * @param array $locations
   *   The list of locations.
   * @param string $additional
   *   Type of area.
   *
   * @return array
   *   School details.
   */
  protected function requestData(?string $key = NULL, array $locations = [], ?string $additional = NULL) {
    $rows = [];
    // Get the config value for rte_mis_school.settings.
    $school_config = $this->configFactory->get('rte_mis_school.settings');
    $languages = $school_config->get('field_default_options.field_medium') ?? [];
    // Get the data.
    $data = $this->rteReportHelper->mappedHabitationQuery($key, $locations, $additional);
    $slno = 1;
    foreach ($data as $value) {
      $rows['slno'] = $slno;
      $rows['udise_code'] = $value->udise_code;
      $rows['school_name'] = $value->school_name;
      $rows['mobile_numer'] = $value->mobile_number;
      $habitations = explode(',', $value->mapped_habitation);
      // Number of habitations mapped.
      $rows['habitation_count'] = count($habitations);
      $rows['habitation_list'] = $value->mapped_habitation;
      $rows['total_seats'] = $this->rteReportHelper->eachSchoolSeatCountLanguage($languages, $value->id);
      $slno++;
    }

    return $rows;
  }

  /**
   * Function to download data in excel.
   */
  public function exportToExcel() {
    // Fetch query parameters from the URL.
    $query_params = $this->request->query->all();
    // Get the headers.
    $header = $this->getHeaders();
    // Get the row datas.
    $rows = $this->getData($query_params);
    // Count the maximum number of columns to be utilized.
    $max_columns = count($header);

    // Name of the file to be downloaded.
    $filename = 'habitation_mapping_report';
    $context = [
      'results' => [],
      'finished' => TRUE,
    ];

    return $this->rteReportHelper->excelDownload('Habitation-Mapping-Report', $header, $rows, $filename, $max_columns, $context);
  }

}
