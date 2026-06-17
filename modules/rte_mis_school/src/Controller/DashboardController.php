<?php

namespace Drupal\rte_mis_school\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dashboard controller for School Admin.
 */
class DashboardController extends ControllerBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a DashboardController object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * Builds the dashboard.
   */
  public function build() {
    $school_id = $this->getCurrentSchoolId();
    $current_year = $this->getCurrentAcademicYear();
    $years = $this->getLastThreeYears();
    
    // Check if ECK mini_node entity type exists
    $has_mini_node = $this->checkMiniNodeExists();
    
    // Get system stats
    $schools_count = $this->getSchoolsCount();
    
    // Prepare demo message if needed
    $demo_message = '';
    $system_status = [];
    
    if (!$has_mini_node) {
      $demo_message = $this->t('ECK entity types not configured yet. Dashboard showing demo structure.');
      $system_status[] = [
        'type' => 'warning',
        'message' => $this->t('To populate data: Configure ECK entity types (mini_node) with bundles: student_application, allocation, notification, reimbursement_claim'),
      ];
    }
    
    if (!$school_id) {
      $system_status[] = [
        'type' => 'info',
        'message' => $this->t('No school associated with your account. Showing demo data with school ID: @id', ['@id' => 8]),
      ];
    }

    return [
      '#theme' => 'rte_mis_school_dashboard',
      '#demo_message' => $demo_message,
      '#system_status' => $system_status,
      '#school_id' => $school_id,
      '#current_year' => $current_year,
      '#schools_count' => $schools_count,
      '#has_mini_node' => $has_mini_node,
      '#notifications' => $this->getNotifications(),
      '#preference_count' => $this->getPreferenceCount($school_id, $current_year),
      '#allotted_count' => $this->getAllottedCount($school_id, $current_year),
      '#admitted_count' => $this->getAdmittedCount($school_id, $current_year),
      '#seat_status' => $this->getSeatStatus($school_id, $years),
      '#claims_status' => $this->getClaimsStatus($school_id, $years),
      '#years' => $years,
      '#attached' => [
        'library' => ['rte_mis_school/dashboard'],
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['rte_mis_school:dashboard'],
        'max-age' => 300,
      ],
    ];
  }

  /**
   * Check if mini_node entity type exists.
   */
  protected function checkMiniNodeExists() {
    try {
      $definition = $this->entityTypeManager->getDefinition('mini_node', FALSE);
      return $definition !== NULL;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Get current school ID for logged-in school admin.
   */
  protected function getCurrentSchoolId() {
    // Using demo school ID for testing
    return 8;
  }

  /**
   * Get current academic year.
   */
  protected function getCurrentAcademicYear() {
    $month = (int) date('n');
    $year = (int) date('Y');
    return $month >= 4 ? $year . '-' . ($year + 1) : ($year - 1) . '-' . $year;
  }

  /**
   * Get last three academic years.
   */
  protected function getLastThreeYears() {
    $month = (int) date('n');
    $year = (int) date('Y');
    $start = $month >= 4 ? $year : $year - 1;
    
    return [
      $start . '-' . ($start + 1),
      ($start - 1) . '-' . $start,
      ($start - 2) . '-' . ($start - 1),
    ];
  }

  /**
   * Get count of schools in system.
   */
  protected function getSchoolsCount() {
    try {
      return (int) $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->getQuery()
        ->accessCheck(TRUE)
        ->condition('vid', 'school')
        ->count()
        ->execute();
    }
    catch (\Exception $e) {
      \Drupal::logger('rte_mis_school')->error('Error counting schools: @message', ['@message' => $e->getMessage()]);
      return 0;
    }
  }

  /**
   * Get demo notifications.
   */
  protected function getDemoNotifications() {
    return [
      [
        'title' => $this->t('Admission process for @year has started', ['@year' => $this->getCurrentAcademicYear()]),
        'created' => strtotime('-2 days'),
      ],
      [
        'title' => $this->t('Submit reimbursement claims by end of month'),
        'created' => strtotime('-5 days'),
      ],
      [
        'title' => $this->t('New guidelines for student verification'),
        'created' => strtotime('-1 week'),
      ],
    ];
  }

  /**
   * Check if field exists on a bundle.
   */
  protected function fieldExists($entity_type, $bundle, $field_name) {
    try {
      $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
      return isset($fields[$field_name]);
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Get latest notifications - with graceful fallback.
   */
  protected function getNotifications() {
    if (!$this->checkMiniNodeExists()) {
      return $this->getDemoNotifications();
    }

    try {
      $query = $this->entityTypeManager->getStorage('mini_node')->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'notification')
        ->sort('created', 'DESC')
        ->range(0, 5);
      
      // Only add status condition if field exists
      if ($this->fieldExists('mini_node', 'notification', 'status')) {
        $query->condition('status', 1);
      }
      
      $ids = $query->execute();

      if (empty($ids)) {
        return $this->getDemoNotifications();
      }

      $notifications = [];
      foreach ($this->entityTypeManager->getStorage('mini_node')->loadMultiple($ids) as $entity) {
        $notifications[] = [
          'title' => $entity->label(),
          'created' => $entity->getCreatedTime(),
        ];
      }
      return $notifications;
    }
    catch (\Exception $e) {
      \Drupal::logger('rte_mis_school')->error('Error fetching notifications: @message', ['@message' => $e->getMessage()]);
      return $this->getDemoNotifications();
    }
  }

  /**
   * Get preference count - with field existence check.
   */
  protected function getPreferenceCount($school_id, $year) {
    if (!$school_id || !$this->checkMiniNodeExists()) {
      return 0;
    }

    try {
      // Check if required fields exist
      if (!$this->fieldExists('mini_node', 'student_application', 'field_preference_schools') || 
          !$this->fieldExists('mini_node', 'student_application', 'field_academic_year')) {
        return 0;
      }

      return (int) $this->entityTypeManager->getStorage('mini_node')->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'student_application')
        ->condition('field_preference_schools.target_id', $school_id)
        ->condition('field_academic_year', $year)
        ->count()
        ->execute();
    }
    catch (\Exception $e) {
      \Drupal::logger('rte_mis_school')->warning('Error fetching preference count: @message', ['@message' => $e->getMessage()]);
      return 0;
    }
  }

  /**
   * Get allotted count - with field existence check.
   */
  protected function getAllottedCount($school_id, $year) {
    if (!$school_id || !$this->checkMiniNodeExists()) {
      return 0;
    }

    try {
      // Check if required fields exist
      if (!$this->fieldExists('mini_node', 'allocation', 'field_school') || 
          !$this->fieldExists('mini_node', 'allocation', 'field_academic_year_allocation')) {
        return 0;
      }

      $query = $this->entityTypeManager->getStorage('mini_node')->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'allocation')
        ->condition('field_school.target_id', $school_id)
        ->condition('field_academic_year_allocation', $year);
      
      // Only add status condition if field exists
      if ($this->fieldExists('mini_node', 'allocation', 'status')) {
        $query->condition('status', 1);
      }
      
      return (int) $query->count()->execute();
    }
    catch (\Exception $e) {
      \Drupal::logger('rte_mis_school')->warning('Error fetching allotted count: @message', ['@message' => $e->getMessage()]);
      return 0;
    }
  }

  /**
   * Get admitted count - with field existence check.
   */
  protected function getAdmittedCount($school_id, $year) {
    if (!$school_id || !$this->checkMiniNodeExists()) {
      return 0;
    }

    try {
      // Check if required fields exist
      if (!$this->fieldExists('mini_node', 'allocation', 'field_school') || 
          !$this->fieldExists('mini_node', 'allocation', 'field_academic_year_allocation') ||
          !$this->fieldExists('mini_node', 'allocation', 'field_student_allocation_status')) {
        return 0;
      }

      $query = $this->entityTypeManager->getStorage('mini_node')->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'allocation')
        ->condition('field_school.target_id', $school_id)
        ->condition('field_academic_year_allocation', $year)
        ->condition('field_student_allocation_status.to_sid', 'student_admission_workflow_admitted');
      
      // Only add status condition if field exists
      if ($this->fieldExists('mini_node', 'allocation', 'status')) {
        $query->condition('status', 1);
      }
      
      return (int) $query->count()->execute();
    }
    catch (\Exception $e) {
      \Drupal::logger('rte_mis_school')->warning('Error fetching admitted count: @message', ['@message' => $e->getMessage()]);
      return 0;
    }
  }

  /**
   * Get seat allocation status.
   */
  protected function getSeatStatus($school_id, $years) {
    $status = [];
    foreach ($years as $year) {
      $preferred = $this->getPreferenceCount($school_id, $year);
      $allotted = $this->getAllottedCount($school_id, $year);
      $admitted = $this->getAdmittedCount($school_id, $year);
      
      $status[$year] = [
        'preferred' => $preferred,
        'allotted' => $allotted,
        'dropped' => max(0, $allotted - $admitted),
        'admitted' => $admitted,
      ];
    }
    return $status;
  }

  /**
   * Get reimbursement claims status - with field existence check.
   */
  protected function getClaimsStatus($school_id, $years) {
    $status = [];
    
    foreach ($years as $year) {
      $admitted = $this->getAdmittedCount($school_id, $year);
      
      try {
        if (!$this->checkMiniNodeExists()) {
          $status[$year] = [
            'admitted' => 0,
            'claim_raised' => '0.00',
            'claims_approved' => 'N/A',
            'receipt_status' => '0.00',
          ];
          continue;
        }

        // Check if required fields exist
        if (!$this->fieldExists('mini_node', 'reimbursement_claim', 'field_school') || 
            !$this->fieldExists('mini_node', 'reimbursement_claim', 'field_academic_year')) {
          $status[$year] = [
            'admitted' => $admitted,
            'claim_raised' => '0.00',
            'claims_approved' => 'N/A',
            'receipt_status' => '0.00',
          ];
          continue;
        }

        $claim_ids = $this->entityTypeManager->getStorage('mini_node')->getQuery()
          ->accessCheck(TRUE)
          ->condition('type', 'reimbursement_claim')
          ->condition('field_school.target_id', $school_id)
          ->condition('field_academic_year', $year)
          ->execute();

        $claim_raised = 0.0;
        $claims_approved = 'N/A';
        $receipt_status = 0.0;

        if (!empty($claim_ids)) {
          $claims = $this->entityTypeManager->getStorage('mini_node')->loadMultiple($claim_ids);
          
          foreach ($claims as $claim) {
            if ($claim->hasField('field_amount_raised') && !$claim->get('field_amount_raised')->isEmpty()) {
              $claim_raised += (float) $claim->get('field_amount_raised')->value;
            }
            
            if ($claim->hasField('field_amount_received') && !$claim->get('field_amount_received')->isEmpty()) {
              $receipt_status += (float) $claim->get('field_amount_received')->value;
            }
            
            if ($claim->hasField('field_status') && $claim->get('field_status')->value === 'approved') {
              if ($claim->hasField('field_approved_by') && !$claim->get('field_approved_by')->isEmpty()) {
                $claims_approved = $claim->get('field_approved_by')->value;
              }
              else {
                $claims_approved = 'Approved';
              }
            }
          }
        }

        $status[$year] = [
          'admitted' => $admitted,
          'claim_raised' => number_format($claim_raised, 2),
          'claims_approved' => $claims_approved,
          'receipt_status' => number_format($receipt_status, 2),
        ];
      }
      catch (\Exception $e) {
        \Drupal::logger('rte_mis_school')->warning('Error fetching claims for year @year: @message', [
          '@year' => $year,
          '@message' => $e->getMessage(),
        ]);
        
        $status[$year] = [
          'admitted' => $admitted,
          'claim_raised' => '0.00',
          'claims_approved' => 'N/A',
          'receipt_status' => '0.00',
        ];
      }
    }
    
    return $status;
  }

}
