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
        'icon' => 'profiles/contrib/rte_mis/modules/rte_mis_home/assets/statistics/district.svg',
        'total_count' => '10',
        'label' => $this->t('Total school'),
      ],
      [
        'icon' => 'profiles/contrib/rte_mis/modules/rte_mis_home/assets/statistics/student.svg',
        'total_count' => '5000',
        'label' => $this->t('Total student'),
      ],
      [
        'icon' => 'profiles/contrib/rte_mis/modules/rte_mis_home/assets/statistics/seats.svg',
        'total_count' => '300',
        'label' => $this->t('Total seat'),
      ],
      [
        'icon' => 'profiles/contrib/rte_mis/modules/rte_mis_home/assets/statistics/district.svg',
        'total_count' => '20',
        'label' => $this->t('Total district'),
      ],
      [
        'icon' => 'profiles/contrib/rte_mis/modules/rte_mis_home/assets/statistics/reimbursement.svg',
        'total_count' => '1000',
        'label' => $this->t('Total reimbursement'),
      ],
    ];

    return [
      '#theme' => 'statistics_block',
      '#statistics' => $statistics,
      '#attached' => [
        'library' => [
          'rte_mis_gin/statistics_block',
        ],
      ],
    ];
  }

}
