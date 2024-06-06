<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a statistics block.
 *
 * @Block(
 *   id = "rte_mis_home_statistics_block",
 *   admin_label = @Translation("Statistics Block"),
 *   category = @Translation("Custom"),
 * )
 */
final class StatisticsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $statistics = [
      [
        'icon' => 'school',
        'total_count' => '10',
        'label' => $this->t('Total school'),
      ],
      [
        'icon' => 'student',
        'total_count' => '5000',
        'label' => $this->t('Total student'),
      ],
      [
        'icon' => 'seats',
        'total_count' => '300',
        'label' => $this->t('Total seat'),
      ],
      [
        'icon' => 'district',
        'total_count' => '20',
        'label' => $this->t('Total district'),
      ],
      [
        'icon' => 'reimbursement',
        'total_count' => '1000',
        'label' => $this->t('Total reimbursement'),
      ],
    ];

    return [
      '#theme' => 'statistics_block',
      '#statistics' => $statistics,
      '#attached' => [
        'library' => [
          'rte_mis_gin/rte_mis_statistics_block',
        ],
      ],
    ];
  }

}
