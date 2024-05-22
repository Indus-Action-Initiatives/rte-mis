<?php

namespace Drupal\rte_mis_state\Plugin\Menu;

use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a default implementation for local action plugins.
 */
class AlterPeopleActionTask extends LocalActionDefault {

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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteProviderInterface $route_provider, Request $request) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_provider);
    $this->request = $request;
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
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(Request $request = NULL) {
    // Subclasses may pull in the request or specific attributes as parameters.
    // The title from YAML file discovery may be a TranslatableMarkup object.
    $role = $request->query->get('role') ?? NULL;
    if (empty($role) || !isset($role) || $role == 'All') {
      $role = 'Add User';
    }
    elseif ($role == 'district_admin') {
      $role = 'Add District User';
    }
    elseif ($role == 'block_admin') {
      $role = 'Add Block User';
    }

    return $role;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(RouteMatchInterface $route_match) {
    $option = parent::getOptions($route_match);
    // $request = $this->request;
    $role = $this->request->query->get('role') ?? NULL;
    if ($role == 'district_admin') {
      $option['query'] = ['display' => 'default', 'role' => $role];
    }
    elseif ($role == 'block_admin') {
      $option['query'] = ['display' => 'default', 'role' => $role];
    }
    else {
      $option['query'] = ['display' => 'default'];
    }
    return $option;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    if (!isset($this->pluginDefinition['cache_contexts'])) {
      return ['url.query_args'];
    }
    return array_merge($this->pluginDefinition['cache_contexts'], ['url.query_args']);
  }

}
