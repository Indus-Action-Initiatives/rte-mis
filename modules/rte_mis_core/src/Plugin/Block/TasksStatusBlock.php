<?php

declare(strict_types=1);

namespace Drupal\rte_mis_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\rte_mis_core\Helper\RteCoreHelper;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a tasks status block.
 *
 * @Block(
 *   id = "rte_mis_core_tasks_status",
 *   admin_label = @Translation("Tasks Status"),
 *   category = @Translation("Custom"),
 * )
 */
final class TasksStatusBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Core helper.
   *
   * @var \Drupal\rte_mis_Core\Helper\RteCoreHelper
   */
  protected $rteCoreHelper;

  /**
   * Constructs the plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current logged in user.
   * @param \Drupal\rte_mis_core\Helper\RteCoreHelper $rte_core_helper
   *   The rte core helper.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    RteCoreHelper $rte_core_helper,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $currentUser;
    $this->rteCoreHelper = $rte_core_helper;
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
      $container->get('rte_mis_core.core_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    // Check the roles of the current user.
    $roles = $this->currentUser->getRoles();
    // Define the routes for the links.
    $tasks = [];
    // For school udise code approval.
    if (in_array('district_admin', $roles)) {
      $view = Views::getView('school');
      if ($view) {
        $view->setDisplay('page_2');
        $view->preExecute();
        $view->execute();
        // Check if the view has any results.
        if (!empty($view->result)) {
          $tasks['School Approval'] = 'view.school.page_2';
        }
      }
    }

    if (array_intersect(['district_admin', 'block_admin'], $roles)) {
      if ($this->rteCoreHelper->isAcademicSessionValid('school_verification')) {
        // Load the view programmatically.
        $view = Views::getView('school_registration');
        if ($view) {
          $view->setDisplay('page_1');
          $view->preExecute();
          $view->execute();
          $block_approval_count = 0;
          $district_approval_count = 0;
          foreach ($view->result as $value) {
            $currentStatus = $value->_entity->get('field_school_verification')->getString() ?? NULL;
            if ($currentStatus == 'school_registration_verification_submitted') {
              $block_approval_count++;
            }
            elseif ($currentStatus == 'school_registration_verification_approved_by_beo') {
              $district_approval_count++;
            }

          }
          if (in_array('block_admin', $roles)) {
            // Check if the view has any result for block admin.
            if ($block_approval_count > 0) {
              $tasks['School Registration Approval'] = 'view.school_registration.page_1';
            }
          }
          else {
            // Check if the view has any results for district admin.
            if ($district_approval_count > 0) {
              $tasks['School Registration Approval'] = 'view.school_registration.page_1';
            }
          }
        }
      }
      if ($this->rteCoreHelper->isAcademicSessionValid('school_mapping')) {
        $tasks['Mapping'] = 'rte_mis_school.form.school_mapping';
      }
    }
    if (in_array('block_admin', $roles)) {
      if ($this->rteCoreHelper->isAcademicSessionValid('student_verification')) {
        // Load the view programmatically.
        $view = Views::getView('student_registration');
        if ($view) {
          $view->setDisplay('page_1');
          $view->preExecute();
          $view->execute();

          $block_student_approval_count = 0;
          foreach ($view->result as $value) {
            $currentStatus = $value->_entity->get('field_student_verification')->getString() ?? NULL;
            $states = [
              'student_workflow_submitted',
              'student_workflow_rejected',
              'student_workflow_incomplete',
              'student_workflow_duplicate',
            ];
            if (!empty($currentStatus) && in_array($currentStatus, $states)) {
              $block_student_approval_count++;
            }
          }
          // Check if the view has any result for block admin.
          if ($block_student_approval_count > 0) {
            $tasks['Student Approval'] = 'view.student_registration.page_1';
          }
        }
      }
    }
    elseif (in_array('state_admin', $roles)) {
      if ($this->rteCoreHelper->isAcademicSessionValid('school_mapping')) {
        $tasks['Mapping Review'] = 'rte_mis_school.form.school_mapping';
      }
    }

    // Generate the links.
    $content = [];
    if ($tasks) {
      foreach ($tasks as $task_name => $route_name) {
        $url = Url::fromRoute($route_name);
        $link = Link::fromTextAndUrl($task_name, $url)->toString();
        $content[] = [
          '#markup' => $link,
        ];
      }
    }

    if (empty($content)) {
      $content[] = [
        '#markup' => $this->t('No Tasks'),
      ];
    }

    // Attach the custom library to the block.
    $build = [
      '#theme' => 'tasks_status_block',
      '#attached' => [
        'library' => [
          'rte_mis_core/rte_mis_tasks_status_block',
        ],
      ],
      '#content' => $content,
      '#cache' => [
        'contexts' => ['user.roles'],
        'tags' => [
          'mini_node_list',
        ],
      ],
    ];

    return $build;
  }

}
