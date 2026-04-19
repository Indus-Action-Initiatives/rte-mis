<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Utility\Html;

/**
 * Controller for the FAQ Page.
 */
class FaqPageController extends ControllerBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new FaqPageController.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Builds the FAQ page.
   *
   * @return array
   *   A render array.
   */
  public function build(): array {
    $config = $this->configFactory->get('rte_mis_home.faq_page_settings');
    $title = $config->get('title') ?: 'Frequently Asked Questions';
    $raw_categories = $config->get('categories') ?: [];

    $categories = [];
    foreach ($raw_categories as $cat) {
      if (empty($cat['title'])) {
        continue;
      }
      $cat_items = [];
      if (!empty($cat['items'])) {
        foreach ($cat['items'] as $item) {
          $cat_items[] = [
            'question' => $item['question'],
            'answer' => $item['answer'],
          ];
        }
      }
      
      $categories[] = [
        'title' => $cat['title'],
        'id' => Html::getId('faq-cat-' . $cat['title']),
        'items' => $cat_items,
      ];
    }

    return [
      '#type' => 'component',
      '#component' => 'rte_mis_theme:faq-page',
      '#props' => [
        'title' => $title,
        'categories' => $categories,
      ],
    ];
  }

}
