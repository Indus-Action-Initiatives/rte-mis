<?php

namespace Drupal\rte_mis_lottery\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\paragraphs\ParagraphInterface;

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
   * Constructs a RteLotteryHelper object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(Connection $database, ConfigFactoryInterface $config_factory) {
    $this->database = $database;
    $this->configFactory = $config_factory;
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
   */
  public function getSchoolSeatCount($school_id, $entry_class) {
    try {
      if (!empty($school_id) && !empty($entry_class)) {
        $language = $this->configFactory->get('rte_mis_lottery.settings')->get('field_default_options.languages');
        $result = $this->database->select('rte_mis_lottery_school_seats_status', 'school_status')
          ->fields('school_status', array_keys($language))
          ->condition('school_id', $school_id)
          ->condition('entry_class', $entry_class)
          ->execute()
          ->fetchAssoc();
        return $result;
      }
      return FALSE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Update the school seat count.
   *
   * @param array $data
   *   Array of data that needs to updated/inserted in DB.
   * @param string $op
   *   Type of operation being performed.
   */
  public function updateSchoolSeatCount($data, $op) {
    try {
      $language = $this->configFactory->get('rte_mis_lottery.settings')->get('field_default_options.languages') ?? [];
      $language = array_keys($language);
      if (((!empty($data['school_id']) && !empty($data['entry_class']) && !empty($data['school_name']) && !empty($data['school_name']) && $op == 'insert') || $op == 'update') && count(array_intersect_key(array_flip($language), $data)) === count($language)) {
        $data['created'] = time();
        $result = $this->database->merge('rte_mis_lottery_school_seats_status')
          ->insertFields($data)
          ->updateFields(
        $data
        )
          ->keys([
            'school_id' => $data['school_id'],
            'entry_class' => $data['entry_class'],
          ])->execute();
        return $result;
      }
      return NULL;
    }
    catch (\Exception $e) {
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
   * Create `allotted_students_details` paragraph provided by data.
   *
   * @param array $data
   *   Array of data that will be used to create paragraph.
   */
  public function createStudentAllocationParagraph($data) {
    if (!empty($data['entry_class']) && !empty($data['medium']) && !empty($data['student_id'])) {
      $paragraph = \Drupal::entityTypeManager()->getStorage('paragraph')->create([
        'type' => 'allotted_students_details',
        'field_entry_class' => [$data['entry_class']],
        'field_medium' => [$data['medium']],
        'field_student_id' => [
          'target_id' => $data['student_id'],
        ],
      ]);
      if ($paragraph instanceof ParagraphInterface) {
        $paragraph->save();
        return [
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ];
      }
    }
    return FALSE;
  }

}
