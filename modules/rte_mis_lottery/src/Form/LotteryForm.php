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
    $row = $this->keyValueExpirableFactory->get('rte_mis_lottery')->get('student-list', []);

    $form['student_count'] = [
      '#type' => 'label',
      '#title' => $this->t('Total eligible Student: @count', ['@count' => count($row)]),
      '#access' => !empty($row) ? TRUE : FALSE,
    ];

    $form['student'] = [
      '#type' => 'table',
      '#header' => [
        'student_name' => $this->t('Student Name'),
        'application_number' => $this->t('Application Number'),
        'mobile_number' => $this->t('Mobile Number'),
      ],
      '#empty' => $this->t('No Student to displays'),
      '#rows' => array_slice($row, 0, 5000),
    ];

    $form['randomize'] = [
      '#type' => 'submit',
      '#value' => empty($row) ? $this->t('Fetch and Randomize Students') : $this->t('Randomize Students'),
      '#submit' => ['::rteMisLotteryFetchStudent'],
      '#limit_validation_errors' => [],
    ];

    $form['clear_student_list'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear List'),
      '#submit' => ['::rteMisLotteryClearStudentList'],
      '#limit_validation_errors' => [],
      '#access' => !empty($row) ? TRUE : FALSE,
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
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $row = $this->keyValueExpirableFactory->get('rte_mis_lottery')->get('student-list', []);
    if (empty($row)) {
      $form_state->setErrorByName('student', $this->t('Student List cannot be empty.'));
    }
  }

  /**
   * Clear the student list from keyvalue.expirable store.
   */
  public function rteMisLotteryClearStudentList(array &$form, FormStateInterface $form_state) {
    $this->keyValueExpirableFactory->get('rte_mis_lottery')->delete('student-list');
  }

  /**
   * Fetch the eligible student and randomize it.
   */
  public function rteMisLotteryFetchStudent(array &$form, FormStateInterface $form_state) {
    $operations = [];
    // Define the number of items to process per batch.
    $batch_size = 100;
    $mini_node_storage = $this->entityTypeManager->getStorage('mini_node');
    // @todo add check if student is not already enrolled in schools.
    // this can be used if second round of lottery is select.
    $query = $mini_node_storage->getQuery();
    $result = $query->condition('status', 1)
      ->condition('field_academic_year', _rte_mis_core_get_current_academic_year())
      ->condition('field_student_verification', 'student_workflow_approved')
      ->condition('type', 'student_details')
      ->accessCheck(FALSE)
      ->execute();
    shuffle($result);
    // Split the result into smaller batches.
    $chunks = array_chunk($result, $batch_size);
    foreach ($chunks as $chunk) {
      $operations[] = ['\Drupal\rte_mis_lottery\Batch\RandomizeStudent::rteMisLotteryProcessStudent', [$chunk]];
    }
    $batch = [
      'title' => $this->t('Randomizing Students'),
      'operations' => $operations,
      'init_message' => $this->t('Starting Randomizing Student.'),
      'progressive' => TRUE,
      'progress_message' => $this->t('Processed @current out of @total. Time elapsed: @elapsed, estimated time remaining: @estimate.'),
      'finished' => '\Drupal\rte_mis_lottery\Batch\RandomizeStudent::rteMisLotteryBatchFinished',
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

}
