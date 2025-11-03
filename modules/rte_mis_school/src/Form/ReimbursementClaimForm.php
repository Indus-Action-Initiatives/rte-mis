<?php

namespace Drupal\rte_mis_school\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for adding reimbursement claims.
 */
class ReimbursementClaimForm extends FormBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ReimbursementClaimForm.
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
    return 'rte_mis_school_reimbursement_claim_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $month = (int) date('n');
    $year = (int) date('Y');
    $current_year = $month >= 4 ? $year . '-' . ($year + 1) : ($year - 1) . '-' . $year;

    $form['claim_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Claim Title'),
      '#required' => TRUE,
      '#default_value' => 'Reimbursement Claim ' . $current_year,
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

    $form['amount_raised'] = [
      '#type' => 'number',
      '#title' => $this->t('Claim Amount Raised (₹)'),
      '#min' => 0,
      '#step' => 0.01,
      '#required' => TRUE,
      '#description' => $this->t('Total amount claimed'),
    ];

    $form['amount_received'] = [
      '#type' => 'number',
      '#title' => $this->t('Amount Received (₹)'),
      '#min' => 0,
      '#step' => 0.01,
      '#description' => $this->t('Amount actually received (leave blank if not received yet)'),
    ];

    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Claim Status'),
      '#options' => [
        'pending' => $this->t('Pending'),
        'approved' => $this->t('Approved'),
        'rejected' => $this->t('Rejected'),
      ],
      '#default_value' => 'pending',
    ];

    $form['approved_by'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Approved By'),
      '#description' => $this->t('Name of approving authority (if approved)'),
      '#states' => [
        'visible' => [
          ':input[name="status"]' => ['value' => 'approved'],
        ],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit Claim'),
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
      
      $claim = $storage->create([
        'type' => 'reimbursement_claim',
        'title' => $form_state->getValue('claim_title'),
      ]);

      if ($claim->hasField('field_academic_year')) {
        $claim->set('field_academic_year', $form_state->getValue('academic_year'));
      }

      if ($claim->hasField('field_school')) {
        $claim->set('field_school', $form_state->getValue('school_id'));
      }

      if ($claim->hasField('field_amount_raised')) {
        $claim->set('field_amount_raised', $form_state->getValue('amount_raised'));
      }

      if ($claim->hasField('field_amount_received') && $form_state->getValue('amount_received')) {
        $claim->set('field_amount_received', $form_state->getValue('amount_received'));
      }

      if ($claim->hasField('field_status')) {
        $claim->set('field_status', $form_state->getValue('status'));
      }


      if ($claim->hasField('field_approved_by') && $form_state->getValue('approved_by')) {
        $claim->set('field_approved_by', $form_state->getValue('approved_by'));
      }

      // If the bundle has a status/published field, publish the claim so dashboard can include approved/published claims
      if ($claim->hasField('status')) {
        $claim->set('status', 1);
      }

      $claim->save();

      // Invalidate dashboard cache so new claims appear immediately.
      \Drupal\Core\Cache\Cache::invalidateTags(['rte_mis_school:dashboard']);

      $this->messenger()->addStatus($this->t('Reimbursement claim "@title" has been submitted.', [
        '@title' => $form_state->getValue('claim_title'),
      ]));

      $form_state->setRedirect('rte_mis_school.dashboard');
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error creating claim: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

}
