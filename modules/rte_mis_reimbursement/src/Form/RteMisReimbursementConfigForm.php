<?php

namespace Drupal\rte_mis_reimbursement\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * RTE MIS Student Reimbursement config form.
 */
class RteMisReimbursementConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'rte_mis_reimbursement.settings';

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new RteMisReimbursementConfigForm object.
   *
   * Class constructor.
   */
  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rte_mis_reimbursement_settings';
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
    // Approval levels.
    $form['approval_level_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Reimbursement Approval Level'),
      '#open' => TRUE,
    ];
    $form['approval_level_container']['approval_level'] = [
      '#type' => 'select2',
      '#required' => TRUE,
      '#title' => $this->t('Select the approval levels'),
      '#options' => [
        'single' => $this->t('Single'),
        'dual' => $this->t('Dual'),
      ],
      '#default_value' => $config->get('approval_level') ?? NULL,
    ];

    // Supplementary fees.
    $form['supplementary_fees'] = [
      '#type' => 'details',
      '#title' => $this->t('Supplementary Fees'),
      '#description' => $this->t('Configure whether schools can raise fees reimbursement claim for supplementary items like school uniform, books etc.'),
      '#open' => TRUE,
    ];
    // Checkbox to enable supplementary fees reimbursement.
    $form['supplementary_fees']['enable_reimbursement'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow schools to raise supplementary fees reimbursement claim.'),
      '#default_value' => $config->get('supplementary_fees.enable_reimbursement') ?? 0,
    ];

    // Options for reimbursement claim types.
    $claim_type_options = [
      'board_type' => $this->t('Based on board type'),
      'claim_request' => $this->t('Based on claim request'),
    ];
    // Claim type for fees reimbursement.
    $form['supplementary_fees']['claim_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Reimbursement claim type'),
      '#description' => $this->t('Select the claim type for reimbursement of school uniform and books fees.'),
      '#options' => $claim_type_options ?? [],
      '#default_value' => $config->get('supplementary_fees.claim_type') ?? NULL,
      '#states' => [
        'visible' => [
          ':input[name="supplementary_fees[enable_reimbursement]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="supplementary_fees[enable_reimbursement]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Payment heads.
    $form['payment_heads'] = [
      '#type' => 'details',
      '#title' => $this->t('Payment Heads'),
      '#description' => $this->t('Configure payment heads settings.'),
      '#open' => TRUE,
    ];
    // Payment head type.
    $form['payment_heads']['payment_head_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Payment head'),
      '#description' => $this->t('Select the payment head for reimbursement claim settlement.'),
      '#required' => TRUE,
      '#options' => [
        'central_head' => $this->t('Central Head'),
        'state_head' => $this->t('State Head'),
        'both' => $this->t('Both'),
      ],
      '#default_value' => $config->get('payment_heads.payment_head_type') ?? 'central_head',
    ];

    // Prepare options for classes.
    $entry_class = $this->config('rte_mis_school.settings')->get('field_default_options.class_level') ?? [];
    // Class list for central head.
    $form['payment_heads']['central_class_list'] = [
      '#type' => 'select2',
      '#title' => $this->t('Class list for Central Head'),
      '#description' => $this->t('Select the multiple class list that will be included under this head.'),
      '#default_value' => $config->get('payment_heads.central_class_list') ?? NULL,
      '#multiple' => TRUE,
      '#options' => $entry_class,
      '#states' => [
        'visible' => [
          ':input[name="payment_heads[payment_head_type]"]' => [
            ['value' => 'central_head'],
            'or',
            ['value' => 'both'],
          ],
        ],
        'required' => [
          ':input[name="payment_heads[payment_head_type]"]' => [
            ['value' => 'central_head'],
            'or',
            ['value' => 'both'],
          ],
        ],
      ],
    ];

    // Class list for state head.
    $form['payment_heads']['state_class_list'] = [
      '#type' => 'select2',
      '#title' => $this->t('Class list for State Head'),
      '#description' => $this->t('Select the multiple class list that will be included under this head'),
      '#default_value' => $config->get('payment_heads.state_class_list') ?? NULL,
      '#multiple' => TRUE,
      '#options' => $entry_class,
      '#states' => [
        'visible' => [
          ':input[name="payment_heads[payment_head_type]"]' => [
            ['value' => 'state_head'],
            'or',
            ['value' => 'both'],
          ],
        ],
        'required' => [
          ':input[name="payment_heads[payment_head_type]"]' => [
            ['value' => 'state_head'],
            'or',
            ['value' => 'both'],
          ],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Show error if reimbursement is enabled but claim type is not selected.
    if (!empty($values['supplementary_fees']['enable_reimbursement']) && empty($values['supplementary_fees']['claim_type'])) {
      $form_state->setErrorByName('supplementary_fees[claim_type]', $this->t('Claim type cannot be empty when reimbursement is enabled.'));
    }
    // Show error if payment head is selected but classes are not added.
    if (!empty($values['payment_heads']['payment_head_type'])) {
      if (in_array($values['payment_heads']['payment_head_type'], ['both', 'central_head']) && empty($values['payment_heads']['central_class_list'])) {
        $form_state->setErrorByName('payment_heads[central_class_list]', $this->t('Central class list cannot be empty.'));
      }
      if (in_array($values['payment_heads']['payment_head_type'], ['both', 'state_head']) && empty($values['payment_heads']['state_class_list'])) {
        $form_state->setErrorByName('payment_heads[state_class_list]', $this->t('State class list cannot be empty.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = $this->configFactory()->getEditable(static::SETTINGS);
    $config->set('approval_level', $values['approval_level_container']['approval_level'] ?? NULL);
    $config->set('supplementary_fees.enable_reimbursement', $values['supplementary_fees']['enable_reimbursement']);
    $config->set('supplementary_fees.claim_type', $values['supplementary_fees']['claim_type'] ?? NULL);
    $config->set('payment_heads.payment_head_type', $values['payment_heads']['payment_head_type'] ?? NULL);
    $config->set('payment_heads.central_class_list', $values['payment_heads']['central_class_list'] ?? NULL);
    $config->set('payment_heads.state_class_list', $values['payment_heads']['state_class_list'] ?? NULL);
    $config->save();
  }

}
