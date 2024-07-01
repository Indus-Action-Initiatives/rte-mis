<?php

namespace Drupal\rte_mis_lottery\Plugin\rest\resource;

use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource for handling lottery data.
 *
 * @RestResource(
 *   id = "lottery_data_resource",
 *   label = @Translation("Lottery Data Resource"),
 *   uri_paths = {
 *     "create" = "/api/v1/lottery-data"
 *   }
 * )
 */
class LotteryDataResource extends ResourceBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The queue factory service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    FileSystemInterface $file_system,
    QueueFactory $queue_factory,
    StateInterface $state,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->fileSystem = $file_system;
    $this->queueFactory = $queue_factory;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('example_rest'),
      $container->get('current_user'),
      $container->get('file_system'),
      $container->get('queue'),
      $container->get('state')
    );
  }

  /**
   * Responds to POST requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the status.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throws exception expected.
   */
  public function post(Request $request) {

    $current_user_role = $this->currentUser->getRoles();
    if (!in_array('state_admin', $current_user_role)) {
      throw new AccessDeniedHttpException('Access denied');
    }

    // Get the request content.
    $data = json_decode($request->getContent(), TRUE);

    // Validate the data.
    if (empty($data) || !isset($data['students']) || !isset($data['schools'])) {
      return new ResourceResponse(['error' => 'Invalid data. Expected "students" and "schools" keys.'], 400);
    }

    // Extract the data from student and school.
    $student_data = $data['students'];
    $school_data = $data['schools'];

    // Validate the student and school data for any empty fields.
    $invalid_students = [];
    foreach ($student_data as $student_id => $student) {
      if (empty($student['application_id']) || empty($student['name']) || empty($student['parent_name']) || empty($student['location']) ||empty($student['preference'])) {
        $invalid_students[$student_id] = $student;
      }
      else {
        foreach ($student['preference'] as $preference) {
          if (empty($preference['school_id']) || empty($preference['medium']) || empty($preference['entry_class'])) {
            $invalid_students[$student_id] = $student;
            break;
          }
        }
      }
    }

    $invalid_schools = [];
    foreach ($school_data as $school_id => $school) {
      if (empty($school['udise_code']) || empty($school['name']) || empty($school['location']) || empty($school['entry_class'])) {
        $invalid_schools[$school_id] = $school;
      }
      else {
        foreach ($school['entry_class'] as $entry_class) {
          if (empty($entry_class['rte_seat'])) {
            $invalid_schools[$school_id] = $school;
            break;
          }
        }
      }
    }

    if (!empty($invalid_students) || !empty($invalid_schools)) {
      return new ResourceResponse([
        'error' => 'Invalid data found.',
        'invalid_students' => $invalid_students,
        'invalid_schools' => $invalid_schools,
      ], 400);
    }

    // Check if student_data is an array and not empty.
    if (!is_array($student_data) || empty($student_data)) {
      return new ResourceResponse(['error' => 'Invalid student data. Expected a non-empty array.'], 400);
    }

    // Check if school_data is valid.
    if (empty($school_data)) {
      return new ResourceResponse(['error' => 'Invalid school data.'], 400);
    }

    // Retrieve and increment the file number from the state system.
    $file_number = $this->state->get('lottery_data_file_number', 0);
    $file_number++;
    $this->state->set('lottery_data_file_number', $file_number);

    $filename = 'school_data_' . $file_number . '.json';
    $file_uri = '../lottery_files/' . $filename;

    // Check if the queue already has items.
    $queue = $this->queueFactory->get('student_data_lottery_queue_cron');
    if ($queue->numberOfItems() > 0) {
      return new ResourceResponse(['error' => 'Lottery already in progress.'], 400);
    }

    try {
      // Add student data to the queue in batches.
      $batchSize = 100;
      $chunks = array_chunk($student_data, $batchSize);
      foreach ($chunks as $chunk) {
        $queue->createItem($chunk);
      }
      // Save the school data to a file.
      $this->fileSystem->saveData(json_encode($school_data), $file_uri, FileSystemInterface::EXISTS_REPLACE);
      return new ResourceResponse(['message' => 'Lottery Started']);
    }
    catch (FileException $e) {
      return new ResourceResponse(['error' => 'An issue occurred with the school list. Please contact site administrator.'], 500);
    }
    catch (\Exception $e) {
      return new ResourceResponse(['error' => 'An unexpected error occurred.'], 500);
    }
  }

}
