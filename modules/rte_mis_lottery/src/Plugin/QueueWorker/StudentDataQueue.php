<?php

namespace Drupal\rte_mis_lottery\Plugin\QueueWorker;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\rte_mis_lottery\Services\RteLotteryHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A queue worker for testing cron exception handling.
 *
 * @QueueWorker(
 *   id = "student_data_lottery_queue_cron",
 *   title = @Translation("Student Data Lottery Queue")
 * )
 */
class StudentDataQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Rte Lottery service.
   *
   * @var \Drupal\rte_mis_lottery\Services\RteLotteryHelper
   */
  protected $rteLotteryHelper;

  /**
   * Queue factory instance.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

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
   * The keyvalue expirable factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface
   */
  protected $keyValueExpirableFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new LocaleTranslation object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\rte_mis_lottery\Services\RteLotteryHelper $rte_lottery_helper
   *   RTE lottery service.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   Queue factory instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $key_value_expirable_factory
   *   The keyvalue expirable factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, StateInterface $state, FileSystemInterface $file_system, Connection $database, RteLotteryHelper $rte_lottery_helper, QueueFactory $queueFactory, EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger, KeyValueExpirableFactoryInterface $key_value_expirable_factory, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->state = $state;
    $this->fileSystem = $file_system;
    $this->database = $database;
    $this->rteLotteryHelper = $rte_lottery_helper;
    $this->queueFactory = $queueFactory;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->keyValueExpirableFactory = $key_value_expirable_factory;
    $this->moduleHandler = $module_handler;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state'),
      $container->get('file_system'),
      $container->get('database'),
      $container->get('rte_mis_lottery.lottery_helper'),
      $container->get('queue'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('rte_mis_lottery'),
      $container->get('keyvalue.expirable'),
      $container->get('module_handler'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $lottery_id = $this->state->get('lottery_data_file_number', 1);
    $directory = '../lottery_files';
    if (is_int($lottery_id)) {
      $file_path = $directory . '/school_data_' . $lottery_id . '.json';
    }
    $content = $this->fileSystem->realpath($file_path);
    if ($content) {
      // Read file content.
      $file_content = file_get_contents($content);
      $school_data = Json::decode($file_content);
      $enqueue_data = [];
      $lottery_initiated_type = $this->state->get('lottery_initiated_type');
      $current_academic_session = _rte_mis_core_get_current_academic_year();
      $student_allocation_queue = $this->queueFactory->get('student_allocation');
      foreach ($data as $student_id => $student_data) {
        // Prepare the data array to update the lottery result for student.
        $values = [
          'student_id' => $student_id,
          'student_name' => $student_data['name'],
          'student_application_number' => $student_data['application_id'],
          'mobile_number' => $student_data['mobile'],
          'lottery_type' => $lottery_initiated_type,
          'academic_session' => $current_academic_session,
          'lottery_id' => $lottery_id,
        ];
        // We'll only process top preference. If other preference exists it
        // should be considered in the next batch of processing.
        $school_preference = array_shift($student_data['preference']) ?? NULL;
        // Fetch the rte_mis_school config.
        // It will be used to display class value in the logger.
        $school_config = $this->configFactory->get('rte_mis_school.settings');
        $class_level = $school_config->get('field_default_options.class_level');
        // Check the following conditions below allotting seat to student.
        // 1. Student has selected school_preference.
        // 2. Student's school preference should exist in json file.
        if (!empty($school_preference) && isset($school_data[$school_preference['school_id']]) && isset($school_data[$school_preference['school_id']]['entry_class'][$school_preference['entry_class']])) {
          $seat_count = $this->rteLotteryHelper->getSchoolSeatCount($school_preference['school_id'], $school_preference['entry_class'], $lottery_initiated_type, $current_academic_session, $lottery_id);
          if ($seat_count === FALSE) {
            $seat_count = $school_data[$school_preference['school_id']]['entry_class'][$school_preference['entry_class']]['rte_seat'];
            // Before proceeding for the allotment we need to update the
            // available seat count considering if any students dropped out
            // after the allocation.
            $alloted_seat_count = $this->rteLotteryHelper->getAllotedSeatCount($school_preference['school_id'], $school_preference['entry_class'], $current_academic_session);
            // Once we get the alloted seat counts for this particular school,
            // entry class and medium, we update the seat count by subtracting
            // the alloted seats count from the actual seat count.
            if (!empty($alloted_seat_count)) {
              foreach ($alloted_seat_count as $seat_medium => $count) {
                $seat_count[$seat_medium] -= $count;
              }
            }
          }

          // Check further conditions.
          // 3. School should have entry class, selected by student.
          // 4. Rte_seat should exist for selected language selected in school.
          // 5. Check school mapped location matches with student location.
          if (isset($seat_count[$school_preference['medium']]) && $seat_count[$school_preference['medium']] > 0 && in_array($student_data['location'], $school_data[$school_preference['school_id']]['location'])) {
            // Decrease the seat count.
            $seat_count[$school_preference['medium']] -= 1;
            // Add the alloted school id.
            $values['allotted_school_id'] = $school_preference['school_id'];
            $values['allocation_status'] = 'Allotted';
            $values['entry_class'] = $school_preference['entry_class'];
            $values['medium'] = $school_preference['medium'];
            $values['school_udise_code'] = $school_data[$school_preference['school_id']]['udise_code'] ?? '-';
            $this->rteLotteryHelper->updateLotteryResult($values);
            // Log the student allotment.
            $this->logger->info($this->t("@student_name(@student_id) has been alloted to @school_name(@school_id) to medium: @medium and entry_class: @entry_class. The available number of seats in @school_name(@school_id) for medium: @medium and entry_class: @entry_class is @seat.", [
              '@student_name' => $student_data['name'],
              '@student_id' => $student_id,
              '@school_name' => $school_data[$school_preference['school_id']]['name'],
              '@school_id' => $school_preference['school_id'],
              '@medium' => $school_preference['medium'],
              '@entry_class' => $class_level[$school_preference['entry_class']],
              '@seat' => $seat_count[$school_preference['medium']],
            ]));
            // Prepare the data array to update the seat count.
            $values = [
              'school_id' => $school_preference['school_id'],
              'school_name' => $school_data[$school_preference['school_id']]['name'],
              'entry_class' => $school_preference['entry_class'],
              'lottery_type' => $lottery_initiated_type,
              'academic_session' => $current_academic_session,
              'lottery_id' => $lottery_id,
            ] + $seat_count;
            // Update the count of seat in
            // `rte_mis_lottery_school_seats_status` table.
            $this->rteLotteryHelper->updateSchoolSeatCount($values);
            // Check the lottery initiated type. If it equal to `internal` then
            // save the student in school mini_node.
            if ($lottery_initiated_type === 'internal') {
              $isRteMisAllocationEnabled = $this->moduleHandler->moduleExists('rte_mis_allocation');
              // Add item in `student_allocation` queue.
              if ($isRteMisAllocationEnabled && isset($school_preference['school_id']) && isset($student_id) && isset($school_preference['entry_class']) && !empty($current_academic_session) && !empty($school_preference['medium'])) {
                $student_allocation_queue->createItem([
                  'field_academic_year_allocation' => $current_academic_session,
                  'field_entry_class_for_allocation' => $school_preference['entry_class'],
                  'field_medium' => $school_preference['medium'],
                  'field_school' => $school_preference['school_id'],
                  'field_student' => $student_id,
                  'type' => 'allocation',
                ]);
              }
              else {
                // Log the error for student allocation mini_node.
                $this->logger->error($this->t("Cannot create allocation mini_node for @student_name(@student_id) in @school_name(@school_id) to medium: @medium and entry_class: @entry_class", [
                  '@student_name' => $student_data['name'],
                  '@student_id' => $student_id,
                  '@school_name' => $school_data[$school_preference['school_id']]['name'],
                  '@school_id' => $school_preference['school_id'],
                  '@medium' => $school_preference['medium'],
                  '@entry_class' => $class_level[$school_preference['entry_class']],
                ]));
              }
            }
          }
          else {
            // Log the student un-allotment.
            $this->logger->info($this->t("@student_name(@student_id) has not been alloted to @school_name(@school_id) to medium: @medium and entry_class: @entry_class. The available number of seats in @school_name(@school_id) for medium: @medium and entry_class: @entry_class is @seat.", [
              '@student_name' => $student_data['name'],
              '@student_id' => $student_id,
              '@school_name' => $school_data[$school_preference['school_id']]['name'],
              '@school_id' => $school_preference['school_id'],
              '@medium' => $school_preference['medium'],
              '@entry_class' => $class_level[$school_preference['entry_class']],
              '@seat' => $seat_count[$school_preference['medium']],
            ]));
            // Student has not been allocated on current preference.
            // Check the following conditions.
            // 1. If other school preference exists, then add the student data
            // in array which will re-added in queue.
            if (!empty($student_data['preference'])) {
              $enqueue_data[$student_id] = $student_data;
            }
            // 2. If other preference does not exist, then mark the student as
            // unallocated.
            else {
              $values['allocation_status'] = 'Un-alloted';
              $this->rteLotteryHelper->updateLotteryResult($values);
            }
          }
        }
        else {
          // Log the student un-allotment.
          $this->logger->info($this->t("@student_name(@student_id) has not been alloted to @school_name(@school_id) to medium: @medium and entry_class: @entry_class due to the unavailability of seats in the schools from the preferred list.", [
            '@student_name' => $student_data['name'],
            '@student_id' => $student_id,
            '@school_name' => $school_data[$school_preference['school_id']]['name'] ?? '-',
            '@school_id' => $school_preference['school_id'],
            '@medium' => $school_preference['medium'],
            '@entry_class' => $class_level[$school_preference['entry_class']],
          ]));
          // Student has not been allocated on current preference.
          // Check the following conditions.
          // 1. If other school preference exists, then add the student data
          // in array which will re-added in queue.
          if (!empty($student_data['preference'])) {
            $enqueue_data[$student_id] = $student_data;
          }
          else {
            // Student does not have preference. Mark student as `un-alloted`.
            $values['allocation_status'] = 'Un-alloted';
            $this->rteLotteryHelper->updateLotteryResult($values);
          }
        }

      }
      $queueFactory = $this->queueFactory->get('student_data_lottery_queue_cron');
      // Re-add the data in queue.
      if (!empty($enqueue_data)) {
        $queueFactory->createItem($enqueue_data);
      }
      // Below condition is used, when all data is processed.
      if ($queueFactory->numberOfItems() == 1 && empty($enqueue_data)) {
        $this->logger->info('Lottery Finished');
        // Below is applicable for only internal request.
        if ($lottery_initiated_type === 'internal') {
          // Delete all data stored for randomizing student.
          $this->keyValueExpirableFactory->get('rte_mis_lottery')->deleteAll();
        }
        else {
          // Set lottery id in state for external lottery.
          $this->state->set('external_lottery_id', $lottery_id);
        }
      }
    }
  }

}
