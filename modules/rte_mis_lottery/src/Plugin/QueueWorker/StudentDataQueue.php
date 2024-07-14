<?php

namespace Drupal\rte_mis_lottery\Plugin\QueueWorker;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eck\EckEntityInterface;
use Drupal\rte_mis_lottery\Services\RteLotteryHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A queue worker for testing cron exception handling.
 *
 * @QueueWorker(
 *   id = "student_data_lottery_queue_cron",
 *   title = @Translation("Student Data Lottery Queue"),
 *   cron = {"time" = 160}
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
   * @var \Drupal\rte_mis_lottery\Services
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
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, StateInterface $state, FileSystemInterface $file_system, Connection $database, RteLotteryHelper $rte_lottery_helper, QueueFactory $queueFactory, EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger, KeyValueExpirableFactoryInterface $key_value_expirable_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->state = $state;
    $this->fileSystem = $file_system;
    $this->database = $database;
    $this->rteLotteryHelper = $rte_lottery_helper;
    $this->queueFactory = $queueFactory;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->keyValueExpirableFactory = $key_value_expirable_factory;
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
      $container->get('keyvalue.expirable')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $file_counter = $this->state->get('lottery_data_file_number');
    $directory = '../lottery_files';
    $file_path = $directory . '/school_data.json';
    if (is_int($file_counter)) {
      $file_path = $directory . '/school_data_' . $file_counter . '.json';
    }
    $content = $this->fileSystem->realpath($file_path);
    if ($content) {
      // Read file content.
      $file_content = file_get_contents($content);
      $school_data = Json::decode($file_content);
      $enqueue_data = [];
      $lottery_initiated_type = $this->state->get('lottery_initiated_type');
      $current_academic_session = _rte_mis_core_get_current_academic_year();
      foreach ($data as $student_id => $student_data) {
        // Prepare the data array to update the lottery result for student.
        $values = [
          'student_id' => $student_id,
          'student_name' => $student_data['name'],
          'student_application_number' => $student_data['application_id'],
          'mobile_number' => $student_data['mobile'],
          'lottery_type' => $lottery_initiated_type,
          'academic_session' => $current_academic_session,
        ];
        // We'll only process top preference. If other preference exists it
        // should be considered in the next batch of processing.
        $school_preference = array_shift($student_data['preference']) ?? NULL;
        // Check the following conditions below allotting seat to student.
        // 1. Student has selected school_preference.
        // 2. Student's school preference should exist in json file.
        if (!empty($school_preference) && !empty($school_data[$school_preference['school_id']]) && !empty($school_data[$school_preference['school_id']]['entry_class'][$school_preference['entry_class']])) {
          $seat_count = $this->rteLotteryHelper->getSchoolSeatCount($school_preference['school_id'], $school_preference['entry_class'], $lottery_initiated_type, $current_academic_session);
          if ($seat_count === FALSE) {
            $seat_count = $school_data[$school_preference['school_id']]['entry_class'][$school_preference['entry_class']]['rte_seat'];
          }
          // Check further conditions.
          // 3. School should have entry class, selected by student.
          // 4. Rte_seat should exist for selected language selected in school.
          // 5. Check school mapped location matches with student location.
          if (!empty($seat_count[$school_preference['medium']]) && $seat_count[$school_preference['medium']] > 0 && in_array($student_data['location'], $school_data[$school_preference['school_id']]['location'])) {
            // Log the student allotment.
            $this->logger->info($this->t("@student_name(@student_id) has been alloted to @school_name(@school_id) to medium: @medium and entry_class: @entry_class", [
              '@student_name' => $student_data['name'],
              '@student_id' => $student_id,
              '@school_name' => $school_data[$school_preference['school_id']]['name'],
              '@school_id' => $school_preference['school_id'],
              '@medium' => $school_preference['medium'],
              '@entry_class' => $school_preference['entry_class'],
            ]));
            // Decrease the seat count.
            $seat_count[$school_preference['medium']] -= 1;
            // Add the alloted school id.
            $values['allotted_school_id'] = $school_preference['school_id'];
            $values['allocation_status'] = 'Allotted';
            $values['entry_class'] = $school_preference['entry_class'];
            $values['medium'] = $school_preference['medium'];
            $values['school_udise_code'] = $school_data[$school_preference['school_id']]['udise_code'] ?? '-';
            $this->rteLotteryHelper->updateLotteryResult($values);
            // Prepare the data array to update the seat count.
            $values = [
              'school_id' => $school_preference['school_id'],
              'school_name' => $school_data[$school_preference['school_id']]['name'],
              'entry_class' => $school_preference['entry_class'],
              'lottery_type' => $lottery_initiated_type,
              'academic_session' => $current_academic_session,
            ] + $seat_count;
            // Update the count of seat in
            // `rte_mis_lottery_school_seats_status` table.
            $this->rteLotteryHelper->updateSchoolSeatCount($values);
            // Check the lottery initiated type. If it equal to `internal` then
            // save the student in school mini_node.
            if ($lottery_initiated_type === 'internal') {
              $school_id = $school_preference['school_id'] ?? NULL;
              if (is_numeric($school_id)) {
                $school_mini_node = $this->entityTypeManager->getStorage('mini_node')->load($school_id);
                if ($school_mini_node instanceof EckEntityInterface) {
                  $values = [
                    'student_id' => $student_id,
                    'medium' => $school_preference['medium'],
                    'entry_class' => $school_preference['entry_class'],
                  ];
                  $paragraph_details = $this->rteLotteryHelper->createStudentAllocationParagraph($values);
                  if (!empty($paragraph_details)) {
                    $school_mini_node->get('field_allotted_students')->appendItem([
                      'target_id' => $paragraph_details['target_id'],
                      'target_revision_id' => $paragraph_details['target_revision_id'],
                    ]);
                    $school_mini_node->save();
                  }
                }
              }

            }
          }
          else {
            // Log the student un-allotment.
            $this->logger->info($this->t("@student_name(@student_id) has not been alloted to @school_name(@school_id) to medium: @medium and entry_class: @entry_class", [
              '@student_name' => $student_data['name'],
              '@student_id' => $student_id,
              '@school_name' => $school_data[$school_preference['school_id']]['name'],
              '@school_id' => $school_preference['school_id'],
              '@medium' => $school_preference['medium'],
              '@entry_class' => $school_preference['entry_class'],
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
          $this->logger->info($this->t("@student_name(@student_id) has not been alloted to @school_name(@school_id) to medium: @medium and entry_class: @entry_class", [
            '@student_name' => $student_data['name'],
            '@student_id' => $student_id,
            '@school_name' => $school_data[$school_preference['school_id']]['name'] ?? '-',
            '@school_id' => $school_preference['school_id'],
            '@medium' => $school_preference['medium'],
            '@entry_class' => $school_preference['entry_class'],
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
      }
    }
  }

}
