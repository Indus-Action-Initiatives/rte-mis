<?php

namespace Drupal\rte_mis_school\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\eck\EckEntityInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'School Habitation' block.
 *
 * @Block(
 *   id = "school_habitation_block",
 *   admin_label = @Translation("School Habitation Block")
 * )
 */
class SchoolHabitationBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $values = [];
    // Check if the user has the role 'school_admin'.
    $currentUserRoles = $this->currentUser->getRoles(TRUE);
    if (in_array('school_admin', $currentUserRoles)) {
      // Load the current user entity.
      $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id()) ?? NULL;

      if ($user instanceof UserInterface) {
        // Get the field_school_details entity reference field.
        if ($user->hasField('field_school_details') && !$user->get('field_school_details')->isEmpty()) {
          // Load the referenced school entity.
          $school_entity = $user->get('field_school_details')->entity ?? NULL;

          if ($school_entity instanceof EckEntityInterface) {
            if ($school_entity->hasField('field_habitations') && !$school_entity->get('field_habitations')->isEmpty()) {
              foreach ($school_entity->get('field_habitations')->referencedEntities() as $referenced_entity) {
                $values[] = $referenced_entity->label();
              }
            }
          }

        }
      }
    }

    return [
      // '#markup' => $output,
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $values,
      '#empty' => $this->t('No Habitation found.'),
    ];
  }

}
