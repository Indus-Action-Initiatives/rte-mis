<?php

namespace Drupal\rte_mis_student\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\mobile_number\Exception\MobileNumberException;
use Drupal\rte_mis_student\Services\MobileOtpServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * This class is controller to student application.
 */
class StudentApplicationController extends ControllerBase {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Mobile OTP service.
   *
   * @var Drupal\rte_mis_student\Services\MobileOtpServiceInterface
   */
  protected $mobileOtpService;

  /**
   * Creates a StudentApplicationController instance.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\rte_mis_student\Services\MobileOtpServiceInterface $mobile_otp_service
   *   Mobile OTP service.
   */
  public function __construct(Request $request, MobileOtpServiceInterface $mobile_otp_service) {
    $this->request = $request;
    $this->mobileOtpService = $mobile_otp_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('rte_mis_student.mobile_otp_service')
    );
  }

  /**
   * Return the element after the student login.
   */
  public function build() {
    $build = $student_details = [];

    $studentDashboardUrl = Url::fromRoute('rte_mis_student.controller.student_application')->toString();
    $code = $this->request->query->get('code', NULL);
    $phoneNumber = $this->request->cookies->get('student-phone', NULL);
    // Get the current academic year.
    $current_academic_year = _rte_mis_core_get_current_academic_year();
    $student_details = $this->entityTypeManager()->getStorage('mini_node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'student_details')
      ->condition('field_mobile_number', $phoneNumber)
      ->condition('field_academic_year', $current_academic_year)
      ->execute();
    if (!empty($student_details)) {
      $student_details = array_values($student_details);
      $student_details = implode('+', $student_details);
    }
    $build['student_application_view'] = [
      '#type' => 'view',
      '#name' => 'student_applications',
      '#display_id' => 'block_1',
      '#arguments' => !empty($student_details) ? [$student_details] : '',
    ];

    $build['add_new_student_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Students'),
      '#url' => Url::fromRoute('eck.entity.add', [
        'eck_entity_bundle' => 'student_details',
        'eck_entity_type' => 'mini_node',
      ], [
        'query' => [
          'destination' => "$studentDashboardUrl?code=$code",
        ],
      ]),
      '#attributes' => [
        'class' => ['primary', 'button'],
      ],
    ];

    return $build;
  }

  /**
   * Create listing for student application status.
   */
  public function getApplicationListing() {
    $build = $result = [];
    $check = FALSE;
    $option = $this->request->query->get('search-by') ?? NULL;
    $value = $this->request->query->get('value') ?? NULL;
    if (isset($option) && !empty(trim($option)) && isset($value) && !empty(trim($value))) {
      // Get the current academic year.
      $current_academic_year = _rte_mis_core_get_current_academic_year();
      $query = $this->entityTypeManager()->getStorage('mini_node')->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'student_details')
        ->condition('field_academic_year', $current_academic_year);
      if ($option == 'application_number') {
        $check = TRUE;
        $query->condition('field_student_application_number', $value);
      }
      elseif ($option == 'phone_number') {
        try {
          $phoneNumber = $this->mobileOtpService->testMobileNumber($value, 'IN');
          $number = $this->mobileOtpService->getCallableNumber($phoneNumber);
          $check = TRUE;
          $query->condition('field_mobile_number', $number);
        }
        catch (MobileNumberException $e) {
          switch ($e->getCode()) {
            case MobileNumberException::ERROR_NO_NUMBER:
              $this->messenger()->addError($this->t('Phone number is required.'));
              break;

            case MobileNumberException::ERROR_INVALID_NUMBER:
            case MobileNumberException::ERROR_WRONG_TYPE:
              $this->messenger()->addError($this->t('The phone number is not a valid mobile number'));
              break;

            case MobileNumberException::ERROR_WRONG_COUNTRY:
              $this->messenger()->addError($this->t('The country value is not valid'));
              break;
          }
        }

      }
      if ($check) {
        $result = $query->execute();
        if (!empty($result)) {
          $result = array_values($result);
          $result = implode('+', $result);
        }
      }
    }

    $build['student_application_view'] = [
      '#type' => 'view',
      '#name' => 'student_applications',
      '#display_id' => 'block_2',
      '#arguments' => !empty($result) ? [$result] : '',
    ];
    return $build;
  }

}
