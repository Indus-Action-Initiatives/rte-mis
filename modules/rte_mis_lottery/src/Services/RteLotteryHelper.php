<?php

namespace Drupal\rte_mis_lottery\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

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
   * Constructs a RteLotteryHelper object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(Connection $database, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->database = $database;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
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
      // @todo Add logger message.
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
      // @todo Add logger message
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
      // @todo Add logger message(should contain data of failing school)
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
      // @todo Add logger message(should contain data of failing allocation)
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
   */
  public function getLotteryResult($type, $academic_session) {
    $result = FALSE;
    try {
      if (!empty($type) && !empty($academic_session)) {
        $result = $this->database->select('rte_mis_lottery_results', 'rt')
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
          ->condition('lottery_type', $type)
          ->execute()->fetchAll();
      }
    }
    catch (\Exception $e) {
      // @todo Add logger message
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

}
