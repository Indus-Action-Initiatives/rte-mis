<?php

namespace Drupal\rte_mis_core\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\TermForm;
use Drupal\taxonomy\TermInterface;

/**
 * This class override the TermForm.
 */
class OverrideTermForm extends TermForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    // Get the roles from current users.
    $roles = $this->currentUser()->getRoles(TRUE);
    // Hide the status field except for app_admin.
    if (!in_array('app_admin', $roles)) {
      $form['status']['#access'] = FALSE;
    }
    $bundle = $this->entity->bundle();
    // Hide the revision toggle to avoid revision not being stored.
    if (isset($form['revision_information'])) {
      // $form['revision_information']['#access'] = FALSE;
    }
    // Alter term form for school vocabulary.
    if ($this->entity->bundle() == 'school') {
      // Hide parent-child form element.
      $form['relations']['#access'] = FALSE;
      // Add custom submit handler to alter school vocabulary.
      $form['actions']['submit']['#submit'][] = '::customSubmit';

      $form['name']['widget'][0]['value']['#attributes']['class'][] = 'udise-number';
      $form['#attached']['library'][] = 'rte_mis_core/general';
    }
    // Alter term for location_schema vocabulary.
    elseif ($bundle == 'location_schema') {
      // Check if location term exist.
      $locationTerms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
        'vid' => 'location',
      ]);
      // If it exist, show the warning message to delete location term first.
      if (!empty($locationTerms)) {
        $this->messenger()->addWarning($this->t('If you want to delete this location schema, first delete all the location.'));
      }

    }
    elseif ($bundle == 'location') {
      $form['langcode']['widget'][0]['value']['#description'] = $this->t('Please select the language for which you want to add location.');
      if (!in_array('app_admin', $roles)) {
        $form['relations']['weight']['#access'] = FALSE;
      }
    }
    return $form;
  }

  /**
   * Custom submit method for saving values in `school` taxonomy.
   */
  public function customSubmit(array $form, FormStateInterface $form_state) {
    // Get the term id.
    $tid = $this->entity->id() ?? NULL;
    if (isset($tid)) {
      // Load the term.
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
      if ($term instanceof TermInterface) {
        // Check if `field_upload_type` is not populated, Can be case of editing
        // existing term.
        if (empty($term->get('field_upload_type')->getString())) {
          $term->set('field_upload_type', 'individual');
        }
        // Override field_ip_address, to keep track of ip_address.
        $term->set('field_ip_address', $this->getRequest()->getClientIp());
        $term->save();
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $term = $this->entity;

    $result = $term->isNew();
    parent::save($form, $form_state);
    // Delete the existing message.
    $this->messenger()->deleteAll();
    $bundle = $this->entity->bundle();
    // Modify the bundle name.
    $bundle = str_replace('_', ' ', $bundle);
    $bundle = ucwords($bundle);
    // For new term creation.
    if ($result) {
      $message = $this->t('A new %bundle %term has been created.',
      [
        '%bundle' => $bundle,
        '%term' => $term->label(),
      ]);
    }
    // For modifing existing terms.
    else {
      $message = $this->t('Updated %bundle %term.',
      [
        '%bundle' => $bundle,
        '%term' => $term->label(),
      ]);
    }
    $this->messenger()->addStatus($message);
  }

}
