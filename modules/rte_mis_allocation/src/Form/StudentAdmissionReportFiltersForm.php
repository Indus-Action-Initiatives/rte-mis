<?php

namespace Drupal\rte_mis_allocation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form for filtering Student Admission Report.
 */
class StudentAdmissionReportFiltersForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'student_admission_report_filters_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL): array {
    $request = $this->getRequest();

    // Read values from URL (sync on reload)
    $form['admission_cycle'] = [
      '#type' => 'select',
      '#title' => $this->t('Admission Cycle'),
      '#options' => $this->getAcademicYearOptions(),
      '#default_value' => $request->query->get('admission_cycle', array_key_first($this->getAcademicYearOptions())),
    ];

    $form['application_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Application Status'),
      '#options' => [
        'verified'   => $this->t('Verified'),
        'duplicate'  => $this->t('Duplicate'),
        'incomplete' => $this->t('Incomplete'),
        'rejected'   => $this->t('Rejected'),
        'approved'   => $this->t('Approved'),
      ],
      '#default_value' => $request->query->get('application_status', 'approved'),
    ];

    $form['allotment_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Allotment Status'),
      '#options' => [
        'allotted'   => $this->t('Allotted'),
        'unallotted' => $this->t('Unallotted'),
      ],
      '#default_value' => $request->query->get('allotment_status', 'allotted'),
    ];

    $form['admission_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Admission Status'),
      '#options' => [
        'admitted'   => $this->t('Admitted'),
        'unadmitted' => $this->t('Unadmitted'),
      ],
      '#default_value' => $request->query->get('admission_status', 'admitted'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
    ];

    // IMPORTANT: GET method for filters.
    $form['#method'] = 'get';

    // Preserve route + ID.
    $form['#action'] = Url::fromRoute(
      'rte_mis_allocation.controller.student_admission_report',
      $id ? ['id' => $id] : []
    )->toString();

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * No submit logic needed.
   * GET form auto-appends query params.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Intentionally empty.
  }

  /**
   * Load academic year values dynamically from mini_nodes.
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
