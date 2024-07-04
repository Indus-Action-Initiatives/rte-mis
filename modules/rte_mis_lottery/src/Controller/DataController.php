<?php

namespace Drupal\rte_mis_lottery\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a UserDataController object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(FileSystemInterface $file_system, QueueFactory $queue_factory, StateInterface $state,) {
    $this->fileSystem = $file_system;
    $this->queueFactory = $queue_factory;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('queue'),
      $container->get('state')
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

    // Get the current user.
    $current_user = $this->currentUser();

    // Load the user entity.
    $user = $this->entityTypeManager()->getStorage('user')->load($current_user->id());
    if (!$user instanceof UserInterface) {
      throw new AccessDeniedHttpException('Access denied. User not found.');
    }

    // Check if the user has the required permission.
    if (!$user->hasPermission('access lottery')) {
      throw new AccessDeniedHttpException('Access denied. User does not have the required permission.');
    }

    // Get the request content.
    $data = json_decode($request->getContent(), TRUE);

    // Validate the data.
    if (empty($data) || !isset($data['students']) || !isset($data['schools'])) {
      return new JsonResponse(['error' => 'Invalid data. Expected "students" and "schools" keys.'], 400);
    }

    // Extract the data from student and school.
    $student_data = $data['students'];
    $school_data = $data['schools'];

    // Validate the student and school data for any empty fields.
    $invalid_students = [];
    foreach ($student_data as $student_id => $student) {
      if (empty($student['application_id']) || empty($student['name']) || empty($student['parent_name']) || empty($student['location']) || empty($student['preference'] || empty($student['mobile']))) {
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
      return new JsonResponse([
        'error' => 'Invalid data found.',
        'invalid_students' => $invalid_students,
        'invalid_schools' => $invalid_schools,
      ], 400);
    }

    // Check if student_data is an array and not empty.
    if (!is_array($student_data) || empty($student_data)) {
      return new JsonResponse(['error' => 'Invalid student data. Expected a non-empty array.'], 400);
    }

    // Check if school_data is valid.
    if (empty($school_data)) {
      return new JsonResponse(['error' => 'Invalid school data.'], 400);
    }

    // Retrieve and increment the file number from the state system.
    $file_number = $this->state->get('lottery_data_file_number', 0);
    $file_number++;
    $this->state->set('lottery_data_file_number', $file_number);

    $filename = 'school_data_' . $file_number . '.json';
    $directory = '../lottery_files';
    $file_uri = $directory . '/' . $filename;

    // Check if the directory exists, and create it if it doesn't.
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    // Check if the queue already has items.
    $queue = $this->queueFactory->get('student_data_lottery_queue_cron');
    if ($queue->numberOfItems() > 0) {
      return new JsonResponse(['error' => 'Lottery already in progress.'], 400);
    }

    try {
      // Add student data to the queue in batches.
      $batchSize = 100;
      $chunks = array_chunk($student_data, $batchSize, TRUE);
      foreach ($chunks as $chunk) {
        $queue->createItem($chunk);
      }
      // Set the state to external request.
      $this->state->set('lottery_initiated_type', 'external');
      // Save the school data to a file.
      $this->fileSystem->saveData(json_encode($school_data), $file_uri, FileSystemInterface::EXISTS_REPLACE);
      $this->loggerFactory->get('rte_mis_lottery')->info($this->t('Lottery Initiated. Type: External'));
      return new JsonResponse(['message' => 'Lottery Started']);
    }
    catch (FileException $e) {
      return new JsonResponse(['error' => 'An issue occurred with the school list. Please contact site administrator.'], 500);
    }
    catch (\Exception $e) {
      return new JsonResponse(['error' => 'An unexpected error occurred.'], 500);
    }
  }

}
