<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Feature Cards block.
 *
 * @Block(
 *   id = "rte_mis_home_feature_cards_block",
 *   admin_label = @Translation("Feature Cards Block"),
 *   category = @Translation("Custom"),
 * )
 */
final class FeatureCardsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
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
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'cards' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $global_cards = $this->configFactory->get('rte_mis_home.settings')->get('feature_cards') ?: [];
    $cards = !empty($this->configuration['cards']) ? $this->configuration['cards'] : $global_cards;

    $icon_options = $this->getIconOptions();

    $form['cards'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Feature Cards'),
      '#tree' => TRUE,
    ];

    for ($i = 0; $i < 6; $i++) {
      $card = $cards[$i] ?? [];
      $form['cards'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('Card @num', ['@num' => $i + 1]),
        '#open' => $i < 3,
      ];
      $form['cards'][$i]['icon'] = [
        '#type' => 'select',
        '#title' => $this->t('Icon'),
        '#options' => $icon_options,
        '#default_value' => $card['icon'] ?? 'info',
        '#empty_option' => $this->t('- None -'),
      ];
      $form['cards'][$i]['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title'),
        '#default_value' => $card['title'] ?? '',
      ];
      $form['cards'][$i]['description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Description'),
        '#rows' => 2,
        '#default_value' => $card['description'] ?? '',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $raw = $form_state->getValue('cards') ?: [];
    // Strip empty cards.
    $this->configuration['cards'] = array_values(array_filter($raw, fn($c) => !empty($c['title'])));
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $global_cards = $this->configFactory->get('rte_mis_home.settings')->get('feature_cards') ?: [];
    $cards = !empty($this->configuration['cards']) ? $this->configuration['cards'] : $global_cards;

    return [
      '#theme' => 'feature_cards_block',
      '#cards' => $cards,
      '#cache' => [
        'tags' => ['config:rte_mis_home.settings'],
      ],
    ];
  }

  /**
   * Returns the available icon options.
   */
  private function getIconOptions(): array {
    return [
      'education'   => $this->t('Education (book)'),
      'reservation' => $this->t('Reservation (people)'),
      'location'    => $this->t('Location (pin)'),
      'calendar'    => $this->t('Calendar'),
      'document'    => $this->t('Document'),
      'info'        => $this->t('Info'),
    ];
  }

}
