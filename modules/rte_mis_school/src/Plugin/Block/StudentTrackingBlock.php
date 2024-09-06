<?php

namespace Drupal\rte_mis_school\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'School Habitation' block.
 *
 * @Block(
 *   id = "school_tracking_block",
 *   admin_label = @Translation("School Tracking Block")
 * )
 */
class StudentTrackingBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->moduleHandler = $moduleHandler;
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
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (!$this->moduleHandler->moduleExists('rte_mis_student_tracking')) {
      return [
        '#markup' => $this->t('This requires rte-mis student tracking to be enabled.'),
      ];
    }
    // Check if the user has the role 'school_admin'.
    $current_user_roles = $this->currentUser->getRoles(TRUE);
    $values = [];
    if (in_array('school_admin', $current_user_roles)) {
      // Load the current user entity.
      $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id()) ?? NULL;

      if ($user instanceof UserInterface) {
        // Get the field_school_details entity reference field.
        if ($user->hasField('field_school_details') && !$user->get('field_school_details')->isEmpty()) {
          // Load the referenced school entity.
          $school_entity = $user->get('field_school_details')->getString();

          $allocations = $this->entityTypeManager->getStorage('mini_node')->loadByProperties(
            [
              'type' => 'student_performance',
              'field_school' => $school_entity,
              'field_academic_session_tracking' => _rte_mis_core_get_current_academic_year(),
              'field_student_tracking_status' => 'student_tracking_workflow_studying',
              'status' => 1,
            ]);

          foreach ($allocations as $value) {
            $values[] = $value->get('field_student')->entity->get('field_student_name')->getString();
          }
        }
      }
    }

    return [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $values,
      '#empty' => $this->t('Data will be available soon.'),
    ];
  }

}
