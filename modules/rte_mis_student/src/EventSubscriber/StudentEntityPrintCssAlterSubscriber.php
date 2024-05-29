<?php

namespace Drupal\rte_mis_student\EventSubscriber;

use Drupal\entity_print\Event\PrintCssAlterEvent;
use Drupal\entity_print\Event\PrintEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to alter entity print css.
 *
 * @see \Drupal\entity_print\Asset\AssetCollector::getCssLibraries
 */
class StudentEntityPrintCssAlterSubscriber implements EventSubscriberInterface {

  /**
   * React to a config object being saved.
   *
   * @param \Drupal\entity_print\Event\PrintCssAlterEvent $event
   *   Entity Print CSS alter event.
   */
  public function alterCss(PrintCssAlterEvent $event) {
    $entities = $event->getEntities();
    foreach ($entities as $entity) {
      if ($entity->getEntityTypeId() === 'mini_node' && $entity->bundle() == 'student_details') {
        $event->getBuild()['#attached']['library'][] = 'rte_mis_student/student_details_pdf';
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      PrintEvents::CSS_ALTER => 'alterCss',
    ];
  }

}
