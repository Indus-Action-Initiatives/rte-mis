<?php

namespace Drupal\rte_mis_school\Plugin\Menu;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eck\EckEntityInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides school registration local task for school_admin.
 */
class SchoolRegistrationLocalTask extends LocalTaskDefault implements ContainerFactoryPluginInterface {

  /**
   * A request stack symfony instance.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Language manager for retrieving the default langcode.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Construct the UserTrackerTab object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Data of the user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, Request $request, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->requestStack = $request;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    if ($user instanceof UserInterface) {
      // Get the school_details mini_node from `field_school_details` field.
      $eckEntity = $user->get('field_school_details')->entity ?? NULL;
      $eckCacheTags = [];
      if ($eckEntity instanceof EckEntityInterface) {
        // Get the cache tag of school_details mini_node.
        $eckCacheTags = $eckEntity->getCacheTags();
      }
      // Add the tag to menu local task.
      if (!isset($this->pluginDefinition['cache_tags'])) {
        return Cache::mergeTags($eckCacheTags, $user->getCacheTags());
      }
      return Cache::mergeTags($this->pluginDefinition['cache_tags'], $user->getCacheTags(), $eckCacheTags);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    if ($user instanceof UserInterface) {
      if (!isset($this->pluginDefinition['cache_contexts'])) {
        return $user->getCacheContexts();
      }
      return Cache::mergeContexts($this->pluginDefinition['cache_contexts'], $user->getCacheContexts());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters(RouteMatchInterface $route_match) {
    $route_parameters = parent::getRouteParameters($route_match);
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    if ($user instanceof UserInterface) {
      $route_parameters['mini_node'] = '';
      $eck_node = $user->get('field_school_details')->getString() ?? '';
      if (!empty($eck_node) && is_numeric($eck_node)) {
        $route_parameters['mini_node'] = $eck_node;
      }
    }
    return $route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(RouteMatchInterface $route_match) {
    $options = parent::getOptions($route_match);
    $lang_code = $this->languageManager->getCurrentLanguage()->getId();
    if ($lang_code != 'en') {
      $options['query']['destination'] = "$lang_code{$options['query']['destination']}";
    }
    return (array) $options;
  }

}
