<?php

namespace Drupal\rte_mis_school\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for adding student allocations.
 */
class AllocationForm extends FormBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new AllocationForm.
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
    return 'rte_mis_school_allocation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
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
    ];

    $form['school_id'] = [
      '#type' => 'number',
      '#title' => $this->t('School ID'),
      '#default_value' => 8,
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Admission Status'),
      '#options' => [
        'allotted' => $this->t('Allotted (Not yet admitted)'),
        'admitted' => $this->t('Admitted'),
      ],
      '#default_value' => 'allotted',
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Allocation'),
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
      
      $allocation = $storage->create([
        'type' => 'allocation',
        'title' => $form_state->getValue('student_name'),
      ]);

      if ($allocation->hasField('field_academic_year_allocation')) {
        $allocation->set('field_academic_year_allocation', $form_state->getValue('academic_year'));
      }

      if ($allocation->hasField('field_school')) {
        $allocation->set('field_school', $form_state->getValue('school_id'));
      }

      // Set workflow state if admitted
      if ($form_state->getValue('status') === 'admitted' && 
          $allocation->hasField('field_student_allocation_status')) {
        $allocation->set('field_student_allocation_status', [
          'to_sid' => 'student_admission_workflow_admitted',
        ]);
      }

      // If the bundle has a status/published field, publish the allocation.
      if ($allocation->hasField('status')) {
        $allocation->set('status', 1);
      }

      $allocation->save();

      // Invalidate dashboard cache so new allocation appears immediately.
      \Drupal\Core\Cache\Cache::invalidateTags(['rte_mis_school:dashboard']);

      $this->messenger()->addStatus($this->t('Allocation for "@name" has been created.', [
        '@name' => $form_state->getValue('student_name'),
      ]));

      $form_state->setRedirect('rte_mis_school.dashboard');
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error creating allocation: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

}
