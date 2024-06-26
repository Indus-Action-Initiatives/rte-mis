<?php

namespace Drupal\rte_mis_lottery\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
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
   * Constructs a LotteryForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $key_value_expirable_factory
   *   The keyvalue expirable factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, KeyValueExpirableFactoryInterface $key_value_expirable_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->keyValueExpirableFactory = $key_value_expirable_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('keyvalue.expirable')
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
        'id' => $this->t('Id'),
        'student_name' => $this->t('Student Name'),
        'mobile_number' => $this->t('Mobile Number'),
        'application_number' => $this->t('Application Number'),
        'location' => $this->t('Location ID'),
      ],
      '#empty' => $this->t('No Student to displays'),
      '#rows' => array_slice($studentData, 0, 5000),
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
    // Fetch the student entity ids, shuffle and break them into the chunks.
    $student_details_result = $this->getStudentEntityId();
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

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @todo Send the data to API.
    $this->messenger()->addMessage($this->t('Lottery Started'));
  }

  /**
   * Fetch the approved student entity ids.
   *
   * @param string $op
   *   Operation name.
   */
  protected function getStudentEntityId($op = '') {
    // @todo add check if student is not already enrolled in schools.
    // this can be used if second round of lottery is select.
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
