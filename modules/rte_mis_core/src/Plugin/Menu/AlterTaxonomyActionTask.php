<?php

namespace Drupal\rte_mis_core\Plugin\Menu;

use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Override the action class for taxonomy add term form.
 */
class AlterTaxonomyActionTask extends LocalActionDefault {

  use StringTranslationTrait;

  /**
   * The route provider to load routes by name.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * A request stack symfony instance.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a LocalActionDefault object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider to load routes by name.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteProviderInterface $route_provider, Request $request, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_provider);
    $this->request = $request;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('router.route_provider'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(?Request $request = NULL) {
    $title = parent::getTitle();
    $parameter = $this->getRouteParameters($this->routeMatch);
    if (($parameter['taxonomy_vocabulary'] ?? '') == 'location') {
      $title = $this->t('Add Location');
    }
    return $title;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    if (!isset($this->pluginDefinition['cache_contexts'])) {
      return ['url'];
    }
    return array_merge($this->pluginDefinition['cache_contexts'], ['url']);
  }

}
