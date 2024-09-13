<?php

namespace Drupal\rte_mis_lottery\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rte_mis_lottery\Services\RteLotteryHelper;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirm form for sending lottery result to student through sms.
 */
class SendSmsConfirmForm extends ConfirmFormBase {

  /**
   * Rte Lottery service.
   *
   * @var \Drupal\rte_mis_lottery\Services
   */
  protected $rteLotteryHelper;

  /**
   * Construct of Block Class service.
   */
  public function __construct(RteLotteryHelper $rte_lottery_helper) {
    $this->rteLotteryHelper = $rte_lottery_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('rte_mis_lottery.lottery_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Send SMS');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Are you sure you want to send sms to students? This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Send');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('view.lottery_results.page_1');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'send_sms_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch_size = 50;
    // Get the exposed filters values from the url.
    $views_exposed_values = $this->getRequest()->query->all();
    // Get the exposed filter value from the url, then add it to the view
    // and get the results.
    $view_id = 'lottery_results';
    $view = Views::getView($view_id);
    $view->setDisplay('page_1');
    $view->setExposedInput([
      'student_name' => $views_exposed_values['student_name'],
      'application_number' => $views_exposed_values['application_number'],
      'mobile_number' => $views_exposed_values['mobile_number'],
      'allocation_status' => $views_exposed_values['allocation_status'],
      'lottery_id' => $views_exposed_values['lottery_id'],
    ]);
    $view->execute();
    $result = $view->result;
    $student_ids = [];
    // Store the student ids in an array.
    foreach ($result as $row) {
      $student_ids[] = $row->rte_mis_lottery_results_student_id ?? '';
    }
    $students = $this->rteLotteryHelper->getLotteryResult('internal', _rte_mis_core_get_current_academic_year(), $student_ids, $views_exposed_values['lottery_id']);
    if (!empty($students)) {
      $chunks = array_chunk($students, $batch_size);
      foreach ($chunks as $chunk) {
        $operations[] = ['\Drupal\rte_mis_lottery\Batch\SendSmsBatch::sendSms', [$chunk]];
      }
      // Prepare the batch data.
      $batch = [
        'title' => $this->t('Sending SMS To Students'),
        'operations' => $operations,
        // 'init_message' => $this->t('Starting Randomizing Student.'),
        'progressive' => TRUE,
        'progress_message' => $this->t('Processed @current out of @total. Time elapsed: @elapsed, estimated time remaining: @estimate.'),
        'finished' => '\Drupal\rte_mis_lottery\Batch\SendSmsBatch::rteMisLotteryBatchFinished',
      ];

      batch_set($batch);
      $form_state->setRedirect('view.lottery_results.page_1');
    }
  }

}
