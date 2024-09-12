<?php

namespace Drupal\rte_mis_student\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eck\EckEntityInterface;
use Drupal\rte_mis_lottery\Services\RteLotteryHelper;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Student Application Status field handler.
 *
 * @ViewsField("rte_mis_student_application_status")
 */
class StudentApplicationStatusField extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Rte Lottery service.
   *
   * @var \Drupal\rte_mis_lottery\Services\RteLotteryHelper
   */
  protected $rteLotteryHelper;

  /**
   * Constructs a new StudentApplicationStatusField object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\rte_mis_lottery\Services\RteLotteryHelper $rte_lottery_helper
   *   RTE lottery service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    RteLotteryHelper $rte_lottery_helper,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->rteLotteryHelper = $rte_lottery_helper;
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
      $container->get('rte_mis_lottery.lottery_helper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): string|MarkupInterface {
    // Current student details mini node.
    $student = $values->_entity;
    $value = '';

    // Return empty string if not a valid mini node.
    if (!$student instanceof EckEntityInterface) {
      return $value;
    }

    // Check if there are any allocation for the student.
    $student_allocation = $this->entityTypeManager->getStorage('mini_node')->loadByProperties([
      'type' => 'allocation',
      'field_academic_year_allocation' => _rte_mis_core_get_current_academic_year(),
      'field_student' => $student->id(),
      'status' => 1,
    ]);
    if (!empty($student_allocation)) {
      $student_allocation = reset($student_allocation);
      if ($student_allocation instanceof EckEntityInterface) {
        $value = $student_allocation->get('field_student_allocation_status')->view([
          'type' => 'list_default',
          'label' => 'hidden',
        ]);
        $value = $this->renderer->render($value);
        return $value;
      }
    }

    // Check if there is an entry for the student in lottery results table.
    $student_lottery_status = $this->rteLotteryHelper->getStudentLotteryStatus(
      'internal',
      _rte_mis_core_get_current_academic_year(),
      [$student->id()]);
    // No school allotted.
    if (!empty($student_lottery_status)) {
      $value = $this->t('Un-allotted');
      return $value;
    }

    // If there is no allocation or lottery data show student
    // verification status.
    if ($student->hasField('field_student_verification')) {
      $value = $student->get('field_student_verification')->view([
        'type' => 'list_default',
        'label' => 'hidden',
      ]);
      $value = $this->renderer->render($value);
    }

    return $value;
  }

}
