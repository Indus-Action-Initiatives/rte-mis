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
    // Load the configuration.
    $config = $this->configFactory->get('rte_mis_student.settings');
    $studentRegistrationSessionStatus = $this->rteCoreHelper->isAcademicSessionValid('student_application');
    // Get student age criteria from the configuration.
    $age_criteria = $config->get('student_age_criteria');
    // Map the age criteria to class names.
    $class_ages = [
      'Nursery' => isset($age_criteria[0]) ? sprintf('%d to %d years', $age_criteria[0]['min_age'], $age_criteria[0]['max_age']) : 'N/A',
      'KG 1' => isset($age_criteria[1]) ? sprintf('%d to %d years', $age_criteria[1]['min_age'], $age_criteria[1]['max_age']) : 'N/A',
      'Class' => isset($age_criteria[3]) ? sprintf('%d to %.1f years', $age_criteria[3]['min_age'], $age_criteria[3]['max_age']) : 'N/A',
    ];
    // Define the categories with placeholders.
    $categories = [
      'Disadvantaged Group' => 'N/A',
      'Economically Weaker Section' => 'N/A',
      'Others' => 'N/A',
    ];
    // Define the others with placeholders.
    $others = [
      'Distance' => 'N/A',
      'High Court Distance' => 'N/A',
      'Notifications' => 'N/A',
    ];
    // Build the output array to pass to the template.
    return [
      '#theme' => 'eligibility_criteria',
      '#class_ages' => $class_ages,
      '#categories' => $categories,
      '#others' => $others,
      '#student_registration_status' => $studentRegistrationSessionStatus,
      '#attached' => [
        'library' => [
          'rte_mis_gin/rte_mis_eligibility_criteria',
        ],
      ],
    ];
  }

}
