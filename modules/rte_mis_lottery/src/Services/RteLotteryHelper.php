<?php

namespace Drupal\rte_mis_lottery\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;

/**
 * Class RteLotteryHelper.
 *
 * Provides functionality to truncate a specified table.
 */
class RteLotteryHelper {

  /**
   * Active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  public $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a RteLotteryHelper object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(Connection $database, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory, StateInterface $state) {
    $this->database = $database;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
    $this->state = $state;
  }

  /**
   * Truncate all the entries from `rte_mis_lottery_school_seats_status` table.
   */
  public function clearTable() {
    try {
      $config = $this->configFactory->get('rte_mis_lottery.settings');
      $time_interval = $config->get('time_interval');
      // Calculate the current timestamp.
      $expected_expiry = strtotime("-{$time_interval} hours", time());
      // Delete records where created timestamp is
      // earlier than the current timestamp.
      $this->database->delete('rte_mis_lottery_school_seats_status')
        ->condition('created', $expected_expiry, '<')
        ->execute();
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('rte_mis_lottery')->error($e->getMessage());
      return FALSE;
    }

  }

  /**
   * Get the current seat count for school by providing on id and entry_class.
   *
   * @param string $school_id
   *   School Id.
   * @param string $entry_class
   *   Entry Class.
   * @param string $type_of_lottery
   *   Type of lottery.
   * @param string $academic_session
   *   Academic session.
   * @param int $lottery_id
   *   Lottery Id.
   */
  public function getSchoolSeatCount($school_id = '', $entry_class = '', $type_of_lottery = '', $academic_session = '', $lottery_id = 0) {
    try {
      if (!empty($school_id) && !empty($entry_class) && !empty($type_of_lottery) && !empty($academic_session)) {
        $language = $this->configFactory->get('rte_mis_lottery.settings')->get('field_default_options.languages');
        $result = $this->database->select('rte_mis_lottery_school_seats_status', 'school_status')
          ->fields('school_status', array_keys($language))
          ->condition('school_id', $school_id)
          ->condition('entry_class', $entry_class)
          ->condition('lottery_type', $type_of_lottery)
          ->condition('academic_session', $academic_session)
          ->condition('lottery_id', $lottery_id)
          ->execute()
          ->fetchAssoc();
        return $result;
      }
      return FALSE;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('rte_mis_lottery')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   * Update the school seat count.
   *
   * @param array $data
   *   Array of data that needs to updated/inserted in DB.
   */
  public function updateSchoolSeatCount($data) {
    try {
      $language = $this->configFactory->get('rte_mis_lottery.settings')->get('field_default_options.languages') ?? [];
      $language = array_keys($language);
      if (!empty($data['school_id']) && !empty($data['entry_class']) && !empty($data['school_name']) && !empty($data['lottery_type']) && !empty($data['academic_session']) && !empty($data['lottery_id']) && count(array_intersect_key(array_flip($language), $data)) === count($language)) {
        $data['created'] = time();
        $result = $this->database->merge('rte_mis_lottery_school_seats_status')
          ->fields($data)
          ->keys([
            'school_id' => $data['school_id'],
            'entry_class' => $data['entry_class'],
            'lottery_type' => $data['lottery_type'],
            'academic_session' => $data['academic_session'],
            'lottery_id' => $data['lottery_id'],
          ])->execute();
        return $result;
      }
      return NULL;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('rte_mis_lottery')->error($e->getMessage());
      $this->loggerFactory->get('rte_mis_lottery')->error('Failed to update seat for school. Here are details:
          School-Id: @school_id,
          Entry-Class: @entry_class,
          lottery_type: @lottery_type,
          Academic Session: @academic_session,
          Lottery Id: @lottery_id
        ', [
          '@school_id,' => $data['school_id'],
          '@entry_class,' => $data['entry_class'],
          '@lottery_type,' => $data['lottery_type'],
          '@academic_session,' => $data['academic_session'],
          '@lottery_id,' => $data['lottery_id'],
        ]);
      return FALSE;
    }
  }

  /**
   * Store the lottery result for student based on allotment status.
   *
   * @param array $data
   *   Array of data that needs to updated/inserted in DB.
   */
  public function updateLotteryResult($data) {
    try {
      if (!empty($data['student_id']) && !empty('student_name') && !empty('student_application_number') && !empty('mobile_number') && !empty('allocation_status')) {
        $data['created'] = time();
        // Create alloted records in `rte_mis_lottery_results` table.
        $this->database->insert('rte_mis_lottery_results')
          ->fields($data)
          ->execute();
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('rte_mis_lottery')->error($e->getMessage());
      $this->loggerFactory->get('rte_mis_lottery')->error('Failed to register lottery result. Here are details:
          Student-Id: @student_id,
          Student Name: @student_name,
          Application Number: @student_application_number,
          Mobile Number: @mobile_number,
          Allocation Status: @allocation_status
        ', [
          '@student_id,' => $data['student_id'],
          '@student_name,' => $data['student_name'],
          '@student_application_number,' => $data['student_application_number'],
          '@mobile_number,' => $data['mobile_number'],
          '@allocation_status,' => $data['allocation_status'],
        ]);
      return FALSE;
    }
  }

  /**
   * Get the student  grouped by student.
   */
  public function getStudentGroupBySchool() {
    $query = $this->database->select('rte_mis_lottery_results', 'r');
    $query->fields('r', ['allotted_school_id']);
    $query->addExpression('GROUP_CONCAT(student_id)', 'student_ids');
    $query->groupBy('r.allotted_school_id');
    $result = $query->execute()->fetchAll();
    return $result;
  }

  /**
   * Get the result of lottery.
   *
   * @param mixed $type
   *   Type of lottery.
   * @param mixed $academic_session
   *   Academic Session.
   * @param array $ids
   *   Student Ids.
   * @param string $lottery_id
   *   Lottery Id.
   */
  public function getLotteryResult(mixed $type, mixed $academic_session, ?array $ids = [], ?string $lottery_id = NULL) {
    $result = FALSE;
    try {
      if (!empty($type) && !empty($academic_session)) {
        $query = $this->database->select('rte_mis_lottery_results', 'rt')
          ->fields('rt', [
            'student_id',
            'student_name',
            'student_application_number',
            'mobile_number', 'allotted_school_id',
            'entry_class',
            'medium',
            'allocation_status',
            'academic_session',
            'school_udise_code',
          ])
          ->condition('academic_session', $academic_session)
          ->condition('lottery_type', $type);
        if ($ids) {
          $query->condition('student_id', $ids, 'IN');
        }
        // If the lottery id is passed,
        // For external it will be passed via states and
        // For internal it will be passed via the filter in '/lottery-results'.
        if ($lottery_id) {
          $query->condition('lottery_id', $lottery_id);
        }
        // If not passed get the latest value for state of internal lottery.
        else {
          $internal_lottery_id = $this->state->get('internal_lottery_id');
          $query->condition('lottery_id', $internal_lottery_id);
        }
        $result = $query->execute()->fetchAll();
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('rte_mis_lottery')->error($e->getMessage());
      return FALSE;
    }
    return $result;
  }

  /**
   * Shuffle the data.
   *
   * @param mixed $list
   *   The data need shuffling.
   */
  public function shuffleData($list) {
    if (!is_array($list)) {
      return $list;
    }
    $keys = array_keys($list);
    shuffle($keys);
    $random = [];
    foreach ($keys as $key) {
      $random[$key] = $list[$key];
    }
    return $random;
  }

  /**
   * Get the alloted seats count medium wise.
   *
   * @param string $school_id
   *   School Id.
   * @param string $entry_class
   *   Entry Class.
   * @param string $academic_session
   *   Academic session.
   *
   * @return array
   *   Array containing alloted seat counts medium wise.
   */
  public function getAllotedSeatCount(string $school_id, string $entry_class, string $academic_session): array {
    // Fetch total number of allocations, excluding the dropout allocations.
    try {
      $allocations = $this->entityTypeManager->getStorage('mini_node')->getAggregateQuery()
        ->accessCheck(FALSE)
        ->condition('field_school', $school_id)
        ->condition('field_academic_year_allocation', $academic_session)
        ->condition('field_student_allocation_status', 'student_admission_workflow_dropout', '<>')
        ->condition('field_entry_class_for_allocation', $entry_class)
        ->condition('status', 1)
        ->aggregate('field_medium', 'COUNT')
        ->groupBy('field_medium')
        ->execute();
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('rte_mis_lottery')->error($e->getMessage());
      return [];
    }

    $alloted_seat_count = [];
    // Build medium wise alloted seat count for the school.
    foreach ($allocations as $allocation) {
      $alloted_seat_count[$allocation['field_medium']] = $allocation['field_medium_count'];
    }

    return $alloted_seat_count;
  }

  /**
   * Get the student lottery status.
   *
   * @param string $type
   *   Type of lottery.
   * @param string $academic_session
   *   Academic Session.
   * @param array $ids
   *   Student Ids.
   *
   * @return bool
   *   True if lottery record exists else False.
   */
  public function getStudentLotteryStatus(string $type, string $academic_session, array $ids = []) {
    $result = FALSE;
    try {
      if (!empty($type) && !empty($academic_session)) {
        $query = $this->database->select('rte_mis_lottery_results', 'rt')
          ->fields('rt', [
            'student_id',
          ])
          ->condition('academic_session', $academic_session)
          ->condition('lottery_type', $type);
        if ($ids) {
          $query->condition('student_id', $ids, 'IN');
        }
        $result = $query->countQuery()->execute()->fetchField();
        return $result > 0;
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('rte_mis_lottery')->error($e->getMessage());
      return FALSE;
    }
    return $result;
  }

}
