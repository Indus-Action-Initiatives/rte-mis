<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the Contact Us standalone page.
 */
class ContactPageController extends ControllerBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a ContactPageController object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
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
   * Builds the contact page content.
   */
  public function build(): array {
    $config = $this->configFactory->get('rte_mis_home.contact_page_settings');

    $page_title = $config->get('page_title') ?: 'Contact Us';
    $cards_title = $config->get('cards_title') ?: 'Office Contact Details';
    $contact_cards = $config->get('contact_cards') ?: [];
    
    $table_title = $config->get('table_title') ?: '';
    $table_headers = $config->get('table_headers') ?: ['Name', 'Designation', 'Section/Desk', 'Address', 'Phone/email'];
    $table_rows = $config->get('table_rows') ?: [];

    return [
      '#type' => 'component',
      '#component' => 'rte_mis_theme:contact-page',
      '#props' => [
        'page_title' => $page_title,
        'table_title' => $table_title,
        'table_headers' => $table_headers,
        'table_rows' => $table_rows,
        'cards_title' => $cards_title,
        'contact_cards' => $contact_cards,
      ],
      '#cache' => [
        'tags' => ['config:rte_mis_home.contact_page_settings'],
      ],
    ];
  }

}
