<?php

namespace Drupal\rte_mis_reimbursement\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * RTE MIS Reimbursement config form.
 */
class RteMisReimbursementConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'rte_mis_reimbursement.settings';

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
      '#title' => $this->t('Reimbursement Approval Levels'),
      '#open' => TRUE,
    ];
    $form['approval_level_container']['approval_level'] = [
      '#type' => 'select2',
      '#required' => TRUE,
      '#title' => $this->t('Select the approval level'),
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

    // Checkbox to override supplementary fees reimbursement for central head.
    $form['supplementary_fees']['enable_central_reimbursement'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Configure central reimbursement settings'),
      '#description' => $this->t('By default central head reimburses all supplementary fees like school uniform and books fees. In order to configure or update default settings check this checkbox and configure below options.'),
      '#default_value' => $config->get('supplementary_fees.enable_central_reimbursement') ?? 0,
    ];

    // Build the fees configuration fields for central head.
    $this->buildFeesConfigurationFields($form, 'central');

    // // Checkbox to enable supplementary fees reimbursement.
    $form['supplementary_fees']['enable_state_reimbursement'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable state reimbursement settings'),
      '#description' => $this->t('On enabling this, below configured state level options for reimbursement claims will be considered.'),
      '#default_value' => $config->get('supplementary_fees.enable_state_reimbursement') ?? 0,
      '#states' => [
        'required' => [
          ':input[name="payment_heads[enable_state_head]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Build the fees configuration fields for state head.
    $this->buildFeesConfigurationFields($form, 'state');

    // Payment heads.
    $form['payment_heads'] = [
      '#type' => 'details',
      '#title' => $this->t('Payment Heads'),
      '#description' => $this->t('Configure payment heads settings.'),
      '#open' => TRUE,
    ];

    // Checkbox to enable state payment head for fees reimbursement.
    $form['payment_heads']['enable_state_head'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable state payment head'),
      '#description' => $this->t('<b>Note:</b> Central head is the default payment head for fees reimbursement if state head is enabled then both heads will be considered for reimbursement.'),
      '#default_value' => $config->get('payment_heads.enable_state_head') ?? 0,
      '#states' => [
        'required' => [
          ':input[name="supplementary_fees[enable_state_reimbursement]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Prepare options for classes.
    $available_classes = $this->config('rte_mis_school.settings')->get('field_default_options.class_level') ?? [];
    $available_classes = array_slice($available_classes, 11, 4, TRUE);

    // Class list for state head.
    $form['payment_heads']['state_class_list'] = [
      '#type' => 'select2',
      '#title' => $this->t('Class list for State Head'),
      '#description' => $this->t('Select the multiple class list that will be included under this head'),
      '#default_value' => $config->get('payment_heads.state_class_list') ?? NULL,
      '#multiple' => TRUE,
      '#options' => $available_classes,
      '#states' => [
        'visible' => [
          ':input[name="payment_heads[enable_state_head]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="payment_heads[enable_state_head]"]' => ['checked' => TRUE],
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

    // States API doesn't work properly for radio and checkbox fields
    // so need to add server side validations.
    // Validations for central and state level fees configurations.
    foreach (['central', 'state'] as $head) {
      // Show error if reimbursement is enabled but claim type is not selected.
      if (!empty($values['supplementary_fees']["enable_{$head}_reimbursement"]) && empty($values['supplementary_fees'][$head]['claim_type'])) {
        $form_state->setErrorByName("supplementary_fees[$head][claim_type]", $this->t('Claim type cannot be empty when fees reimbursement settings is enabled.'));
      }

      // Show error if claim type is board type but no board is selected.
      if (!empty($values['supplementary_fees'][$head]['claim_type'])
        && $values['supplementary_fees'][$head]['claim_type'] == 'board_type'
        && empty(array_filter($values['supplementary_fees'][$head]['board_options']))) {
        $form_state->setErrorByName("supplementary_fees[$head][board_options]", $this->t('Select at least one board when claim type is selected as board type.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = $this->configFactory()->getEditable(static::SETTINGS);

    // Set approval level value.
    $config->set('approval_level', $values['approval_level_container']['approval_level'] ?? '');
    // Set central reimbursement enablement status.
    $config->set(
      'supplementary_fees.enable_central_reimbursement',
      $values['supplementary_fees']['enable_central_reimbursement'] ?? 0,
    );
    // Set state reimbursement enablement status.
    $config->set(
      'supplementary_fees.enable_state_reimbursement',
      $values['supplementary_fees']['enable_state_reimbursement'] ?? 0,
    );

    // Set central fees options configuration for central and state.
    foreach (['central', 'state'] as $head) {
      // Set value for reimbursement claim type.
      $config->set(
        "supplementary_fees.{$head}.claim_type",
        $values['supplementary_fees'][$head]['claim_type'] ?? '',
      );

      $board_wise_config = [];
      // If board type claim is selected prepare config values for
      // each board else set empty array.
      if (!empty($values['supplementary_fees'][$head]['claim_type'])
        && $values['supplementary_fees'][$head]['claim_type'] == 'board_type') {
        // Prepare config value for fee options as per the selected board.
        foreach ($values['supplementary_fees'][$head]['board_options'] as $board => $selected) {
          if (!$selected) {
            continue;
          }
          $board_wise_config[$board] = !empty($values['supplementary_fees'][$head][$board])
            ? $values['supplementary_fees'][$head][$board]
            : [];
        }
      }
      // Set board wise fees configurations.
      $config->set(
        "supplementary_fees.{$head}.boards",
        $board_wise_config,
      );

      $fees_options = [];
      // Set fee configuration for claim request option if claim request opttion
      // is selected for claim.
      if (!empty($values['supplementary_fees'][$head]['claim_type'])
        && $values['supplementary_fees'][$head]['claim_type'] == 'claim_request') {
        $fees_options = $values['supplementary_fees'][$head]['fees_options'];
      }

      // Set fees options configurations for claim request option.
      $config->set(
        "supplementary_fees.{$head}.fees_options",
        $fees_options,
      );
    }

    // Set state head enablement status.
    $config->set(
      'payment_heads.enable_state_head',
      $values['payment_heads']['enable_state_head'] ?? 0,
    );

    // Set state head class list.
    $config->set(
      'payment_heads.state_class_list',
      $values['payment_heads']['state_class_list'] ?? [],
    );
    $config->save();
  }

  /**
   * Builds the fees configuration fields for the configuration form.
   *
   * @param array $form
   *   Reference to the form object.
   * @param string $head
   *   Value for the payment head.
   */
  protected function buildFeesConfigurationFields(array &$form, $head = 'central') {
    $config = $this->config(static::SETTINGS);
    // Options for reimbursement claim types.
    $claim_type_options = [
      'board_type' => $this->t('Based on board type'),
      'claim_request' => $this->t('Based on claim request'),
      'full' => $this->t('Full reimbursement'),
    ];
    // Allowed fees options for board wise reimbursement.
    $allowed_fees_options = $config->get('default_fees_options') ?? [];
    // Board options, applicable if board wise claim is selected.
    $board_options = $this->config('rte_mis_school.settings')->get('field_default_options.field_board_type') ?? [];

    // Claim type for fees reimbursement.
    $form['supplementary_fees'][$head]['claim_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Reimbursement claim type'),
      '#description' => $this->t(
        'To set board wise fees options select <b>Based on board type</b> option.<br>
        To set fees options for claim request select <b>Based on claim request</b> option.<br>
        For full reimbursement regardless of the claim raised by Schools select <b>Full reimbursement</b> option.'
      ),
      '#options' => $claim_type_options ?? [],
      '#default_value' => $config->get("supplementary_fees.{$head}.claim_type") ?? NULL,
      '#states' => [
        'visible' => [
          ":input[name=\"supplementary_fees[enable_{$head}_reimbursement]\"]" => ['checked' => TRUE],
        ],
        'required' => [
          ":input[name=\"supplementary_fees[enable_{$head}_reimbursement]\"]" => ['checked' => TRUE],
        ],
      ],
    ];

    // Board options.
    $form['supplementary_fees'][$head]['board_options'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select the board'),
      '#options' => $board_options,
      '#default_value' => array_keys($config->get("supplementary_fees.{$head}.boards") ?? []) ?? NULL,
      '#states' => [
        'visible' => [
          ":input[name=\"supplementary_fees[enable_{$head}_reimbursement]\"]" => ['checked' => TRUE],
          ":input[name=\"supplementary_fees[$head][claim_type]\"]" => [
            ['value' => 'board_type'],
          ],
        ],
        'required' => [
          ":input[name=\"supplementary_fees[enable_{$head}_reimbursement]\"]" => ['checked' => TRUE],
          ":input[name=\"supplementary_fees[$head][claim_type]\"]" => [
            ['value' => 'board_type'],
          ],
        ],
      ],
    ];

    // Allowed fees options for board wise reimbursement.
    foreach ($board_options as $board => $board_name) {
      $form['supplementary_fees'][$head][$board] = [
        '#type' => 'select2',
        '#title' => $this->t('Select the fees options for @board board', [
          '@board' => $board_name,
        ]),
        '#multiple' => TRUE,
        '#options' => $allowed_fees_options,
        '#default_value' => $config->get("supplementary_fees.{$head}.boards.$board") ?? NULL,
        '#states' => [
          'visible' => [
            ":input[name=\"supplementary_fees[enable_{$head}_reimbursement]\"]" => ['checked' => TRUE],
            ":input[name=\"supplementary_fees[$head][claim_type]\"]" => [
              ['value' => 'board_type'],
            ],
            ":input[name=\"supplementary_fees[$head][board_options][$board]\"]" => ['checked' => TRUE],
          ],
          'required' => [
            ":input[name=\"supplementary_fees[enable_{$head}_reimbursement]\"]" => ['checked' => TRUE],
            ":input[name=\"supplementary_fees[$head][claim_type]\"]" => [
              ['value' => 'board_type'],
            ],
            ":input[name=\"supplementary_fees[$head][board_options][$board]\"]" => ['checked' => TRUE],
          ],
        ],
      ];
    }

    // Allowed fees options for claim request based reimbursement.
    $form['supplementary_fees'][$head]['fees_options'] = [
      '#type' => 'select2',
      '#title' => $this->t("Select the fees options"),
      '#multiple' => TRUE,
      '#options' => $allowed_fees_options,
      '#default_value' => $config->get("supplementary_fees.{$head}.fees_options") ?? NULL,
      '#states' => [
        'visible' => [
          ":input[name=\"supplementary_fees[enable_{$head}_reimbursement]\"]" => ['checked' => TRUE],
          ":input[name=\"supplementary_fees[$head][claim_type]\"]" => [
            ['value' => 'claim_request'],
          ],
        ],
        'required' => [
          ":input[name=\"supplementary_fees[enable_{$head}_reimbursement]\"]" => ['checked' => TRUE],
          ":input[name=\"supplementary_fees[$head][claim_type]\"]" => [
            ['value' => 'claim_request'],
          ],
        ],
      ],
    ];
  }

}