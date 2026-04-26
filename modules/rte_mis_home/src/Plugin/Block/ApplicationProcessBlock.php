<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'Application Process' block.
 *
 * @Block(
 *   id = "rte_mis_home_application_process",
 *   admin_label = @Translation("Application Process Block"),
 *   category = @Translation("RTE MIS")
 * )
 */
class ApplicationProcessBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new ApplicationProcessBlock instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $config = $this->configFactory->get('rte_mis_home.application_process_settings');

    $steps = $config->get('steps') ?: [
      [
        'icon' => 'register',
        'title' => $this->t('Register'),
        'description' => $this->t('Create account with mobile number'),
      ],
      [
        'icon' => 'document',
        'title' => $this->t('Fill Details'),
        'description' => $this->t('Enter student information'),
      ],
      [
        'icon' => 'upload',
        'title' => $this->t('Upload Documents'),
        'description' => $this->t('Submit required documents'),
      ],
      [
        'icon' => 'school',
        'title' => $this->t('Select Schools'),
        'description' => $this->t('Choose up to 5 schools'),
      ],
      [
        'icon' => 'submit',
        'title' => $this->t('Submit'),
        'description' => $this->t('Review and submit application'),
      ],
      [
        'icon' => 'track',
        'title' => $this->t('Track Status'),
        'description' => $this->t('Monitor application progress'),
      ],
    ];

    $guidelines_items_config = $config->get('guidelines_items') ?: [];
    $guidelines_items = [];
    if (empty($guidelines_items_config)) {
      $guidelines_items = [
        $this->t('Keep documents in PDF/JPG format (max 2MB each)'),
        $this->t('Applications can be saved as draft and completed later'),
        $this->t('SMS and email notifications at each stage'),
        $this->t('Transparent lottery system for fair seat allocation'),
      ];
    } else {
      foreach ($guidelines_items_config as $item) {
        if (!empty($item['text'])) {
          $guidelines_items[] = clone $this->t($item['text']); // Convert configured text logically 
        }
      }
    }

    return [
      '#type' => 'component',
      '#component' => 'rte_mis_theme:application-process',
      '#props' => [
        'title' => $config->get('title') ?: $this->t('Application Process'),
        'subtitle' => $config->get('subtitle') ?: $this->t('Complete your RTE admission in 6 simple steps'),
        'steps' => $steps,
        'guidelines' => [
          'title' => $config->get('guidelines_title') ?: $this->t('Important Guidelines'),
          'items' => $guidelines_items,
          'button' => [
            'text' => $config->get('button_text') ?: $this->t('Apply Now'),
            'url' => $config->get('button_link') ?: '#',
          ],
        ],
      ],
      '#cache' => [
        'tags' => $config->getCacheTags(),
      ],
    ];
  }
}
