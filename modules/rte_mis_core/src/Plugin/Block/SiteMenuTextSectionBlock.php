<?php

namespace Drupal\rte_mis_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\token\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Site Menu Text Section' block with image and text fields.
 *
 * @Block(
 *   id = "site__menu_text_section_block",
 *   admin_label = @Translation("Site Menu Text Header & Footer Block"),
 * )
 */
class SiteMenuTextSectionBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The token service.
   *
   * @var \Drupal\token\TokenInterface
   */
  protected $token;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Token $token) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->token = $token;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('token'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $build = [];

    $text = isset($config['block_text']) && $config['block_text'] !== '' ? $config['block_text'] : $this->t('Copyright © [date:custom:Y]. Department of School and Mass Education, Government of India. All Rights Reserved.');
    // Perform token replacement for [date:custom:Y].
    $text = $this->token->replace($text);

    $build['sub_heading']['#markup'] = '<p>' . $text . '</p>';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['block_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Block Text'),
      '#token_types' => ['date'],
      '#default_value' => isset($config['block_text']) && $config['block_text'] !== '' ? $config['block_text'] : $this->t('Copyright © [date:custom:Y]. Department of School and Mass Education, Government of India. All Rights Reserved.'),
      '#description' => $this->t('Use [date:custom:Y] to set the Current Year.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->configuration['block_text'] = $values['block_text'];
  }

}
