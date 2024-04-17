<?php

namespace Drupal\rte_mis_school\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * RTE MIS School config form.
 */
class RteMisSchoolConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'rte_mis_school.settings';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
  public function __construct(EntityTypeManagerInterface $entityTypeManager, AccountInterface $current_user) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rte_mis_school_settings';
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
    $form['school_verification'] = [
      '#type' => 'details',
      '#title' => $this->t('School Verification'),
      '#open' => TRUE,
    ];
    $form['school_verification']['single_approval'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable single approval process.'),
      '#description' => $this->t('Change the dual school verification to single approval process. Select the role below that can do the verification on both level.'),
      '#default_value' => $config->get('school_verification.single_approval') ?? 0,
      '#attributes' => [
        'id' => ['school_verification'],
      ],
    ];
    $form['school_verification']['single_approval_role'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Role.'),
      '#options' => [
        'block_admin' => $this->t('Block Admin'),
        'district_admin' => $this->t('District Admin'),
      ],
      '#default_value' => $config->get('school_verification.single_approval_role') ?? NULL,
      '#required' => TRUE,
      '#states' => [
        'visible' => [
          ':input[id="school_verification"]' => ['checked' => TRUE],
        ],
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable(static::SETTINGS);
    $values = $form_state->getValues();
    $config->set('school_verification.single_approval', $values['school_verification']['single_approval']);
    $config->set('school_verification.single_approval_role', $values['school_verification']['single_approval_role']);
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
