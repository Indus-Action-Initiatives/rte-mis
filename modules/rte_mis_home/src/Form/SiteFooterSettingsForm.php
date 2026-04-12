<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Site Footer global defaults for rte_mis_home.
 */
final class SiteFooterSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'rte_mis_home_site_footer_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['rte_mis_home.site_footer_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('rte_mis_home.site_footer_settings');

    $form['brand_subtitle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Brand Subtitle'),
      '#default_value' => $config->get('brand_subtitle') ?: 'Right to Education Management Information System',
    ];

    $form['contact'] = [
      '#type' => 'details',
      '#title' => $this->t('Contact Info'),
      '#open' => TRUE,
    ];
    $form['contact']['helpline'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Helpline'),
      '#default_value' => $config->get('helpline') ?: '1800-XXX-XXXX',
    ];
    $form['contact']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => $config->get('email') ?: 'help@rte.gov.in',
    ];
    $form['contact']['hours'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Working Hours'),
      '#default_value' => $config->get('hours') ?: 'Mon-Sat, 9 AM - 6 PM',
    ];

    $form['admin'] = [
      '#type' => 'details',
      '#title' => $this->t('System Info'),
      '#open' => TRUE,
    ];
    $form['admin']['admin_login_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Admin Login URL'),
      '#default_value' => $config->get('admin_login_url') ?: '#',
    ];
    $form['admin']['last_updated'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Updated Date'),
      '#default_value' => $config->get('last_updated') ?: date('F j, Y'),
    ];

    // Menu selections
    $menus = \Drupal\system\Entity\Menu::loadMultiple();
    $menu_options = ['' => $this->t('- None -')];
    foreach ($menus as $id => $menu) {
      $menu_options[$id] = $menu->label();
    }

    $form['menus'] = [
      '#type' => 'details',
      '#title' => $this->t('Menu Bindings'),
      '#open' => TRUE,
    ];
    
    $form['menus']['quick_links_menu'] = [
      '#type' => 'select',
      '#title' => $this->t('Quick Links Menu'),
      '#options' => $menu_options,
      '#default_value' => $config->get('quick_links_menu') ?: '',
    ];
    $form['menus']['resources_menu'] = [
      '#type' => 'select',
      '#title' => $this->t('Resources Menu'),
      '#options' => $menu_options,
      '#default_value' => $config->get('resources_menu') ?: '',
    ];
    $form['menus']['gov_links_menu'] = [
      '#type' => 'select',
      '#title' => $this->t('Government Links Menu'),
      '#options' => $menu_options,
      '#default_value' => $config->get('gov_links_menu') ?: '',
    ];
    $form['menus']['footer_links_menu'] = [
      '#type' => 'select',
      '#title' => $this->t('Footer Links Menu'),
      '#options' => $menu_options,
      '#default_value' => $config->get('footer_links_menu') ?: '',
    ];
    $form['menus']['social_links_menu'] = [
      '#type' => 'select',
      '#title' => $this->t('Social Links Menu'),
      '#options' => $menu_options,
      '#default_value' => $config->get('social_links_menu') ?: '',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('rte_mis_home.site_footer_settings');
    
    $config->set('brand_subtitle', $form_state->getValue('brand_subtitle'))
      ->set('helpline', $form_state->getValue('helpline'))
      ->set('email', $form_state->getValue('email'))
      ->set('hours', $form_state->getValue('hours'))
      ->set('admin_login_url', $form_state->getValue('admin_login_url'))
      ->set('last_updated', $form_state->getValue('last_updated'))
      ->set('quick_links_menu', $form_state->getValue('quick_links_menu'))
      ->set('resources_menu', $form_state->getValue('resources_menu'))
      ->set('gov_links_menu', $form_state->getValue('gov_links_menu'))
      ->set('footer_links_menu', $form_state->getValue('footer_links_menu'))
      ->set('social_links_menu', $form_state->getValue('social_links_menu'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
