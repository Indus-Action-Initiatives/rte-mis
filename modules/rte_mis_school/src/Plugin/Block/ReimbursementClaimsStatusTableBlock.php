<?php

namespace Drupal\rte_mis_school\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Reimbursement Claims Status Table' block.
 *
 * @Block(
 *   id = "reimbursement_claims_status_table_block",
 *   admin_label = @Translation("Reimbursement Claims Status Table Block")
 * )
 */
class ReimbursementClaimsStatusTableBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
    $claims_status = $this->getClaimsStatus($school_id, $years);

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['reimbursement-claims']],
      '#attached' => [
        'library' => [
          'rte_mis_school/dashboard',
        ],
      ],
    ];

    $build['title'] = ['#markup' => '<h2>' . $this->t('Reimbursement Claims Status (Last 3 Years)') . '</h2>'];

    $header = [
      $this->t('Academic Year'),
      $this->t('Admitted Students'),
      $this->t('Claim Raised (Amount)'),
      $this->t('Claims Approved (Last Authority)'),
      $this->t('Receipt Status (Amount Received)'),
    ];

    $rows = [];
    foreach ($years as $year) {
      $rows[] = [
        $year,
        $claims_status[$year]['admitted'] ?? 0,
        $claims_status[$year]['claim_raised'] ?? 0,
        $claims_status[$year]['claims_approved'] ?? 'N/A',
        $claims_status[$year]['receipt_status'] ?? 0,
      ];
    }

    $build['table'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No claim data available'),
      '#attributes' => ['class' => ['claims-status-table']],
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

  protected function getClaimsStatus($school_id, $years) {
    $status = [];
    foreach ($years as $year) {
      $admitted = $this->entityTypeManager->getStorage('mini_node')->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'admission')
        ->condition('field_admitted_school.target_id', $school_id)
        ->condition('field_academic_year', $year)
        ->count()
        ->execute();

      $claim_ids = $this->entityTypeManager->getStorage('mini_node')->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'reimbursement_claim')
        ->condition('field_school.target_id', $school_id)
        ->condition('field_academic_year', $year)
        ->execute();
      $claim_raised = 0;
      $last_approved_by = 'N/A';
      $received = 0;
      if ($claim_ids) {
        $claims = $this->entityTypeManager->getStorage('mini_node')->loadMultiple($claim_ids);
        foreach ($claims as $claim) {
          $claim_raised += (float) $claim->get('field_amount_raised')->value;
          $received += (float) $claim->get('field_amount_received')->value;
          if ($claim->get('field_status')->value === 'approved') {
            $last_approved_by = $claim->get('field_approved_by')->value;
          }
        }
      }

      $status[$year] = [
        'admitted' => $admitted,
        'claim_raised' => $claim_raised,
        'claims_approved' => $last_approved_by,
        'receipt_status' => $received,
      ];
    }
    return $status;
  }

}