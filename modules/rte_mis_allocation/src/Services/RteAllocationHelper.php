<?php

namespace Drupal\rte_mis_allocation\Services;

use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eck\EckEntityInterface;

/**
 * Class RteAllocationHelper.
 *
 * Provides helper functions for rte mis allocation module.
 */
class RteAllocationHelper {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * Constructs a RteAllocationHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFormBuilderInterface $entity_form_builder) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
  }

  /**
   * Builds render array for allocation details section.
   *
   * @param \Drupal\eck\EckEntityInterface $student_allocation
   *   The student allocation mini node object.
   * @param string $view_mode
   *   View mode of the mini node.
   *
   * @return array
   *   The render array for allocation details section.
   */
  public function buildAllocationDetailsSection(EckEntityInterface $student_allocation, string $view_mode = 'full'): array {
    $build = [];
    $build['student_allocation_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Allocation Details'),
      '#open' => TRUE,
      '#attributes' => [
        'class' => ['student-allocation-section'],
      ],
      '#weight' => 100,
    ];
    $view_builder = $this->entityTypeManager->getViewBuilder('mini_node');
    $build['student_allocation_container']['allocation_view'] = $view_builder->view($student_allocation, 'allocation_details');
    // Get the current state.
    $current_sid = workflow_node_current_state($student_allocation, 'field_student_allocation_status');
    // If view mode is 'allocation_details' build allocation details section
    // based on the current allocation status.
    if ($view_mode == 'allocation_details' && !in_array($current_sid, [
      'student_admission_workflow_dropout',
      'student_admission_workflow_not_admitted',
    ])) {
      $build['student_allocation_container']['field_student_allocation_form'] = $this->entityFormBuilder->getForm($student_allocation);
      $build['student_allocation_container']['field_student_allocation_form']['#title'] = $this->t('Student allocation');
    }
    else {
      $build['student_allocation_container']['field_student_allocation_form'] = $student_allocation->get('field_student_allocation_status')->view([
        'type' => 'list_default',
      ]);
      $build['student_allocation_container']['field_student_allocation_form']['#title'] = $this->t('Current Student allocation Status');
    }

    return $build;
  }

}
