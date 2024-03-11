<?php

namespace Drupal\rte_mis_core\EventSubscriber;

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
    ]) && $currentRoute_display !== 'school_detail_edit') {
      $redirectUrl = '';
      // Redirect for edit node.
      if ($miniNode instanceof EckEntityInterface) {
        $bundle = $miniNode->bundle();
        if ($currentRoute === 'entity.mini_node.edit_form' && $bundle === 'school_details') {
          $redirectUrl = Url::fromRoute($currentRoute, [
            'mini_node' => $miniNode->id(),
            'display' => 'school_detail_edit',
          ])->toString();
        }
      }
      // Redirect for new mini node.
      elseif ($currentRoute === 'eck.entity.add' && $request->attributes->get('eck_entity_bundle') === 'school_details') {
        $redirectUrl = Url::fromRoute($currentRoute, [
          'eck_entity_type' => 'mini_node',
          'eck_entity_bundle' => 'school_details',
          'display' => 'school_detail_edit',
        ])->toString();
      }
      // Redirect to the generated URL.
      if (!empty($redirectUrl)) {
        $event->setResponse(new RedirectResponse($redirectUrl, 301));
      }

    }

  }

}
