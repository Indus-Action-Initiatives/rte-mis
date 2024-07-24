<?php

namespace Drupal\rte_mis_lottery\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * RTE MIS Lottery config form.
 */
class LotteryConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'rte_mis_lottery.settings';

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
    return 'rte_mis_lottery_settings';
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

    $form['time_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Time Interval'),
      '#description' => $this->t('Enter the time interval in hours after which the lottery status table will be cleared.'),
      '#default_value' => $config->get('time_interval'),
      '#min' => 24,
      '#required' => TRUE,
    ];

    $form['notify_student'] = [
      '#type' => 'details',
      '#title' => $this->t('SMS Settings'),
      '#open' => TRUE,
    ];

    $form['notify_student']['enable_sms'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Notify student about the result.'),
      '#description' => $this->t('This option will allow admin to send sms to student about lottery results.'),
      '#default_value' => $config->get('notify_student.enable_sms'),
    ];

    $form['notify_student']['alloted_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('School Alloted Message'),
      '#default_value' => $config->get('notify_student.alloted_message'),
      '#description' => $this->t('Available token for replacement: <strong>!application_number</strong> and <strong>!udise_code</strong>.'),
      '#states' => [
        'visible' => [
          ':input[name="enable_sms"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="enable_sms"]' => ['checked' => TRUE],
        ],
      ],
      '#attributes' => [
        'data-maxlength' => 200,
        'class' => [
          'maxlength',
        ],
        'maxlength_js_label' => [
          $this->t('Content limit is up to @limit characters, remaining: <strong>@remaining</strong>'),
        ],
        '#maxlength_js_enforce' => TRUE,
      ],
    ];
    $form['notify_student']['un_alloted_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('School Un-alloted Message'),
      '#description' => $this->t('Available token for replacement: <strong>!application_number</strong>.'),
      '#default_value' => $config->get('notify_student.un_alloted_message'),
      '#states' => [
        'visible' => [
          ':input[name="enable_sms"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="enable_sms"]' => ['checked' => TRUE],
        ],
      ],
      '#attributes' => [
        'data-maxlength' => 200,
        'class' => [
          'maxlength',
        ],
        'maxlength_js_label' => [
          $this->t('Content limit is up to @limit characters, remaining: <strong>@remaining</strong>'),
        ],
        '#maxlength_js_enforce' => TRUE,
      ],
    ];

    $form['#attached']['library'][] = 'maxlength/maxlength';
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $value = (int) $form_state->getValue('time_interval');

    // The value should be a whole number.
    if (!is_int($value)) {
      $form_state->setErrorByName('time_interval', $this->t('The time interval must be a whole number (integer).'));
    }
    // The minimum value should be 24 hours.
    elseif ($value < 24) {
      $form_state->setErrorByName('time_interval', $this->t('The time interval should be at least 24 hours.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->config('rte_mis_lottery.settings')
      ->set('time_interval', $values['time_interval'])
      ->set('notify_student.enable_sms', $values['enable_sms'])
      ->set('notify_student.alloted_message', $values['alloted_message'])
      ->set('notify_student.un_alloted_message', $values['un_alloted_message'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
