<?php

namespace Drupal\rte_mis_school\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Seat Allotment Status Table' block.
 *
 * @Block(
 *   id = "seat_allotment_status_table_block",
 *   admin_label = @Translation("Seat Allotment Status Table Block")
 * )
 */
class SeatAllotmentStatusTableBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected $entityTypeManager;

  protected $currentUser;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  public function build() {
    $school_id = $this->getCurrentSchoolId();

    if (!$school_id) {
      return ['#markup' => $this->t('No school associated with your account.')];
    }

    $years = $this->getLastThreeYears();
    $seat_status = $this->getSeatStatus($school_id, $years);

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['seat-allotment']],
      '#attached' => [
        'library' => [
          'rte_mis_school/dashboard',
        ],
      ],
    ];

    $build['title'] = ['#markup' => '<h2>' . $this->t('Seat Allotment Status (Last 3 Years)') . '</h2>'];

    $header = [
      $this->t('Academic Year'),
      $this->t('Preferred'),
      $this->t('Allotted'),
      $this->t('Dropped'),
      $this->t('Admitted'),
    ];

    $rows = [];
    foreach ($years as $year) {
      $rows[] = [
        $year,
        $seat_status[$year]['preferred'] ?? 0,
        $seat_status[$year]['allotted'] ?? 0,
        $seat_status[$year]['dropped'] ?? 0,
        $seat_status[$year]['admitted'] ?? 0,
      ];
    }

    $build['table'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No data available'),
      '#attributes' => ['class' => ['seat-status-table']],
    ];

    return $build;
  }

  protected function getCurrentSchoolId() {
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $school = $user->get('field_school_details')->entity;
    return $school ? $school->id() : NULL;
  }

  protected function getLastThreeYears() {
    $current_year = (int) date('Y');
    return [
      $current_year . ' - ' . ($current_year + 1),
      ($current_year - 1) . ' - ' . $current_year,
      ($current_year - 2) . ' - ' . ($current_year - 1),
    ];
  }

  protected function getSeatStatus($school_id, $years) {
    $status = [];
    foreach ($years as $year) {
      $preferred = $this->entityTypeManager->getStorage('mini_node')->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'student_application')
        ->condition('field_preference_schools.target_id', $school_id)
        ->condition('field_academic_year', $year)
        ->count()
        ->execute();

      $allotted = $this->entityTypeManager->getStorage('mini_node')->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'allotment')
        ->condition('field_allotted_school.target_id', $school_id)
        ->condition('field_academic_year', $year)
        ->count()
        ->execute();

      $admitted = $this->entityTypeManager->getStorage('mini_node')->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'admission')
        ->condition('field_admitted_school.target_id', $school_id)
        ->condition('field_academic_year', $year)
        ->count()
        ->execute();

      $dropped = $allotted - $admitted;

      $status[$year] = [
        'preferred' => $preferred,
        'allotted' => $allotted,
        'dropped' => $dropped,
        'admitted' => $admitted,
      ];
    }
    return $status;
  }

}