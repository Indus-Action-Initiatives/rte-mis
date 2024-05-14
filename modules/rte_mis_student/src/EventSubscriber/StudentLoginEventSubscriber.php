<?php

namespace Drupal\rte_mis_student\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\eck\EckEntityInterface;
use Drupal\rte_mis_student\Services\MobileOtpServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber for handling student login.
 */
class StudentLoginEventSubscriber implements EventSubscriberInterface {
  use StringTranslationTrait;

  /**
   * Drupal\Core\Routing\CurrentRouteMatch definition.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * The mobileOtpService service.
   *
   * @var \Drupal\rte_mis_student\Services\MobileOtpServiceInterface
   */
  protected $mobileOtpService;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * StudentLoginEventSubscriber Constructor.
   */
  public function __construct(CurrentRouteMatch $current_route_match, MobileOtpServiceInterface $mobile_otp_service, MessengerInterface $messenger, AccountInterface $current_user) {
    $this->routeMatch = $current_route_match;
    $this->mobileOtpService = $mobile_otp_service;
    $this->messenger = $messenger;
    $this->currentUser = $current_user;
  }

  /**
   * Implements EventSubscriberInterface::getSubscribedEvents().
   *
   * @return array
   *   An array of event listener definitions.
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onResponse', 30];
    return $events;
  }

  /**
   * Check the cookie and redirect to login page if not valid.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event to process.
   */
  public function onResponse(ResponseEvent $event) {
    if (!$event->isMainRequest()) {
      return;
    }
    // Get the request parameters.
    $request = $event->getRequest();

    $roles = $this->currentUser->getRoles();

    $routName = $this->routeMatch->getRouteName();
    if (in_array($routName, [
      'entity.mini_node.canonical',
      'rte_mis_student.controller.student_application',
      'eck.entity.add',
      'entity.mini_node.edit_form',
    ])) {
      // Get all cookies from request.
      $cookies = $request->cookies;
      $studentTokenCookie = $cookies->get('student-token', NULL);
      $studentPhoneCookie = $cookies->get('student-phone', NULL);
      $code = $request->query->get('code') ?? NULL;
      if (in_array('anonymous', $roles)) {
        if ($routName == 'eck.entity.add') {
          $bundle = $this->routeMatch->getParameter('eck_entity_bundle') ?? NULL;
          if ($bundle != 'student_details') {
            return;
          }
        }
        if ($routName == 'entity.mini_node.edit_form') {
          $entity = $this->routeMatch->getParameter('mini_node') ?? NULL;
          if ($entity instanceof EckEntityInterface &&  $entity->bundle() != 'student_details') {
            return;
          }
        }
        if (!isset($code) && $request->query->has('destination')) {
          $destination = $request->query->get('destination');
          $code = $this->extractCodeFromUrl($destination);
        }
        $message = [
          'method' => 'addError',
          'message' => $this->t('Session expired. Please login again'),
        ];
        // Redirect to login if cookie and query parameter doesn't exists.
        if (!$studentTokenCookie || !$studentPhoneCookie || !$code) {
          $event->setResponse($this->redirectToStudentLogin($message));
        }
        // Redirect to login, if invalid cookie and query is set.
        $result = $this->mobileOtpService->validateUser($code, $studentTokenCookie);
        if (!$result) {
          $event->setResponse($this->redirectToStudentLogin($message));
        }
      }
      elseif (in_array('block_admin', $roles)) {
        if ($routName == 'entity.mini_node.edit_form') {
          $entity = $this->routeMatch->getParameter('mini_node') ?? NULL;
          if ($entity instanceof EckEntityInterface &&  $entity->bundle() != 'student_details') {
            return;
          }
          if (!isset($code) && $request->query->has('destination')) {
            $embeddedDestination = $request->query->get('destination');
            $code = $this->extractCodeFromUrl($embeddedDestination);
          }
          $phoneNumber = $entity->get('field_mobile_number')->local_number ?? '';
          $destination = Url::fromRoute('entity.mini_node.edit_form', ['mini_node' => $entity->id()])->toString();
          if (!$studentTokenCookie || !$studentPhoneCookie || !$code) {
            if ($request->query->has('destination')) {
              $request->query->remove('destination');
            }
            $event->setResponse($this->redirectToStudentLogin([], [
              'query' => [
                'phone' => $phoneNumber,
                'destination' => $destination,
              ],
            ]));
            return;
          }
          // Redirect to login, if invalid cookie and query is set.
          $result = $this->mobileOtpService->validateUser($code, $studentTokenCookie);
          if (!$result) {
            $message = [
              'method' => 'addError',
              'message' => $this->t('Session expired. Please login again'),
            ];
            $event->setResponse($this->redirectToStudentLogin($message, [
              'query' => [
                'phone' => $phoneNumber,
                'destination' => $destination,
              ],
            ]));
          }
        }
      }
    }
  }

  /**
   * Extract the code from destination query parameter.
   */
  public function extractCodeFromUrl($url) {
    // Parse the URL to get the query string.
    $parsed_url = parse_url($url);
    // Check if the query string exists and is not empty.
    if (isset($parsed_url['query']) && !empty($parsed_url['query'])) {
      // Parse the query string to get the query parameters.
      parse_str($parsed_url['query'], $query_params);
      // Check if the code parameter exists in the query parameters.
      if (isset($query_params['code'])) {
        // Return the value of the code parameter.
        return $query_params['code'];
      }
    }

    // Return null if the code parameter is not found.
    return NULL;
  }

  /**
   * Redirect to student login page.
   */
  public function redirectToStudentLogin($message = [], $options = []) {
    if (!empty($message)) {
      $this->messenger->{$message['method']}($message['message']);
    }
    $url = Url::fromRoute('rte_mis_student.login.form', [], $options);
    $response = new RedirectResponse($url->toString());
    return $response;
  }

}
