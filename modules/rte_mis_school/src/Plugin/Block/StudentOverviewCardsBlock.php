<?php

namespace Drupal\rte_mis_school\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Student Overview Cards' block.
 *
 * @Block(
 *   id = "student_overview_cards_block",
 *   admin_label = @Translation("Student Overview Cards Block")
 * )
 */
class StudentOverviewCardsBlock extends BlockBase implements ContainerFactoryPluginInterface {

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

    $current_year = $this->getCurrentAcademicYear();

    $preference_count = $this->getPreferenceCount($school_id, $current_year);
    $allotted_count = $this->getAllottedCount($school_id, $current_year);
    $admitted_count = $this->getAdmittedCount($school_id, $current_year);

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['student-overview']],
      '#attached' => [
        'library' => [
          'rte_mis_school/dashboard',
        ],
      ],
    ];

    $build['title'] = ['#markup' => '<h2>' . $this->t('Student Overview') . '</h2>'];

    $build['cards'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['cards-container']],
    ];

    $build['cards']['preferred'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card']],
      'title' => ['#markup' => '<h3>' . $this->t('Total Preferred Students') . '</h3>'],
      'value' => ['#markup' => '<p>' . $preference_count . '</p>'],
    ];

    $build['cards']['allotted'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card']],
      'title' => ['#markup' => '<h3>' . $this->t('Total Allotted Students') . '</h3>'],
      'value' => ['#markup' => '<p>' . $allotted_count . '</p>'],
    ];

    $build['cards']['admitted'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card']],
      'title' => ['#markup' => '<h3>' . $this->t('Total Admitted Students') . '</h3>'],
      'value' => ['#markup' => '<p>' . $admitted_count . '</p>'],
    ];

    return $build;
  }

  protected function getCurrentSchoolId() {
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $school = $user->get('field_school_details')->entity;
    return $school ? $school->id() : NULL;
  }

  protected function getCurrentAcademicYear() {
    return _rte_mis_core_get_current_academic_year();
  }

  protected function getPreferenceCount($school_id, $year) {
    $query = $this->entityTypeManager->getStorage('mini_node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'student_application')
      ->condition('field_preference_schools.target_id', $school_id)
      ->condition('field_academic_year', $year);
    return $query->count()->execute();
  }

  protected function getAllottedCount($school_id, $year) {
    $query = $this->entityTypeManager->getStorage('mini_node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'allotment')
      ->condition('field_allotted_school.target_id', $school_id)
      ->condition('field_academic_year', $year);
    return $query->count()->execute();
  }

  protected function getAdmittedCount($school_id, $year) {
    $query = $this->entityTypeManager->getStorage('mini_node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'admission')
      ->condition('field_admitted_school.target_id', $school_id)
      ->condition('field_academic_year', $year);
    return $query->count()->execute();
  }

}