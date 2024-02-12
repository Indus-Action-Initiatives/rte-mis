<?php

namespace Drupal\rte_mis_school\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\TermForm;
use Drupal\taxonomy\TermInterface;

/**
 * This class override the entity edit form of `school_udise_code` taxonomy.
 */
class SchoolUdiseCodeTermForm extends TermForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    if ($this->entity->bundle() == 'school_udise_code') {
      // Hide parent-child form element.
      $form['relations']['#access'] = FALSE;
      // Add custom submit handler to alter school_udise_code vocabulary.
      $form['actions']['submit']['#submit'][] = '::customSubmit';

    }
    return $form;
  }

  /**
   * Custom submit method for saving values in `school_udise_code` taxonomy.
   */
  public function customSubmit(array $form, FormStateInterface $form_state) {
    // Get the term id.
    $tid = $this->entity->id() ?? NULL;
    if (isset($tid)) {
      // Load the term.
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
      if ($term instanceof TermInterface) {
        // Flag variable used to add check if we actually need to save the term.
        $flag = FALSE;
        // Check if `field_upload_type` is not populated, Can be case of editing
        // existing term.
        if (empty($term->get('field_upload_type')->getString())) {
          $flag = TRUE;
          $term->set('field_upload_type', 'individual');
        }
        // Check if `field_ip_address` is not populated, Can be case of editing
        // existing term.
        if (empty($term->get('field_ip_address')->getString())) {
          $flag = TRUE;
          $term->set('field_ip_address', $this->getRequest()->getClientIp());
        }
        if ($flag) {
          $term->save();
        }
      }
    }

  }

}
