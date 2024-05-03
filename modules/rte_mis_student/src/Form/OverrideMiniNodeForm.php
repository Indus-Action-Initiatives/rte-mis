<?php

namespace Drupal\rte_mis_student\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eck\EckEntityInterface;
use Drupal\eck\Form\Entity\EckEntityForm;

/**
 * Override the form for mini_node entity.
 */
class OverrideMiniNodeForm extends EckEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    // Get the bundle.
    $bundle = $this->entity->bundle();
    if ($bundle == 'student_details') {
      $values = $form_state->getValues();
      $form['field_school_preference_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'school-preference-wrapper',
        ],
      ];
      // Build table.
      $form['field_school_preference_wrapper']['items'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('School Name'),
          $this->t('Selected'),
          $this->t('Weight'),
        ],
        '#empty' => $this->t('Please select Gender, Available class, Date of birth and Location.'),
        '#tableselect' => FALSE,
        '#tabledrag' => [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => 'group-order-weight',
          ],
        ],
      ];
      // Create search button.
      $form['field_school_preference_wrapper']['search_school'] = [
        '#type' => 'button',
        '#value' => $this->t('Search School'),
        '#ajax' => [
          'callback' => [$this, 'fetchSchoolPreferenceAjaxCallback'],
          'wrapper' => 'school-preference-wrapper',
        ],
        '#validate' => ['::validateSearchSchool'],
      ];
      // Ajax properties to applied in gender, dob, location and class field.
      $ajaxProperty = [
        'callback' => [$this, 'multiEventAjaxWrapper'],
        'wrapper' => 'school-preference-wrapper',
        'event' => 'change',
        'progress' => 'none',
      ];

      $form['field_gender']['widget']['#ajax'] = $ajaxProperty;
      $form['field_class']['widget']['#ajax'] = $ajaxProperty;
      $form['field_date_of_birth']['widget'][0]['value']['#ajax'] = $ajaxProperty;
      $form['field_location']['widget'][0]['target_id']['#ajax'] = $ajaxProperty;
      // Restrict the date.
      $form['field_date_of_birth']['widget'][0]['value']['#attributes']['min'] = '2000-01-01';
      $form['field_date_of_birth']['widget'][0]['value']['#attributes']['max'] = date('Y-m-d');
      // Fetch the school list based on form_state values.
      if ((isset($values['field_class'][0]['value']) && is_numeric($values['field_class'][0]['value'])) && isset($values['field_date_of_birth'][0]['value']) && $values['field_date_of_birth'][0]['value'] instanceof DrupalDateTime && !empty($values['field_gender'][0]['value']) && !empty($values['field_location'][0]['target_id'])) {
        $this->fetchSchoolList($form, $form_state);
      }
      // Fetch the school list on student edit form.
      elseif ($this->getRouteMatch()->getRouteName() == 'entity.mini_node.edit_form') {
        $this->fetchSchoolList($form, $form_state);
      }
      // Custom submit handler.
      $form['actions']['submit']['#submit'][] = [$this, 'customSchoolDetailSubmitHandler'];
    }
    return $form;
  }

  /**
   * Ajax callback to reset school preference.
   */
  public function multiEventAjaxWrapper(array &$form, FormStateInterface $form_state) {
    $items = $form_state->getValue('items');
    if (!empty($items)) {
      // Reset the row in table to none.
      $form['field_school_preference_wrapper']['items'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('School Name'),
          $this->t('Status'),
          $this->t('Weight'),
        ],
        '#empty' => $this->t('Please search and re-select the school again.'),
      ];
    }
    return $form['field_school_preference_wrapper'];
  }

  /**
   * Validation for search school button.
   */
  public function validateSearchSchool(array &$form, FormStateInterface $form_state) {
    // clearErrors is used because the button used for search school validate
    // the whole form and using #limit_validation_errors property does not allow
    // non-validated field in form_state.
    $form_state->clearErrors();
    $values = $form_state->getValues();
    // Validate the gender field.
    if (empty($values['field_gender'][0]['value'])) {
      $form_state->setErrorByName('field_gender', $this->t('Gender is required for school selection.'));
    }
    // Validate the location field.
    if (empty($values['field_location'][0]['target_id'])) {
      $form_state->setErrorByName('field_location', $this->t('Location is required for school selection.'));
    }
    // Validate the DOB field.
    if (isset($values['field_date_of_birth'][0]['value']) && !($values['field_date_of_birth'][0]['value'] instanceof DrupalDateTime)) {
      $form_state->setErrorByName('field_date_of_birth', $this->t('Date of birth is required for school selection.'));
    }
    // Validate the class field.
    if (!isset($values['field_class'][0]['value']) || !is_numeric($values['field_class'][0]['value'])) {
      $form_state->setErrorByName('field_class', $this->t('Available classes is required for school selection.'));
    }
  }

  /**
   * Custom submit handler for mini_node student_detail.
   */
  public function customSchoolDetailSubmitHandler(array &$form, FormStateInterface $form_state) {
    // Get the entity.
    $miniNode = $form_state->getFormObject()->getEntity();
    if ($miniNode instanceof EckEntityInterface) {
      $targetIds = [];
      // Get the school list from table element.
      $schoolPreferences = $form_state->getValue('items');
      if (!empty($schoolPreferences)) {
        // Loop each school list and store them to field if school is selected.
        foreach ($schoolPreferences as $schoolPreference) {
          $status = $schoolPreference['status'] ?? NULL;
          $id = $schoolPreference['id'] ?? NULL;
          if ($status) {
            $targetIds[] = ['target_id' => $id];
          }
        }
      }
      $miniNode->set('field_school_preference', $targetIds);
      $miniNode->save();
    }

  }

  /**
   * Ajax callback for fetching school preference.
   */
  public function fetchSchoolPreferenceAjaxCallback(array &$form, FormStateInterface $form_state) {
    return $form['field_school_preference_wrapper'];
  }

  /**
   * Get the table row as school preference.
   *
   * This method populate the table row with school based on gender, DOB, class
   * and location.
   */
  public function fetchSchoolList(&$form, FormStateInterface $form_state) {
    // Get the entity, mainly used to fetch value in edit mode.
    $miniNode = $form_state->getFormObject()->getEntity();
    $availableSchools = [];
    // Get the values from form_state.
    $values = $form_state->getValues();
    $studentLocation = $values['field_location'][0]['target_id'] ?? $miniNode->get('field_location')->getString() ?? NULL;
    $studentGender = $values['field_gender'][0]['value'] ?? $miniNode->get('field_gender')->getString() ?? NULL;
    $studentSelectedClass = $values['field_class'][0]['value'] ?? $miniNode->get('field_class')->getString() ?? NULL;
    $studentDob = $values['field_date_of_birth'][0]['value'] ?? $miniNode->get('field_date_of_birth')->date ?? NULL;
    // Get the school id from field. Used in edit form.
    $selectedSchoolPreference = $miniNode->get('field_school_preference')->getValue() ?? [];
    // Flatten the array.
    $selectedSchoolPreference = array_column($selectedSchoolPreference, 'target_id');
    // Calculate the age in student.
    $studentAgeInMonths = $this->calculateStudentAge($studentDob);
    // Get the age criteria for different class.
    $studentAgeCriteria = $this->config('rte_mis_student.settings')->get('student_age_criteria') ?? [];
    $classAgeCriteria = $studentAgeCriteria[$studentSelectedClass] ?? NULL;
    $minimumAgeLimit = ($classAgeCriteria['min_age'] ?? 0) * 12;
    $maximumAgeLimit = ($classAgeCriteria['max_age'] ?? 0) * 12;
    // Only proceed further if the student age is within the range of permitted
    // age of class.
    if ($studentAgeInMonths >= $minimumAgeLimit && $studentAgeInMonths <= $maximumAgeLimit) {
      $filteredSchools = [];
      // Load All schools mapped to user selected habitation.
      $schools = $this->entityTypeManager->getStorage('mini_node')->loadByProperties([
        'type' => 'school_details',
        'status' => 1,
        'field_habitations' => $studentLocation,
        'field_school_verification' => 'school_registration_verification_approved_by_deo',
      ]);
      // Check if school is active, unaided and non_minority.
      foreach ($schools as $school) {
        $tid = $school->get('field_udise_code')->getString();
        $query = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()
          ->condition('vid', 'school')
          ->condition('tid', $tid)
          ->condition('field_aid_status', 'unaided')
          ->condition('field_minority_status', 'non_minority')
          ->condition('status', 1)
          ->accessCheck(TRUE);
        $result = $query->execute();
        if ($result) {
          $filteredSchools[] = $school;
        }
      }
      // Further, check if school has preferred entry class selected by user
      // If the class is present then check the particular gender is matching.
      if (!empty($filteredSchools)) {
        foreach ($filteredSchools as $school) {
          foreach ($school->field_entry_class->referencedEntities() as $entryClass) {
            // Education type(girls|boys).
            $schoolEducationType = $entryClass->get("field_education_type")->getString();
            // Entry Class(1st, Nursery)
            $schoolEntryClass = $entryClass->get("field_entry_class")->getString();
            // Add the school, if passes the check of gender and entry class.
            if ($this->filterSchool($studentGender, $studentSelectedClass, $schoolEducationType, $schoolEntryClass)) {
              $availableSchools[$school->id()] = $school;
            }
          }
        }
      }
    }
    // Prepare the table row.
    if (!empty($availableSchools)) {
      // This is used to sort the availableSchool list on the edit form mode.
      if (!empty($selectedSchoolPreference)) {
        $availableSchools = $this->sortPreferenceSchool($selectedSchoolPreference, $availableSchools);
      }
      $count = 0;
      foreach ($availableSchools as $availableSchool) {
        $form['field_school_preference_wrapper']['items'][$count]['#attributes']['class'][] = 'draggable';
        $form['field_school_preference_wrapper']['items'][$count]['label'] = [
          '#plain_text' => $availableSchool->get('field_school_name')->getString(),
        ];
        $form['field_school_preference_wrapper']['items'][$count]['status'] = [
          '#type' => 'checkbox',
          '#default_value' => (in_array($availableSchool->id(), $selectedSchoolPreference) ? TRUE : $miniNode->isNew()) ? TRUE : FALSE,
        ];

        $form['field_school_preference_wrapper']['items'][$count]['weight'] = [
          '#type' => 'weight',
          '#title_display' => 'invisible',
          '#default_value' => $count,
          '#attributes' => [
            'class' => [
              'draggable-weight',
              'group-order-weight',
            ],
          ],
        ];

        $form['field_school_preference_wrapper']['items'][$count]['id'] = [
          '#type' => 'hidden',
          '#value' => $availableSchool->id(),
        ];
        $count++;
      }
    }
    else {
      $form['field_school_preference_wrapper']['items']['#empty'] = $this->t('Sorry, There are no school available for the selected Gender, Class, Date of birth and Location.');
    }
  }

  /**
   * Sort the school preference based on entity id.
   */
  private function sortPreferenceSchool($selected_school_preference, $available_schools) {
    $sortedSchools = [];
    foreach ($selected_school_preference as $preferenceSchoolId) {
      if (isset($available_schools[$preferenceSchoolId])) {
        $sortedSchools[] = $available_schools[$preferenceSchoolId];
        unset($available_schools[$preferenceSchoolId]);
      }
    }
    return array_merge($sortedSchools, array_values($available_schools));
  }

  /**
   * This method filter the school, if it match the certain criteria.
   */
  private function filterSchool($student_gender = NULL, $student_selected_class = NULL, $school_education_type = NULL, $school_entry_class = NULL) {
    if (!empty($student_gender) && is_numeric($student_selected_class) && !empty($school_education_type) && is_numeric($school_entry_class)) {
      // Match the selected entry class with school entry class being offered.
      if ($student_selected_class == $school_entry_class) {
        // Gender match. Below is the following cases in format
        // Selected-Gender: School education type.
        // 1. Girl - [girls, co-ed]
        // 2. Boy - [boys, co-ed]
        // 3. Transgender - [any].
        if ($student_gender == 'girl' && in_array($school_education_type, ['girls', 'co-ed'])) {
          return TRUE;
        }
        elseif ($student_gender == 'boy' && in_array($school_education_type, ['boys', 'co-ed'])) {
          return TRUE;
        }
        elseif ($student_gender == 'transgender') {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * This method calculate the student age against 31st March current Year.
   */
  private function calculateStudentAge($student_dob) {
    if ($student_dob instanceof DrupalDateTime) {
      $currentYear = date('Y');
      $currentDate = new DrupalDateTime("$currentYear-03-31");
      // Calculate the difference in months.
      $interval = $student_dob->diff($currentDate);
      $months = $interval->m + ($interval->y * 12);
      return $months;
    }
    return FALSE;
  }

}
