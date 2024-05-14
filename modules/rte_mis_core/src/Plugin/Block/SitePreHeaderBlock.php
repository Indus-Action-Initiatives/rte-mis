<?php

namespace Drupal\rte_mis_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Site Pre Menu Section' block with image and text fields.
 *
 * @Block(
 *   id = "site_pre_menu_text_section_block",
 *   admin_label = @Translation("Site Pre Menu Text Header"),
 * )
 */
class SitePreHeaderBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();

    $block_text = isset($config['block_text']) && $config['block_text'] !== '' ? $config['block_text'] : $this->t('School Education Department RTE Portal Government of India');
    $block_email = isset($config['block_email']) && $config['block_email'] !== '' ? $config['block_email'] : 'rtemis@info.com';
    $block_phone = isset($config['block_phone']) && $config['block_phone'] !== '' ? $config['block_phone'] : '9000090000';

    return [
      '#theme' => 'block--site-pre-menu-text-section-block',
      '#values' => [
        'block_text' => $block_text,
        'block_email' => $block_email,
        'block_phone' => $block_phone,
      ],
    ];
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
      '#default_value' => isset($config['block_text']) && $config['block_text'] !== '' ? $config['block_text'] : $this->t('School Education Department RTE Portal Government of India'),
    ];

    $form['block_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => isset($config['block_email']) && $config['block_email'] !== '' ? $config['block_email'] : 'rtemis@info.com',
    ];

    $form['block_phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone Number'),
      '#default_value' => isset($config['block_phone']) && $config['block_phone'] !== '' ? $config['block_phone'] : '9000090000',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->configuration['block_text'] = $values['block_text'];
    $this->configuration['block_email'] = $values['block_email'];
    $this->configuration['block_phone'] = $values['block_phone'];
  }

}
