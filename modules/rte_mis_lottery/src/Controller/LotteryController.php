<?php

namespace Drupal\rte_mis_lottery\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Queue\QueueFactory;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller that handle API request for lottery.
 */
class LotteryController extends ControllerBase {

  /**
   * Queue factory instance.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Constructs a new LotteryController object.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   Queue factory instance.
   */
  public function __construct(QueueFactory $queueFactory) {
    $this->queueFactory = $queueFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('queue'),
    );
  }

  /**
   * Get the current status of lottery.
   *
   * API endpoint: '/api/v1/lottery-status'
   */
  public function getStatus() {
    $user = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
    if ($user instanceof UserInterface && $user->hasPermission('view lottery status')) {
      $queue_factory = $this->queueFactory->get('student_data_lottery_queue_cron');
      if ($queue_factory->numberOfItems() > 0) {
        $data = [
          '#message' => 'Lottery in progress.',
        ];
      }
      else {
        $data = [
          '#message' => 'Lottery is over or not started.',
        ];
      }

      $status_code = Response::HTTP_OK;
    }
    else {
      $data = [
        '#message' => 'Access Denied',
      ];
      $status_code = Response::HTTP_FORBIDDEN;
    }
    $response = new JsonResponse($data, $status_code);
    return $response;

  }

}
