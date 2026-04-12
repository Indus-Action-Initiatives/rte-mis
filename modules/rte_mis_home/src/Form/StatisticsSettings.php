<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure rte_mis_home statistics settings.
 */
final class StatisticsSettings extends ConfigFormBase
{
  /**
   * Constructs a StatisticsSettings object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'rte_mis_home_statistics_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array
  {
    return ['rte_mis_home.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $config = $this->config('rte_mis_home.settings');
    $form_state->setCached(FALSE);

    $default_statistics = [
      ['icon' => 'school', 'total_count' => '10', 'label' => 'Total Schools'],
      ['icon' => 'student', 'total_count' => '5000', 'label' => 'Total Students'],
      ['icon' => 'seats', 'total_count' => '300', 'label' => 'Total Seats'],
      ['icon' => 'district', 'total_count' => '20', 'label' => 'Total Districts'],
      ['icon' => 'reimbursement', 'total_count' => '1000', 'label' => 'Total Reimbursement'],
    ];

    $existing_statistics = $config->get('statistics') ?: $default_statistics;

    $num_stats = $form_state->get('num_stats');
    if ($num_stats === NULL) {
      $num_stats = max(1, count($existing_statistics));
      $form_state->set('num_stats', $num_stats);
    }

    $form['statistics_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Statistics Metrics'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#prefix' => '<div id="statistics-wrapper">',
      '#suffix' => '</div>',
    ];

    for ($i = 0; $i < $num_stats; $i++) {
      $form['statistics_wrapper'][$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Statistic @num', ['@num' => $i + 1]),
      ];
      $form['statistics_wrapper'][$i]['icon'] = [
        '#type' => 'select',
        '#title' => $this->t('Icon'),
        '#options' => [
          'school' => $this->t('School'),
          'student' => $this->t('Student'),
          'seats' => $this->t('Seats'),
          'district' => $this->t('District'),
          'reimbursement' => $this->t('Reimbursement'),
        ],
        '#empty_option' => $this->t('- Select -'),
        '#default_value' => $existing_statistics[$i]['icon'] ?? '',
        '#required' => FALSE,
      ];
      $form['statistics_wrapper'][$i]['total_count'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Total Count'),
        '#default_value' => $existing_statistics[$i]['total_count'] ?? '',
      ];
      $form['statistics_wrapper'][$i]['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#default_value' => $existing_statistics[$i]['label'] ?? '',
      ];
    }

    $form['statistics_wrapper']['add_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another statistic'),
      '#submit' => ['::addStatisticSubmit'],
      '#ajax' => [
        'callback' => '::addStatisticAjax',
        'wrapper' => 'statistics-wrapper',
      ],
      '#button_type' => 'secondary',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    $config = $this->configFactory->getEditable('rte_mis_home.settings');
    $values = $form_state->getValue('statistics_wrapper');

    $statistics = [];
    if (!empty($values)) {
      foreach ($values as $key => $stat) {
        if (is_numeric($key) && !empty($stat['icon']) && !empty($stat['total_count']) && !empty($stat['label'])) {
          $statistics[] = [
            'icon' => $stat['icon'],
            'total_count' => $stat['total_count'],
            'label' => $stat['label'],
          ];
        }
      }
    }

    $config->set('statistics', $statistics);
    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Submit handler for the "Add another statistic" button.
   */
  public function addStatisticSubmit(array &$form, FormStateInterface $form_state): void {
    $num_stats = $form_state->get('num_stats') + 1;
    $form_state->set('num_stats', $num_stats);
    $form_state->setRebuild();
  }

  /**
   * Ajax callback for the "Add another statistic" button.
   */
  public function addStatisticAjax(array &$form, FormStateInterface $form_state): array {
    return $form['statistics_wrapper'];
  }
}
