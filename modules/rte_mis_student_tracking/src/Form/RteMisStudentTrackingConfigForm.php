<?php

namespace Drupal\rte_mis_student_tracking\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * RTE MIS Student Tracking config form.
 */
class RteMisStudentTrackingConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'rte_mis_student_tracking.settings';

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs the service objects.
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
    return 'rte_mis_student_tracking_settings';
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
    $entry_class = $this->config('rte_mis_school.settings')->get('field_default_options.class_level') ?? [];
    $config = $this->config(static::SETTINGS);

    // Define the permission based on the current logged in user.
    $permission = [
      'entry_class_list' => TRUE,
      'student_autorenewal' => TRUE,
    ];

    if (!$this->currentUser->hasPermission('administer site configuration')
      && $this->currentUser->hasPermission('change student renewal date')) {
      $permission['entry_class_list'] = FALSE;
    }

    $form['student_autorenewal_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Student Auto-Renewal'),
      '#open' => TRUE,
      '#access' => $permission['student_autorenewal'],
    ];
    $form['student_autorenewal_container']['renewal_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Auto-Renewal Date'),
      '#description' => $this->t("Select the date on which the student's auto-renewal will occur."),
      '#default_value' => $config->get('renewal_date') ?? NULL,
      '#required' => TRUE,
      '#attributes' => [
        'min' => date('Y-m-d'),
      ],
    ];

    $form['allowed_class_list_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Allowed Class List'),
      '#open' => TRUE,
      '#access' => $permission['entry_class_list'],
    ];
    $form['allowed_class_list_container']['allowed_class_list'] = [
      '#type' => 'select2',
      '#title' => $this->t('Class list'),
      '#description' => $this->t('Select the multiple class list that will be populated in Entry Class|Current Class.'),
      '#default_value' => $config->get('allowed_class_list') ?? NULL,
      '#multiple' => TRUE,
      '#required' => TRUE,
      '#options' => $entry_class,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (empty($values['renewal_date'])) {
      $form_state->setErrorByName('renewal_date', $this->t('Renewal date cannot be empty.'));
    }
    if (empty($values['allowed_class_list'])) {
      $form_state->setErrorByName('allowed_class_list', $this->t('Allowed class list cannot be empty.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = $this->configFactory()->getEditable(static::SETTINGS);
    $config->set('renewal_date', $values['renewal_date']);
    $config->set('allowed_class_list', $values['allowed_class_list']);
    $config->save();
  }

}
