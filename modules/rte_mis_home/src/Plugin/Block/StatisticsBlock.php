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
        'icon' => 'https://static.vecteezy.com/system/resources/thumbnails/019/986/406/small/location-icon-gps-pointer-icon-map-locator-sign-pin-location-line-art-style-free-png.png',
        'total_count' => '10',
        'label' => $this->t('Total school'),
      ],
      [
        'icon' => 'https://static.vecteezy.com/system/resources/thumbnails/019/986/406/small/location-icon-gps-pointer-icon-map-locator-sign-pin-location-line-art-style-free-png.png',
        'total_count' => '5000',
        'label' => $this->t('Total student'),
      ],
      [
        'icon' => 'https://static.vecteezy.com/system/resources/thumbnails/019/986/406/small/location-icon-gps-pointer-icon-map-locator-sign-pin-location-line-art-style-free-png.png',
        'total_count' => '300',
        'label' => $this->t('Total seat'),
      ],
      [
        'icon' => 'https://static.vecteezy.com/system/resources/thumbnails/019/986/406/small/location-icon-gps-pointer-icon-map-locator-sign-pin-location-line-art-style-free-png.png',
        'total_count' => '20',
        'label' => $this->t('Total district'),
      ],
      [
        'icon' => 'https://static.vecteezy.com/system/resources/thumbnails/019/986/406/small/location-icon-gps-pointer-icon-map-locator-sign-pin-location-line-art-style-free-png.png',
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
