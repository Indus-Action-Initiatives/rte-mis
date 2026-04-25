<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Contact Us page settings with dynamic Add More cards.
 */
final class ContactPageSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'rte_mis_home_contact_page_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['rte_mis_home.contact_page_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('rte_mis_home.contact_page_settings');
    $cards = $config->get('contact_cards') ?: [];

    // Store state on first load
    if ($form_state->get('num_cards') === NULL) {
      $form_state->set('num_cards', count($cards) ?: 1);
    }
    // Table State
    if ($form_state->get('num_table_rows') === NULL) {
      $form_state->set('num_table_rows', count($config->get('table_rows') ?: []) ?: 1);
    }

    $form['#tree'] = TRUE;

    $form['page_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Page Title'),
      '#default_value' => $config->get('page_title') ?: 'Contact Us',
      '#required' => TRUE,
    ];

    $form['table_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Top Table Title'),
      '#default_value' => $config->get('table_title') ?: 'Education and Sports Department - Mantralaya (Extension), Mumbai',
    ];

    $headers = $config->get('table_headers') ?: ['Name', 'Designation', 'Section/Desk', 'Address', 'Phone/email'];

    $form['table_headers'] = [
      '#type' => 'details',
      '#title' => $this->t('Table Column Headers'),
      '#open' => TRUE,
    ];
    $form['table_headers']['header_1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Column 1 Header (Name)'),
      '#default_value' => $headers[0] ?? 'Name',
    ];
    $form['table_headers']['header_2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Column 2 Header (Designation)'),
      '#default_value' => $headers[1] ?? 'Designation',
    ];
    $form['table_headers']['header_3'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Column 3 Header (Section/Desk)'),
      '#default_value' => $headers[2] ?? 'Section/Desk',
    ];
    $form['table_headers']['header_4'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Column 4 Header (Address)'),
      '#default_value' => $headers[3] ?? 'Address',
    ];
    $form['table_headers']['header_5'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Column 5 Header (Phone/email)'),
      '#default_value' => $headers[4] ?? 'Phone/email',
    ];

    $form['table_rows'] = [
      '#type' => 'details',
      '#title' => $this->t('Top Table Contacts'),
      '#open' => TRUE,
      '#attributes' => ['id' => 'table-rows-wrapper'],
    ];

    $num_table_rows = $form_state->get('num_table_rows');
    $stored_table_rows = $config->get('table_rows') ?: [];

    for ($i = 0; $i < $num_table_rows; $i++) {
      $row_data = $stored_table_rows[$i] ?? [];
      $form['table_rows'][$i] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['table-row-container']],
      ];
      $form['table_rows'][$i]['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
        '#default_value' => $row_data['name'] ?? '',
      ];
      $form['table_rows'][$i]['designation'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Designation'),
        '#default_value' => $row_data['designation'] ?? '',
      ];
      $form['table_rows'][$i]['section'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Section/Desk'),
        '#default_value' => $row_data['section'] ?? '',
      ];
      $form['table_rows'][$i]['address'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Address'),
        '#default_value' => $row_data['address'] ?? '',
      ];
      $form['table_rows'][$i]['contact'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Phone/Email'),
        '#default_value' => $row_data['contact'] ?? '',
      ];
    }

    $form['add_table_row'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Table Row'),
      '#submit' => ['::addTableRowSubmit'],
      '#ajax' => [
        'callback' => '::addTableRowCallback',
        'wrapper' => 'table-rows-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    $form['cards_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact Cards Section Title'),
      '#default_value' => $config->get('cards_title') ?: 'Office Contact Details',
    ];

    $form['contact_cards'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Office Contact Cards'),
      '#attributes' => ['id' => 'contact-cards-wrapper'],
    ];

    $num_cards = $form_state->get('num_cards');

    for ($i = 0; $i < $num_cards; $i++) {
      $card_data = $cards[$i] ?? [];
      $has_data = !empty($card_data['department_name']);

      $form['contact_cards'][$i] = [
        '#type' => 'details',
        '#title' => $has_data ? $card_data['department_name'] : $this->t('Contact Card @num', ['@num' => $i + 1]),
        '#open' => $has_data || $i === ($num_cards - 1),
      ];
      
      $form['contact_cards'][$i]['department_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Department Name'),
        '#default_value' => $card_data['department_name'] ?? '',
      ];
      
      $form['contact_cards'][$i]['address'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Address'),
        '#description' => $this->t('HTML breaks `<br>` allowed.'),
        '#default_value' => $card_data['address'] ?? '',
      ];

      $form['contact_cards'][$i]['phone'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Phone'),
        '#default_value' => $card_data['phone'] ?? '',
      ];

      $form['contact_cards'][$i]['email'] = [
        '#type' => 'email',
        '#title' => $this->t('Email'),
        '#default_value' => $card_data['email'] ?? '',
      ];

      $form['contact_cards'][$i]['jurisdiction'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Jurisdiction'),
        '#default_value' => $card_data['jurisdiction'] ?? '',
      ];
    }

    $form['add_card'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another Contact Card'),
      '#submit' => ['::addCardSubmit'],
      '#ajax' => [
        'callback' => '::addCardCallback',
        'wrapper' => 'contact-cards-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Submit handler for the "Add another Contact Card" button.
   */
  public function addCardSubmit(array &$form, FormStateInterface $form_state): void {
    $num_cards = $form_state->get('num_cards');
    $form_state->set('num_cards', $num_cards + 1);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for "Add another Contact Card".
   */
  public function addCardCallback(array &$form, FormStateInterface $form_state): array {
    return $form['contact_cards'];
  }

  /**
   * Submit handler for the "Add Table Row" button.
   */
  public function addTableRowSubmit(array &$form, FormStateInterface $form_state): void {
    $num_rows = $form_state->get('num_table_rows');
    $form_state->set('num_table_rows', $num_rows + 1);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for "Add Table Row".
   */
  public function addTableRowCallback(array &$form, FormStateInterface $form_state): array {
    return $form['table_rows'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Process Cards
    $raw_cards = $form_state->getValue('contact_cards') ?: [];
    $clean_cards = [];
    foreach ($raw_cards as $key => $card) {
      if (is_numeric($key) && !empty(trim($card['department_name']))) {
        $clean_cards[] = [
          'department_name' => trim($card['department_name']),
          'address' => trim($card['address']),
          'phone' => trim($card['phone']),
          'email' => trim($card['email']),
          'jurisdiction' => trim($card['jurisdiction']),
        ];
      }
    }

    // Process Table Rows
    $raw_rows = $form_state->getValue('table_rows') ?: [];
    $clean_rows = [];
    foreach ($raw_rows as $key => $row) {
      if (is_numeric($key) && (!empty(trim($row['name'])) || !empty(trim($row['designation'])))) {
        $clean_rows[] = [
          'name' => trim($row['name']),
          'designation' => trim($row['designation']),
          'section' => trim($row['section']),
          'address' => trim($row['address']),
          'contact' => trim($row['contact']),
        ];
      }
    }

    // Process Headers
    $header_1 = $form_state->getValue(['table_headers', 'header_1']);
    $header_2 = $form_state->getValue(['table_headers', 'header_2']);
    $header_3 = $form_state->getValue(['table_headers', 'header_3']);
    $header_4 = $form_state->getValue(['table_headers', 'header_4']);
    $header_5 = $form_state->getValue(['table_headers', 'header_5']);
    $clean_headers = [
      $header_1 ?: 'Name',
      $header_2 ?: 'Designation',
      $header_3 ?: 'Section/Desk',
      $header_4 ?: 'Address',
      $header_5 ?: 'Phone/email',
    ];

    $this->config('rte_mis_home.contact_page_settings')
      ->set('page_title', $form_state->getValue('page_title'))
      ->set('table_title', $form_state->getValue('table_title'))
      ->set('table_headers', $clean_headers)
      ->set('table_rows', $clean_rows)
      ->set('cards_title', $form_state->getValue('cards_title'))
      ->set('contact_cards', $clean_cards)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
