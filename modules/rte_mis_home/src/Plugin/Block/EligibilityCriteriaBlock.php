<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\rte_mis_core\Helper\RteCoreHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an eligibility criteria block.
 *
 * @Block(
 *   id = "rte_mis_home_eligibility_criteria",
 *   admin_label = @Translation("Eligibility Criteria"),
 *   category = @Translation("Custom"),
 * )
 */
final class EligibilityCriteriaBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Core helper.
   *
   * @var \Drupal\rte_mis_Core\Helper\RteCoreHelper
   */
  protected $rteCoreHelper;

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    RteCoreHelper $rte_core_helper,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->rteCoreHelper = $rte_core_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('rte_mis_core.core_helper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    // Load student settings for age criteria.
    $student_config = $this->configFactory->get('rte_mis_student.settings');
    // Load home settings for eligibility items and visibility.
    $home_config = $this->configFactory->get('rte_mis_home.settings');

    $studentRegistrationSessionStatus = $this->rteCoreHelper->isAcademicSessionValid('student_application');
    
    // Get student age criteria.
    $age_criteria = $student_config->get('student_age_criteria');
    $class_ages = [];
    if ($age_criteria) {
      $class_ages = [
        [
          'label' => $this->t('Nursery'),
          'value' => isset($age_criteria[0]) ? $this->t('@min_age to @max_age years', [
            '@min_age' => $age_criteria[0]['min_age'],
            '@max_age' => $age_criteria[0]['max_age'],
          ]) : $this->t('N/A'),
        ],
        [
          'label' => $this->t('KG 1'),
          'value' => isset($age_criteria[1]) ? $this->t('@min_age to @max_age years', [
            '@min_age' => $age_criteria[1]['min_age'],
            '@max_age' => $age_criteria[1]['max_age'],
          ]) : $this->t('N/A'),
        ],
        [
          'label' => $this->t('Class 1'),
          'value' => isset($age_criteria[3]) ? $this->t('@min_age to @max_age years', [
            '@min_age' => $age_criteria[3]['min_age'],
            '@max_age' => $age_criteria[3]['max_age'],
          ]) : $this->t('N/A'),
        ],
      ];
    }

    // Define the categories with placeholders (backward compatibility if needed).
    $categories = [
      ['label' => $this->t('Disadvantaged Group'), 'value' => $this->t('N/A')],
      ['label' => $this->t('Economically Weaker Section'), 'value' => $this->t('N/A')],
      ['label' => $this->t('Others'), 'value' => $this->t('N/A')],
    ];

    // Define the others with placeholders.
    $others = [
      ['label' => $this->t('Distance'), 'value' => $this->t('3km')],
      ['label' => $this->t('High Court Distance'), 'value' => $this->t('N/A')],
      ['label' => $this->t('Notifications'), 'value' => $this->t('N/A')],
    ];

    // Build the output array to pass to the template.
    return [
      '#theme' => 'eligibility_criteria',
      '#class_ages' => $class_ages,
      '#categories' => $categories,
      '#others' => $others,
      '#student_registration_status' => $studentRegistrationSessionStatus,
      '#show_class_age' => $home_config->get('show_class_age') ?? TRUE,
      '#eligibility' => $home_config->get('eligibility_items') ?: [
        $this->t('Weaker section: Family Annual income less than 800,000 per annum'),
        $this->t('Scheduled Caste, Backward Class/Other Backward Class (non-creamy layer)'),
        $this->t('War widows\' children and Destitute parents\' children ( minimum 50% disability)')
      ],
      '#priorities' => $home_config->get('priority_items') ?: [
        $this->t('1st Priority : Children residing within a 1 km radius of the school.'),
        $this->t('2nd Priority : Children residing within a radius of 3 km.'),
        $this->t('3rd Priority : In case of unfilled vacancies, children residing beyond 3 km but within 6 km radius.')
      ],
      '#attached' => [
        'library' => [
          'rte_mis_gin/rte_mis_eligibility_criteria',
        ],
      ],
      '#cache' => [
        'tags' => $home_config->getCacheTags(),
      ],
    ];
  }

}
