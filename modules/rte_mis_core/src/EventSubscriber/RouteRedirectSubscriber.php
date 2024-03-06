<?php

namespace Drupal\rte_mis_core\EventSubscriber;

use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects certain routes.
 */
class RouteRedirectSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['redirectToRedirectedCheck'];
    return $events;
  }

  /**
   * Redirect to the default form mode.
   */
  public function redirectToRedirectedCheck(RequestEvent $event) {
    $request = $event->getRequest();
    $currentRoute = $request->attributes->get('_route');
    $currentRoute_display = $request->query->get('display');

    if ($currentRoute == 'user.admin_create' && $currentRoute_display !== 'default') {
      // Redirect to '/admin/people/create?display=state'.
      $redirectUrl = Url::fromRoute('user.admin_create', ['display' => 'default'])->toString();
      // Redirect to the generated URL.
      $event->setResponse(new RedirectResponse($redirectUrl, 301));

    }
  }

}
