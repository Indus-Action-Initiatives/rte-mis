<?php

namespace Drupal\rte_mis_student;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\taxonomy\Form\OverviewTerms;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\user\UserInterface;

/**
 * Override the terms overview form.
 */
class CustomVocabularyListBuilder extends OverviewTerms {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, VocabularyInterface $taxonomy_vocabulary = NULL) {
    $build = parent::buildForm($form, $form_state, $taxonomy_vocabulary);
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser()->id());
    $roles = $this->currentUser()->getRoles();
    if ($taxonomy_vocabulary->id() == 'location' && $user instanceof UserInterface && array_intersect($roles, [
      'state_admin',
      'district_admin',
    ])) {
      $locationId = $user->get('field_location_details')->getString() ?? 0;
      // Unset the operation and weight header.
      if ($user->hasRole('district_admin')) {
        unset($build["terms"]["#header"]["operations"]);
        unset($build["terms"]["#header"]["weight"]);
      }
      $hideSubTerm = FALSE;
      // Loop terms and and unset operation and weight from build term data.
      foreach ($build['terms'] as $key => $value) {
        if (strpos($key, 'tid:') === 0) {
          $build['terms'][$key]['term'] = [
            '#prefix' => $value["term"]['#prefix'],
            '#plain_text' => $value["term"]['#title'],
            'tid' => $value["term"]['tid'],
            'parent' => $value["term"]['parent'],
            'depth' => $value["term"]['depth'],
          ];

          // Hide the other districts and sublevels if it does not belong to the
          // district admin.
          if ($user->hasRole('district_admin')) {
            if ($value["#term"]->depth == 0 && $value["#term"]->id() != $locationId || ($hideSubTerm && $value["#term"]->depth > 0)) {
              unset($build['terms'][$key]);
              $hideSubTerm = TRUE;
            }
            else {
              $hideSubTerm = FALSE;
              unset($build['terms'][$key]['operations']);
              unset($build['terms'][$key]['weight']);
            }
          }
        }
      }
    }

    $empty = $this->t('No terms available. <a href=":link">Add %vid</a>.', [
      '%vid' => $taxonomy_vocabulary->id(),
      ':link' => Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $taxonomy_vocabulary->id()])->toString(),
    ]);

    // Update the form's '#empty' property if it exists.
    if (isset($build['terms']['#empty'])) {
      $build['terms']['#empty'] = $empty;
    }

    return $build;
  }

}
