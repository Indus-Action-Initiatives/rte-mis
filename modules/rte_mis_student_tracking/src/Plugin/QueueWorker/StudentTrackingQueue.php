<?php

namespace Drupal\rte_mis_student_tracking\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eck\EckEntityInterface;
use Drupal\workflow\Entity\WorkflowTransition;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A queue worker for testing cron exception handling.
 *
 * @QueueWorker(
 *   id = "student_tracking_queue",
 *   title = @Translation("Student Tracking Auto Promotion Queue")
 * )
 */
class StudentTrackingQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  public $configFactory;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new StudentTrackingQueue object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger,
    ConfigFactoryInterface $config_factory,
    TimeInterface $time,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->configFactory = $config_factory;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('rte_mis_student_tracking'),
      $container->get('config.factory'),
      $container->get('datetime.time'),
    );
  }

  /**
   * Processes single queue item.
   *
   * @param mixed $data
   *   The data contains an array of items containing
   *   student performance mini nodes.
   */
  public function processItem($data) {
    // Loop over all the items.
    foreach ($data as $mini_node_id) {
      $student_tracking_config = $this->configFactory->get('rte_mis_student_tracking.settings');
      $allowed_class_list = $student_tracking_config->get('allowed_class_list') ?? [];
      // Load student performance mini nodes.
      $mini_node = $this->entityTypeManager->getStorage('mini_node')->load($mini_node_id);
      if ($mini_node instanceof EckEntityInterface) {
        $current_state = workflow_node_current_state($mini_node, 'field_student_tracking_status');
        $current_class = $mini_node->get('field_current_class')->getString();
        // New class the student will be promoted to.
        $new_class = $current_class;
        $promoted_class = TRUE;

        // If current status is 'Dropout' then do not create new
        // student performance mini node and set promoted class as 'No'.
        if ($current_state == 'student_tracking_workflow_dropout') {
          $promoted_class = FALSE;
        }

        // Check if it valid class.
        // Proceed for class check only if promoted class is TRUE
        // that means student has not dropped out in prev year.
        if (isset($allowed_class_list[$current_class]) && $promoted_class) {
          // Check if student can be promoted to next class i.e., next class
          // is available.
          if (isset($allowed_class_list[$current_class + 1])) {
            $new_class = $current_class + 1;
          }
          else {
            // Keep the current class as is and update the workflow and mark the
            // status as 'Education Completed'.
            // Get the current state.
            if ($current_state == 'student_tracking_workflow_studying') {
              $transition = WorkflowTransition::create([
                0 => $current_state,
                'field_name' => 'field_student_tracking_status',
              ]);
              // Set the target entity.
              $transition->setTargetEntity($mini_node);
              // Get a user with app admin role so that state transition does
              // not fail.
              $user_storage = $this->entityTypeManager->getStorage('user');
              $user_ids = $user_storage->getQuery()
                ->accessCheck(FALSE)
                ->condition('roles', 'app_admin')
                ->condition('status', 1)
                ->range(0, 1)
                ->execute();
              // Set the target state to 'Education completed'.
              $transition->setValues('student_tracking_workflow_edu_completed', reset($user_ids), $this->time->getRequestTime(), $this->t('Education completed.'));
              // Execute the transition and update the student_performance
              // mini node.
              $transition->executeAndUpdateEntity();
              $promoted_class = FALSE;
            }
          }
        }

        // If promoted class is FALSE, edit the same student performance
        // mini node and mark promoted class as 'No'. Otherwise, create
        // new mini node and set updated class.
        if (!$promoted_class) {
          $mini_node->set('field_promoted_class', $promoted_class)
            ->set('status', 0)
            ->save();
        }
        else {
          // Prepare data for new mini node.
          $fields_data = $this->prepareNewStudentPerformanceData($mini_node);
          // Create new student performance mini node.
          try {
            $new_mini_node = $this->entityTypeManager->getStorage('mini_node')->create($fields_data);
            // Set current academic year.
            $new_mini_node->set('field_academic_session_tracking', _rte_mis_core_get_current_academic_year());
            // Set updated current class.
            $new_mini_node->set('field_current_class', $new_class);
            // Set promoted class.
            $new_mini_node->set('field_promoted_class', $promoted_class);
            // Set tracking status to 'studying' explicitly so that we get
            // proper transition in workflow history.
            $new_mini_node->set('field_student_tracking_status', 'student_tracking_workflow_studying');
            $new_mini_node->save();

            // Log the student performance mini node creation.
            $this->logger->info($this->t('Student @student_name (@student_id) has been promoted to the class @new_class successfully.', [
              '@student_name' => $mini_node->get('field_student_name')->getString(),
              '@student_id' => $mini_node->get('field_student')->getValue()[0]['target_id'],
              '@new_class' => $new_class,
            ]));
          }
          catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error($this->t('Failed to promote student @student_name (@student_id) to new class.', [
              '@student_name' => $mini_node->get('field_student_name')->getString(),
              '@student_id' => $mini_node->get('field_student')->getValue()[0]['target_id'],
            ]));
          }
        }
      }
    }
  }

  /**
   * Prepares fields data for new student performance mini node.
   *
   * @param \Drupal\eck\EckEntityInterface $mini_node
   *   Mini node to use to prepare fields data.
   *
   * @return array
   *   Fields data for new mini node.
   */
  protected function prepareNewStudentPerformanceData(EckEntityInterface $mini_node) {
    $fields = [
      'field_entry_year', 'field_student', 'field_student_name', 'field_medium', 'field_gender',
      'field_entry_class_for_allocation', 'field_mobile_number', 'field_parent_name',
      'field_caste', 'field_date_of_birth', 'field_religion', 'field_residential_address',
      'field_school', 'field_school_name', 'field_udise_code', 'field_student_application_number',
    ];
    $fields_data = [
      'type' => 'student_performance',
    ];
    // Get data from the given student performance mini node.
    foreach ($fields as $field_name) {
      $fields_data[$field_name] = $mini_node->get($field_name)->getValue() ?? NULL;
    }

    return $fields_data;
  }

}
