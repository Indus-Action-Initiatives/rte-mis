<?php

namespace Drupal\rte_mis_student\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles custom logout logic.
 */
class StudentLogoutController extends ControllerBase {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The URL generator service.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * The current request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs the StudentLogoutController.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The URL generator service.
   */
  public function __construct(MessengerInterface $messenger, UrlGeneratorInterface $url_generator) {
    $this->messenger = $messenger;
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('url_generator'),
    );
  }

  /**
   * Access check for the custom logout route.
   */
  public function access(Request $request) {

    $code = $request->get('code');

    // Check if the 'code' parameter exists in the URL.
    if (!empty($code) && rte_mis_student_check_cookie_valid($code)) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

  /**
   * Perform custom logout logic.
   */
  public function logout() {
    // Generate the redirect URL.
    $redirect_url = $this->urlGenerator->generate('rte_mis_student.login.form');

    // Clear the 'student-token' cookie.
    $response = new RedirectResponse($redirect_url);
    $response->headers->clearCookie('student-token');

    // Add a message indicating successful logout.
    $this->messenger->addMessage($this->t('You have been logged out successfully.'));

    return $response;
  }

}
