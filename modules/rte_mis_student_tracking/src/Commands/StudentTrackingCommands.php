<?php

namespace Drupal\rte_mis_student_tracking\Commands;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eck\EckEntityInterface;
use Drupal\workflow\Entity\WorkflowTransition;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for student tracking.
 */
class StudentTrackingCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Queue factory instance.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Constructs a StudentTrackingCommands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current account.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   Queue factory instance.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user, TimeInterface $time, QueueFactory $queueFactory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->time = $time;
    $this->queueFactory = $queueFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('datetime.time'),
      $container->get('queue')
    );
  }

  /**
   * Command to update student allocation status as per the given ids.
   *
   * @param string $ids
   *   Allocation mini node id(s), comma separated if multiple ids are
   *   to be passed. Don't pass anything to process all allocation mini nodes.
   *
   * @command rte_mis_student_tracking:update-allocation-status
   * @aliases uas
   * @usage uas 1,2,3
   *   This will update allocation mini nodes with ids 1,2,3 and update the
   *   allocation status from allotted to admitted.
   */
  public function updateAllocationStatus($ids = NULL) {
    if ($this->io()->confirm('Are you sure you want to update the allocation status?', FALSE)) {
      $from_state = 'student_admission_workflow_allotted';
      $to_state = 'student_admission_workflow_admitted';
      // If ids are provided use these ids to process allocation mini
      // nodes else process all published allocation mini nodes.
      if ($ids) {
        $nids = explode(',', $ids);
      }
      else {
        $nids = $this->entityTypeManager->getStorage('mini_node')->getQuery()
          ->accessCheck(FALSE)
          ->condition('type', 'allocation')
          ->condition('status', 1)
          ->condition('field_student_allocation_status', $from_state)
          ->execute();
      }
      $user_storage = $this->entityTypeManager->getStorage('user');
      // Get a user with app admin role so that state transition does
      // not fail.
      $user_ids = $user_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('roles', 'app_admin')
        ->condition('status', 1)
        ->execute();
      // Load allocation nodes and update status.
      foreach ($nids as $nid) {
        $mini_node = $this->entityTypeManager->getStorage('mini_node')->load($nid);
        if ($mini_node instanceof EckEntityInterface && $mini_node->bundle() == 'allocation') {
          // Get the current state.
          $current_state = workflow_node_current_state($mini_node, 'field_student_allocation_status');
          if ($current_state == $from_state) {
            $transition = WorkflowTransition::create([
              0 => $current_state,
              'field_name' => 'field_student_allocation_status',
            ]);
            // Set the target entity.
            $transition->setTargetEntity($mini_node);
            // Set the target state with require details.
            $transition->setValues($to_state, reset($user_ids), $this->time->getRequestTime(), $this->t('Updated'));
            // Execute the transition and update the allocation entity.
            $transition->executeAndUpdateEntity();
          }
        }
        else {
          $this->output()->writeln("Could not update allocation mini node.");
        }
      }

      $this->output()->writeln('Allocation update process has been completed.');
    }
    else {
      // If user declines, output a message.
      $this->output()->writeln('Update operation cancelled.');
    }
  }

  /**
   * Command to create bulk data for student performance mini node.
   *
   * @param int $count
   *   Number of mini nodes to generate.
   *
   * @command rte_mis_student_tracking:bulk-generate-student-peformance
   * @aliases bgsp
   * @usage bgsf 1000
   *   This will create 1000 mini nodes for student performance.
   */
  public function bulkGenerateStudentPerformance($count = 100) {
    $mini_node_storage = $this->entityTypeManager->getStorage('mini_node');
    // Get max 5 students.
    $student_ids = $mini_node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'student_details')
      ->condition('status', 1)
      ->range(0, 5)
      ->execute();

    // Get max 5 schools.
    $school_ids = $mini_node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'school_details')
      ->condition('status', 1)
      ->range(0, 5)
      ->execute();

    // Student's caste.
    $caste = ['sc', 'st', 'obc', 'gen', 'oc', 'bc'];

    // Student's gender.
    $gender = ['girl', 'boy', 'transgender'];

    // Student's medium.
    $medium = ['english', 'hindi'];

    // Religion.
    $religion = ['hindu', 'muslim', 'sikh', 'christian', 'others'];

    // Entry class.
    $entry_class = [0, 1, 2, 3];

    // Current class.
    $current_class = [7, 8, 9 , 10];

    // Entry year.
    $entry_year = ['2020_21', '2021_22', '2023_24'];

    // DOB.
    $dob = ['2020-08-22', '2019-06-20', '2018-04-15'];

    // Parent name.
    $parent_name = ['parent one', 'parent two', 'parent three'];

    // Address.
    $address = ['test address 1', 'test address 2'];

    // Workflow states.
    $states = ['student_tracking_workflow_studying', 'student_tracking_workflow_dropout'];

    // Generate mini nodes as per the requested number of items
    // with random field values from given set of values.
    while ($count--) {
      // Load random student details mini node and get student name.
      $random_student_id = $this->random($student_ids);
      $student = $mini_node_storage->load($random_student_id);
      $student_name = $student->get('field_student_name')->getString();

      // Load random school details mini node and get school name.
      $random_school_id = $this->random($school_ids);
      $school = $mini_node_storage->load($random_school_id);
      $school_name = $school->get('field_school_name')->getString();
      $school_udise_code = $school->get('field_udise_code')->getString();

      $failed_count = 0;
      try {
        // Create student performance node with random values.
        $mini_node = $mini_node_storage->create([
          'type' => 'student_performance',
          'field_academic_session' => _rte_mis_core_get_previous_academic_year(),
          'field_caste' => $this->random($caste),
          'field_current_class' => $this->random($current_class),
          'field_date_of_birth' => $this->random($dob),
          'field_entry_class_for_allocation' => $this->random($entry_class),
          'field_entry_year' => $this->random($entry_year),
          'field_gender' => $this->random($gender),
          'field_medium' => $this->random($medium),
          'field_parent_name' => $this->random($parent_name),
          'field_residential_address' => $this->random($address),
          'field_school' => [
            'target_id' => $random_student_id,
          ],
          'field_student' => [
            'target_id' => $random_school_id,
          ],
          'field_school_name' => $school_name,
          'field_udise_code' => $school_udise_code,
          'field_student_name' => $student_name,
          'field_religion' => $this->random($religion),
        ]);
        $mini_node->save();
      }
      catch (\Exception $e) {
        $failed_count++;
      }

      $user_storage = $this->entityTypeManager->getStorage('user');
      // Get a user with app admin role so that state transition does
      // not fail.
      $user_ids = $user_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('roles', 'app_admin')
        ->condition('status', 1)
        ->execute();

      $transition = WorkflowTransition::create([
        0 => 'student_tracking_workflow_creation',
        'field_name' => 'field_student_tracking_status',
      ]);
      // Set the target entity.
      $transition->setTargetEntity($mini_node);
      // Set the target state with require details.
      $transition->setValues($this->random($states), reset($user_ids), $this->time->getRequestTime(), $this->t('Status Updated'));
      // Execute the transition and update the allocation entity.
      $transition->executeAndUpdateEntity();

      $this->output()->writeln('Operation finished successfully.');
      if ($failed_count > 0) {
        $this->output()->writeln("Failed to create $failed_count mini nodes.");
      }
    }
  }

  /**
   * Command to delete student performance mini nodes.
   *
   * @param mixed $count
   *   Number of mini nodes to delete.
   *
   * @command rte_mis_student_tracking:delete-student-peformance
   * @aliases dsp
   * @usage dsp 1000
   *   This will delete 1000 mini nodes for student performance.
   */
  public function deleteStudentPerformance($count = NULL) {
    $mini_node_storage = $this->entityTypeManager->getStorage('mini_node');
    $query = $mini_node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'student_performance')
      ->condition('status', 1)
      ->sort('id', 'DESC');
    // Fetch records as per the count provided.
    // If empty fetch all student performance mini nodes.
    if (!empty($count)) {
      $query->range(0, $count);
    }
    $mini_node_ids = $query->execute();
    // Delete all selected mini nodes.
    foreach ($mini_node_ids as $mini_node_id) {
      $mini_node_storage->load($mini_node_id)->delete();
    }
  }

  /**
   * Get random item from an array.
   *
   * @param array $values
   *   An array of values.
   *
   * @return mixed
   *   Random value from the array or empty string.
   */
  private function random($values) {
    if (is_array($values)) {
      return $values[array_rand($values)];
    }
    return '';
  }

  /**
   * Command to update student allocation status as per the given states.
   *
   * @param mixed $count
   *   Number of items to add in queue.
   *
   * @command rte_mis_student_tracking:add-items-to-queue
   * @aliases aitq
   * @usage aitq
   *   This will auto promote the students, this is useful for student
   *   tracking testing.
   */
  public function addItemsToStudentTrackingQueue($count = NULL) {
    // Load all student performance mini nodes with previous academic year.
    $query = $this->entityTypeManager->getStorage('mini_node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'student_performance')
      ->condition('field_academic_session', _rte_mis_core_get_previous_academic_year())
      ->condition('status', 1);
    if ($count) {
      $query->range(0, $count);
    }
    $student_performance_ids = $query->execute();

    // Return if student_performance_ids is empty.
    if (empty($student_performance_ids)) {
      return;
    }
    // Student tracking queue.
    $queue = $this->queueFactory->get('student_tracking_queue');
    // Push mini node ids to the student tracking queue for
    // processing in chunks of 100 items.
    foreach (array_chunk($student_performance_ids, 100) as $chunk) {
      $queue->createItem($chunk);
    }

    $items = count($student_performance_ids);
    $this->output()->writeln("Update operation cancelled. $items items added in the queue.");
  }

}
