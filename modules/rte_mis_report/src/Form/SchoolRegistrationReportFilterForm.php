<?php

namespace Drupal\rte_mis_report\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\metatag_dc\Plugin\metatag\Tag\Type;

/**
 * Form for filtering School Registration Report.
 */
class SchoolRegistrationReportFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'school_registration_report_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL): array {
    // Preserve all existing query parameters safely.
    $request = $this->getRequest();
    $status = $request->query->get('status');

    // Read values from URL (sync on reload)
    $form['admission_cycle'] = [
      '#type' => 'select',
      '#title' => $this->t('Admission Cycle'),
      '#options' => $this->getAcademicYearOptions(),
      '#default_value' => $request->query->get('admission_cycle', array_key_first($this->getAcademicYearOptions())),
    ];

    $form['pending_approvals'] = [
      '#type' => 'select',
      '#title' => $this->t('Pending Approvals By'),
      '#options' => [
        'all'   => $this->t('All'),
        'block_officer'   => $this->t('Block Officers'),
        'district_officer'  => $this->t('District Officers'),
      ],
      '#default_value' => $request->query->get('pending_approvals', 'all'),
    ];

    $form['mapping_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Mapping Status'),
      '#options' => [
        'mapped'   => $this->t('Mapped'),
        'unmapped' => $this->t('Unmapped'),
      ],
      '#default_value' => $request->query->get('mapping_status', 'mapped'),
    ];

    if ($status !== NULL && $status !== '') {
      $form['status'] = [
        '#type' => 'hidden',
        '#value' => $status,
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#button_type' => 'primary',
    ];

    $request = $this->getRequest();
    $path = $request->getPathInfo();
    $query = $request->query->all();

    // Get method for filters.
    $form['#method'] = 'get';

    $form['#action'] = Url::fromUserInput(
      $path,
      ['query' => $query]
    )->toString();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // No special submission handling required as we are using GET method.
  }

  /**
   * Helper function to get academic year options.
   */
  public function getAcademicYearOptions(): array {
    $storage = \Drupal::entityTypeManager()->getStorage('mini_node');

    // Fetch school_details years.
    $school_ids = $storage->getQuery()
      ->condition('type', 'school_details')
      ->accessCheck(FALSE)
      ->execute();

    // Fetch student_details years.
    $student_ids = $storage->getQuery()
      ->condition('type', 'student_details')
      ->accessCheck(FALSE)
      ->execute();

    $ids = array_unique(array_merge($school_ids, $student_ids));
    $options = [];

    if (!empty($ids)) {
      foreach ($storage->loadMultiple($ids) as $entity) {
        $year = $entity->get('field_academic_year')->getString();
        if ($year) {
          $label = str_replace('_', '–', $year);
          $options[$year] = $label;
        }
      }
    }

    krsort($options);
    return $options ?: ['2025-26' => '2025-26'];
  }

}
