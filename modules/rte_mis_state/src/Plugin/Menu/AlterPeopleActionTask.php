<?php

namespace Drupal\rte_mis_state\Plugin\Menu;

use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Modifies the action button to change parameters.
 */
class AlterPeopleActionTask extends LocalActionDefault {

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
   * The user account service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

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
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteProviderInterface $route_provider, Request $request, AccountInterface $account) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_provider);
    $this->request = $request;
    $this->currentUser = $account;
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
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(Request $request = NULL) {
    // Subclasses may pull in the request or specific attributes as parameters.
    // The title from YAML file discovery may be a TranslatableMarkup object.
    $role = $request->query->get('role') ?? NULL;
    $current_user_role = $this->currentUser->getRoles();
    // If current user role is state admin then check for both district & block.
    if (in_array('state_admin', $current_user_role)) {
      if (empty($role) || !isset($role) || !in_array($role, ['block_admin', 'district_admin'])) {
        $role = $this->t('Add User');
      }
      elseif ($role == 'district_admin') {
        $role = $this->t('Add District User');
      }
      elseif ($role == 'block_admin') {
        $role = $this->t('Add Block User');
      }
    }
    // For current user role district admin check for only block.
    elseif (in_array('district_admin', $current_user_role)) {
      if (empty($role) || !isset($role) || !in_array($role, ['block_admin'])) {
        $role = $this->t('Add User');
      }
      elseif ($role == 'block_admin') {
        $role = $this->t('Add Block User');
      }
    }
    // Default for other user roles.
    else {
      $role = $this->t('Add User');
    }
    return $role;

  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(RouteMatchInterface $route_match) {
    $option = parent::getOptions($route_match);
    $role = $this->request->query->get('role') ?? NULL;
    $current_user_role = $this->currentUser->getRoles();
    if (array_intersect(['state_admin', 'district_admin'], $current_user_role)) {
      if ($role == 'district_admin') {
        $option['query'] = ['display' => 'default', 'role' => $role];
      }
      elseif ($role == 'block_admin') {
        $option['query'] = ['display' => 'default', 'role' => $role];
      }
      else {
        $option['query'] = ['display' => 'default'];
      }
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
