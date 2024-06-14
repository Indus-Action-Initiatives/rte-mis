<?php

namespace Drupal\rte_mis_core\Controller;

use Drupal\taxonomy\Controller\TaxonomyController;
use Drupal\taxonomy\VocabularyInterface;

/**
 * This class override the TaxonomyController.
 */
class OverrideTaxonomyController extends TaxonomyController {

  /**
   * {@inheritDoc}
   */
  public function addForm(VocabularyInterface $taxonomy_vocabulary) {
    $term = $this->entityTypeManager()->getStorage('taxonomy_term')->create(['vid' => $taxonomy_vocabulary->id()]);
    $form = $this->entityFormBuilder()->getForm($term);

    switch ($taxonomy_vocabulary->id()) {
      case 'location':
        $title = 'Add Location';
        break;

      case 'school':
        $title = 'Add School';
        break;

      case 'location_schema':
        $title = 'Add Location Schema';
        break;

      default:
        $title = 'Add Term';
        break;
    }

    return [
      '#title' => $title,
      'form' => $form,
    ];
  }

}
