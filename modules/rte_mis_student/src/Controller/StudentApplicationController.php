<?php

namespace Drupal\rte_mis_student\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
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
   * Creates a StudentApplicationController instance.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  public function __construct(Request $request) {
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')->getCurrentRequest(),
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
    $student_details = $this->entityTypeManager()->getStorage('mini_node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'student_details')
      ->condition('field_mobile_number', $phoneNumber)
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

}
