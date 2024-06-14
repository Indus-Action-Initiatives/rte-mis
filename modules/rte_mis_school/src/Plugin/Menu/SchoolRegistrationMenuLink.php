<?php

namespace Drupal\rte_mis_school\Plugin\Menu;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eck\EckEntityInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create sub-menu for school registration.
 */
class SchoolRegistrationMenuLink extends MenuLinkDefault {

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
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StaticMenuLinkOverridesInterface $static_override, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $static_override);
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu_link.static.overrides'),
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    $title = (string) $this->pluginDefinition['title'];
    if ($this->getRouteName() == 'rte_mis_school.school_registration.edit') {
      $parameter = $this->getRouteParameters();
      $eck_node = $parameter['mini_node'] ?? 0;
      $school = $this->entityTypeManager->getStorage('mini_node')->load($eck_node);
      if ($school instanceof EckEntityInterface && $school->field_school_verification->getString() != 'school_registration_verification_pending') {
        $title = $this->t('Edit Registration');
      }
    }

    return $title;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters() {
    $route_parameters = parent::getRouteParameters();
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    if ($user instanceof UserInterface) {
      $route_parameters['user'] = 0;
      $route_parameters['mini_node'] = 0;
      $eck_node = $user->get('field_school_details')->getString() ?? 0;
      if (!empty($eck_node) && is_numeric($eck_node)) {
        $route_parameters['mini_node'] = $eck_node;
        $route_parameters['user'] = $this->currentUser->id();
      }
      if ($this->getRouteName() == 'entity_print.view') {
        $route_parameters['entity_id'] = 0;
        $school = $this->entityTypeManager->getStorage('mini_node')->load($eck_node);
        if ($school instanceof EckEntityInterface) {
          if ($school->field_school_verification->getString() != 'school_registration_verification_pending') {
            $route_parameters['entity_id'] = !empty($eck_node) ? $eck_node : 0;
          }
        }
      }
    }
    return $route_parameters;
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
        return Cache::mergeTags($eckCacheTags, $user->getCacheTags(), ['menu_link_content_list']);
      }
      return Cache::mergeTags($this->pluginDefinition['cache_tags'], $user->getCacheTags(), $eckCacheTags);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['user' , 'url.path'];
  }

}
