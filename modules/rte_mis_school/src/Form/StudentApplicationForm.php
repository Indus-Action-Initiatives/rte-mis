<?php

namespace Drupal\rte_mis_school\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for adding student applications.
 */
class StudentApplicationForm extends FormBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new StudentApplicationForm.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rte_mis_school_student_application_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get current academic year
    $month = (int) date('n');
    $year = (int) date('Y');
    $current_year = $month >= 4 ? $year . '-' . ($year + 1) : ($year - 1) . '-' . $year;

    $form['student_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Student Name'),
      '#required' => TRUE,
    ];

    $form['academic_year'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Academic Year'),
      '#default_value' => $current_year,
      '#required' => TRUE,
      '#description' => $this->t('Format: YYYY-YYYY (e.g., 2025-2026)'),
    ];

    $form['school_id'] = [
      '#type' => 'number',
      '#title' => $this->t('School ID'),
      '#default_value' => 8,
      '#required' => TRUE,
      '#description' => $this->t('Taxonomy term ID of the school'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Student Application'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $storage = $this->entityTypeManager->getStorage('mini_node');
      
      $application = $storage->create([
        'type' => 'student_application',
        'title' => $form_state->getValue('student_name'),
      ]);

      // Add fields if they exist
      if ($application->hasField('field_academic_year')) {
        $application->set('field_academic_year', $form_state->getValue('academic_year'));
      }

      if ($application->hasField('field_preference_schools')) {
        $application->set('field_preference_schools', $form_state->getValue('school_id'));
      }

      // If the bundle has a status/published field, publish the application
      if ($application->hasField('status')) {
        $application->set('status', 1);
      }

      $application->save();

      // Invalidate dashboard cache so new applications appear immediately.
      \Drupal\Core\Cache\Cache::invalidateTags(['rte_mis_school:dashboard']);

      $this->messenger()->addStatus($this->t('Student application for "@name" has been created.', [
        '@name' => $form_state->getValue('student_name'),
      ]));

      $form_state->setRedirect('rte_mis_school.dashboard');
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error creating application: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

}
