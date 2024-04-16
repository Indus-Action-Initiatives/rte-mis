<?php

namespace Drupal\rte_mis_core\EventSubscriber;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\eck\EckEntityInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects certain routes.
 */
class RouteRedirectSubscriber implements EventSubscriberInterface {

  /**
   * The user account service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs an RouteRedirect Subscriber object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account service.
   */
  public function __construct(AccountInterface $account) {
    $this->currentUser = $account;
  }

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
    $miniNode = $request->attributes->get('mini_node') ?? NULL;
    if ($currentRoute == 'user.admin_create' && $currentRoute_display !== 'default') {
      // Redirect to '/admin/people/create?display=state'.
      $redirectUrl = Url::fromRoute('user.admin_create', ['display' => 'default'])->toString();
      // Redirect to the generated URL.
      $event->setResponse(new RedirectResponse($redirectUrl, 301));

    }
    // Redirect to form mode `school_detail_edit` for mini node 'school_details'
    // bundle.
    elseif (in_array($currentRoute, [
      'entity.mini_node.edit_form',
      'eck.entity.add',
      'rte_mis_school.school_registration.edit',
    ]) && $currentRoute_display !== 'school_detail_edit') {
      $redirectUrl = '';
      $routeParameter = [
        'display' => 'school_detail_edit',
      ];
      // Redirect for edit node.
      if ($miniNode instanceof EckEntityInterface) {
        $bundle = $miniNode->bundle();
        if (in_array($currentRoute, [
          'entity.mini_node.edit_form',
          'rte_mis_school.school_registration.edit',
        ]) && $bundle === 'school_details') {
          $destination = $request->query->get('destination');
          // Remove the destination query parameter from current request.
          if ($destination) {
            $routeParameter['destination'] = $destination;
            $request->query->replace(['destination' => '']);
          }
          $routeParameter['mini_node'] = $miniNode->id();
          $routeParameter['user'] = $this->currentUser->id();
          $redirectUrl = Url::fromRoute($currentRoute, $routeParameter)->toString();
        }
      }
      // Redirect for new mini node.
      elseif ($currentRoute === 'eck.entity.add' && $request->attributes->get('eck_entity_bundle') === 'school_details') {
        $routeParameter = array_merge($routeParameter, [
          'eck_entity_type' => 'mini_node',
          'eck_entity_bundle' => 'school_details',
        ]);
        $redirectUrl = Url::fromRoute($currentRoute, $routeParameter)->toString();
      }
      // Redirect to the generated URL.
      if (!empty($redirectUrl)) {
        $event->setResponse(new RedirectResponse($redirectUrl, 301));
      }

    }

  }

}
