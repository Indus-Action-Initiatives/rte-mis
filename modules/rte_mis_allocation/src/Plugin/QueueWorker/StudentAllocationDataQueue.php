<?php

namespace Drupal\rte_mis_allocation\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process a queue for creating allocation mini_node.
 *
 * @QueueWorker(
 *   id = "student_allocation",
 *   title = @Translation("Student Allocation"),
 *   cron = {"time" = 160}
 * )
 */
class StudentAllocationDataQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new LocaleTranslation object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('rte_mis_allocation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    try {
      if (!empty($data['field_academic_year_allocation']) && !empty($data['field_entry_class_for_allocation']) && !empty($data['field_medium']) && !empty($data['field_school']) && !empty($data['field_student'])) {
        $this->entityTypeManager->getStorage('mini_node')->create($data)->save();
      }
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      $this->logger->error($this->t('Failed to create allocation Mini Node. Here are details :-
        Academic Year: @academic_year,
        Student Mini Node Id: @student_id,
        School Mini Node Id: @school_id,
        Medium: @medium,
        Entry Class: @entry_class
       ', [
         '@academic_year' => $data['field_academic_year_allocation'],
         '@student_id' => $data['field_student'],
         '@school_id' => $data['field_school'],
         '@medium' => $data['field_medium'],
         '@entry_class' => $data['field_entry_class_for_allocation'],
       ]));
    }

  }

}
