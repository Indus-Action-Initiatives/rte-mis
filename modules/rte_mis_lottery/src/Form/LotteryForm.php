<?php

namespace Drupal\rte_mis_lottery\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\State\StateInterface;
use Drupal\rte_mis_lottery\Services\RteLotteryHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Fetch the eligible students, randomize it and start the lottery process.
 */
class LotteryForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The keyvalue expirable factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface
   */
  protected $keyValueExpirableFactory;

  /**
   * The queue object.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

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
   * Constructs a LotteryForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $key_value_expirable_factory
   *   The keyvalue expirable factory.
   * @param \Drupal\Core\Queue\QueueInterface $queue
   *   The queue object.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\rte_mis_lottery\Services\RteLotteryHelper $rte_lottery_helper
   *   RTE lottery service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, KeyValueExpirableFactoryInterface $key_value_expirable_factory, QueueInterface $queue, FileSystemInterface $file_system, StateInterface $state, Connection $database, RteLotteryHelper $rte_lottery_helper) {
    $this->entityTypeManager = $entity_type_manager;
    $this->keyValueExpirableFactory = $key_value_expirable_factory;
    $this->queue = $queue;
    $this->fileSystem = $file_system;
    $this->state = $state;
    $this->database = $database;
    $this->rteLotteryHelper = $rte_lottery_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('keyvalue.expirable'),
      $container->get('queue')->get('student_data_lottery_queue_cron'),
      $container->get('file_system'),
      $container->get('state'),
      $container->get('database'),
      $container->get('rte_mis_lottery.lottery_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lottery_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // @todo Check the queue worker. If item exists, then show the lottery in
    // progress message and hide below lottery form.
    if ($this->queue->numberOfItems() > 0) {
      $form['label'] = [
        '#type' => 'label',
        '#title' => $this->t('Lottery already in progress. Please refresh periodically and wait for completion.'),
      ];
      return $form;
    }
    $form['label'] = [
      '#type' => 'label',
      '#title' => $this->t('Current Session: @currentYear-@nextYear', [
        '@currentYear' => date('Y'),
        '@nextYear' => date('y', strtotime('+1 year')),
      ]),
    ];
    $lotteryData = $this->keyValueExpirableFactory->get('rte_mis_lottery');
    $studentData = $lotteryData->get('student-list', []);
    $schoolData = $lotteryData->get('school-list', []);
    $displayData = array_slice($studentData, 0, 5000);
    if (!empty($displayData)) {
      foreach ($displayData as &$value) {
        unset($value['location']);
      }
    }

    $form['student_count'] = [
      '#type' => 'label',
      '#title' => $this->t('Total eligible Student: @count', ['@count' => count($studentData)]),
    ];

    $form['school_count'] = [
      '#type' => 'label',
      '#title' => $this->t('Total eligible School: @count', ['@count' => count($schoolData)]),
    ];

    $form['student'] = [
      '#type' => 'table',
      '#header' => [
        'student_name' => $this->t('Student Name'),
        'mobile_number' => $this->t('Mobile Number'),
        'application_number' => $this->t('Application Number'),
        'parent_name' => $this->t('Parent Name'),
      ],
      '#empty' => $this->t('No Student to displays'),
      '#rows' => $displayData,
    ];

    $form['randomize'] = [
      '#type' => 'submit',
      '#value' => empty($studentData) ? $this->t('Fetch and Randomize Students') : $this->t('Randomize Students'),
      '#submit' => ['::rteMisLotteryFetchStudent'],
      '#validate' => ['::validateRandomize'],
    ];

    $form['clear_student_list'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear List'),
      '#submit' => ['::rteMisLotteryClearStudentList'],
      '#limit_validation_errors' => [],
      '#access' => !empty($studentData) ? TRUE : FALSE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Lottery'),
    ];

    return $form;
  }

  /**
   * Validation for randomization.
   */
  public function validateRandomize(array &$form, FormStateInterface $form_state) {
    // Check if any valid student/school is available for lottery.
    $studentEntityId = $this->getStudentEntityId('validate');
    $schoolEntityId = $this->getSchoolEntityId('validate');
    if (empty($studentEntityId)) {
      $form_state->setErrorByName('student_count', $this->t('No eligible student found'));
    }
    if (empty($schoolEntityId)) {
      $form_state->setErrorByName('school_count', $this->t('No eligible school found'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Check data is fetched to begin the lottery process.
    $lotteryData = $this->keyValueExpirableFactory->get('rte_mis_lottery');
    $studentData = $lotteryData->get('student-list', []);
    $schoolData = $lotteryData->get('school-list', []);
    if (empty($studentData)) {
      $form_state->setErrorByName('student_count', $this->t('Student List cannot be empty'));
    }
    if (empty($schoolData)) {
      $form_state->setErrorByName('school_count', $this->t('School List cannot be empty'));
    }
  }

  /**
   * Clear the student list from keyvalue.expirable store.
   */
  public function rteMisLotteryClearStudentList(array &$form, FormStateInterface $form_state) {
    $lotteryData = $this->keyValueExpirableFactory->get('rte_mis_lottery');
    $lotteryData->delete('student-list');
    $lotteryData->delete('school-list');
  }

  /**
   * Fetch the eligible student and randomize it.
   */
  public function rteMisLotteryFetchStudent(array &$form, FormStateInterface $form_state) {
    $operations = [];
    // Define the number of items to process per batch.
    $batch_size = 100;
    $lotteryData = $this->keyValueExpirableFactory->get('rte_mis_lottery');
    $studentData = $lotteryData->get('student-list', []);
    $schoolData = $lotteryData->get('school-list', []);
    // Re-shuffle student list and store this in keyvalue service.
    // Also re-store the school data so that it has same expiry time.
    if (!empty($studentData) && !empty($schoolData)) {
      $student_details_result = $this->rteLotteryHelper->shuffleData($studentData);
      $lotteryData->setWithExpire('student-list', $student_details_result, 3600);
      $lotteryData->setWithExpire('school-list', $schoolData, 3600);
    }
    else {
      // ELse do entity query to fetch schools and students.
      // Fetch the student entity ids, shuffle and break them into the chunks.
      $student_details_result = $this->getStudentEntityId('lottery');
      shuffle($student_details_result);
      // Split the result into smaller batches.
      $chunks = array_chunk($student_details_result, $batch_size);
      foreach ($chunks as $chunk) {
        $operations[] = ['\Drupal\rte_mis_lottery\Batch\PrepareLotteryData::rteMisLotteryProcessStudent', [$chunk]];
      }
      // Fetch the school entity ids, shuffle and break them into the chunks.
      $school_details_result = $this->getSchoolEntityId();
      $chunks = array_chunk($school_details_result, $batch_size);
      foreach ($chunks as $chunk) {
        $operations[] = ['\Drupal\rte_mis_lottery\Batch\PrepareLotteryData::rteMisLotteryProcessSchool', [$chunk]];
      }
      // Prepare the batch data.
      $batch = [
        'title' => $this->t('Randomizing Students'),
        'operations' => $operations,
        'init_message' => $this->t('Starting Randomizing Student.'),
        'progressive' => TRUE,
        'progress_message' => $this->t('Processed @current out of @total. Time elapsed: @elapsed, estimated time remaining: @estimate.'),
        'finished' => '\Drupal\rte_mis_lottery\Batch\PrepareLotteryData::rteMisLotteryBatchFinished',
      ];

      batch_set($batch);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $lotteryData = $this->keyValueExpirableFactory->get('rte_mis_lottery');
    $studentData = $lotteryData->get('student-list', []);
    $schoolData = $lotteryData->get('school-list', []);
    // Re-shuffle the data.
    $studentData = $this->rteLotteryHelper->shuffleData($studentData);
    // Create chunk of student data.
    $studentData = array_chunk($studentData, 100, TRUE);
    foreach ($studentData as $value) {
      $this->queue->createItem($value);
    }
    $directory = '../lottery_files';
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    // Retrieve and increment the file number from the state system.
    $counter = $this->state->get('lottery_data_file_number', 1);
    $destination = $directory . '/school_data_' . $counter . '.json';
    if (file_exists($destination)) {
      do {
        $destination = $directory . '/school_data_' . ++$counter . '.json';
      } while (file_exists($destination));
    }
    $this->state->set('lottery_data_file_number', $counter);
    // Set the state to internal to differentiate b/w internal/external request.
    $this->state->set('lottery_initiated_type', 'internal');
    $this->fileSystem->saveData(Json::Encode($schoolData), $destination, FileSystemInterface::EXISTS_REPLACE);
    $this->messenger()->addMessage($this->t('Lottery Started'));
    $this->logger('rte_mis_lottery')->info($this->t('Lottery Initiated. Type: Internal'));
  }

  /**
   * Fetch the approved student entity ids.
   *
   * @param string $op
   *   Operation name.
   */
  protected function getStudentEntityId($op = '') {
    $student_details_query = $this->entityTypeManager->getStorage('mini_node')->getQuery();
    $student_details_query->condition('status', 1)
      ->condition('field_academic_year', _rte_mis_core_get_current_academic_year())
      ->condition('field_student_verification', 'student_workflow_approved')
      ->condition('type', 'student_details')
      ->accessCheck(FALSE);
    // If method is triggered by randomize button, fetch only single record.
    if ($op == 'validate') {
      $student_details_query->range(0, 1);
    }
    elseif ($op == 'lottery') {
      // Add check if student is not already enrolled in schools.
      // this can be used if second round of lottery is select.
      $student_ids = $this->database->select('rte_mis_lottery_results', 'rt')
        ->fields('rt', ['student_id'])
        ->condition('academic_session', _rte_mis_core_get_current_academic_year())
        ->condition('lottery_type', 'internal')
        ->execute()
        ->fetchCol();
      if (!empty($student_ids)) {
        $student_details_query->condition('id', $student_ids, 'NOT IN');
      }
    }
    return $student_details_query->execute();
  }

  /**
   * Fetch the approved school entity ids.
   *
   * @param string $op
   *   Operation name.
   */
  protected function getSchoolEntityId($op = '') {
    $school_details_query = $this->entityTypeManager->getStorage('mini_node')->getQuery();
    $school_details_query->condition('status', 1)
      ->condition('field_academic_year', _rte_mis_core_get_current_academic_year())
      ->condition('field_school_verification', 'school_registration_verification_approved_by_deo')
      ->condition('type', 'school_details')
      ->accessCheck(FALSE);
    // If method is triggered by randomize button, fetch only single record.
    if ($op == 'validate') {
      $school_details_query->range(0, 1);
    }
    return $school_details_query->execute();
  }

}
