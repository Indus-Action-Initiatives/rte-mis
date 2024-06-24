<?php

namespace Drupal\rte_mis_lottery\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Queue\QueueFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for handling user data POST requests.
 */
class DataController extends ControllerBase {

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
   * Constructs a UserDataController object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory service.
   */
  public function __construct(FileSystemInterface $file_system, QueueFactory $queue_factory) {
    $this->fileSystem = $file_system;
    $this->queueFactory = $queue_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('queue')
    );
  }

  /**
   * Handles the POST request to save user data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function handlePost(Request $request) {
    // Get the request content.
    $data = json_decode($request->getContent(), TRUE);

    // Validate the data.
    if (empty($data) || !isset($data['student']) || !isset($data['school'])) {
      return new JsonResponse(['error' => 'Invalid data. Expected "student" and "school" keys.'], 400);
    }

    // Extract the data from student and school.
    $student_data = $data['student'];
    $school_data = $data['school'];

    // Check if student_data is an array and not empty.
    if (!is_array($student_data) || empty($student_data)) {
      return new JsonResponse(['error' => 'Invalid student data. Expected a non-empty array.'], 400);
    }

    // Check if school_data is valid.
    if (empty($school_data)) {
      return new JsonResponse(['error' => 'Invalid school data.'], 400);
    }

    // Generate a unique filename.
    $filename = 'school_data_' . date('YmdHis') . '.json';

    // Define the file URI (../Files/).
    $file_uri = '../Files/' . $filename;

    // Check if the queue already has items.
    $queue = $this->queueFactory->get('student_data_lottery_queue_cron');
    if ($queue->numberOfItems() > 0) {
      return new JsonResponse(['error' => 'Queue already in progress.'], 400);
    }

    // Add student data to the queue in batches.
    $batchSize = 100;
    $chunks = array_chunk($student_data, $batchSize);
    foreach ($chunks as $chunk) {
      $queue->createItem($chunk);
    }

    // Save the school data to a file.
    try {
      $this->fileSystem->saveData(json_encode($school_data), $file_uri, FileSystemInterface::EXISTS_REPLACE);
      return new JsonResponse(['message' => 'Data saved successfully', 'file' => $file_uri]);
    }
    catch (FileException $e) {
      return new JsonResponse(['error' => 'Failed to save school data file.'], 500);
    }
    catch (\Exception $e) {
      return new JsonResponse(['error' => 'An unexpected error occurred.'], 500);
    }
  }

}
