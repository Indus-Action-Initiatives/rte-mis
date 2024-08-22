<?php

namespace Drupal\rte_mis_student\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Random;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\eck\EckEntityInterface;
use Drupal\eck\Form\Entity\EckEntityForm;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\workflow\Entity\WorkflowTransition;

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
    $roles = $this->currentUser()->getRoles();
    // Unset Path for Alias change.
    if ($bundle == 'school_details' && array_intersect($roles, ['school', 'school_admin'])) {
      unset($form['path']);
    }
    if ($bundle == 'student_details') {
      $values = $form_state->getValues();
      $form['#attributes']['id'] = 'school-detail-wrapper';

      $form['field_school_preference_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'school-preference-wrapper',
          // Dirty hack of marking school-selection tab as required.
          'required' => 'required',
        ],
      ];
      $tableDragIcon = new FormattableMarkup('<span class="tabledrag-handle"></span>', []);
      $form['field_school_preference_wrapper']['selection_markup'] = [
        '#prefix' => '<div class="selection-detail-markup"><p>',
        '#markup' => $this->t('Select Nearest school within 1 km from your residence first. You can select more than one school. Use the icon @icon in the below table to sort the school preferences.', ['@icon' => $tableDragIcon]),
        '#suffix' => '</p></div>',
      ];
      // Build table.
      $form['field_school_preference_wrapper']['items'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('School Name'),
          $this->t('UDISE Code'),
          $this->t('Medium'),
          $this->t('RTE Seat'),
          $this->t('Entry Class'),
          $this->t('Selected'),
          [
            'data' => $this->t('Weight'),
            'class' => ['tabledrag-hide'],
          ],
        ],
        '#empty' => $this->t('Please select Gender, Date of birth and Location.'),
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
        '#value' => $this->t('View Schools'),
        '#ajax' => [
          'callback' => [$this, 'fetchSchoolPreferenceAjaxCallback'],
          'wrapper' => 'school-preference-wrapper',
          'progress' => [
            'type' => 'fullscreen',
          ],
        ],
        '#validate' => ['::validateSearchSchool'],
      ];
      // Ajax properties to applied in gender, dob, location and class field.
      $ajaxProperty = [
        'callback' => [$this, 'multiEventAjaxWrapper'],
        'wrapper' => 'school-detail-wrapper',
        'event' => 'change',
        'progress' => [
          'type' => 'fullscreen',
        ],
      ];

      $form['field_gender']['widget']['#ajax'] = $ajaxProperty;
      $form['field_date_of_birth']['widget'][0]['value']['#ajax'] = $ajaxProperty;
      $form['field_location']['widget'][0]['target_id']['#ajax'] = $ajaxProperty;
      // Load the labels for the location field.
      $this->alterCshsLabels($form, $form_state);
      // Restrict the date.
      $form['field_date_of_birth']['widget'][0]['value']['#attributes']['min'] = '2000-01-01';
      $form['field_date_of_birth']['widget'][0]['value']['#attributes']['max'] = date('Y-m-d');
      // Hide `field_orphan` if `field_single_girl_child` or
      // `field_has_siblings` value is selected.
      $form['field_orphan']['#states'] = [
        'invisible' => [
            [':input[name="field_single_girl_child"]' => ['value' => 1]],
          'or',
            [':input[name="field_has_siblings"]' => ['value' => 1]],
        ],
      ];

      $form['field_parent_type']['parent_details_markup'] = [
        '#prefix' => '<div class="parent-detail-markup"><p>',
        '#markup' => $this->t('Detailed Father/Mother/Guardian(It is mandatory to fill the details of atleast one)'),
        '#suffix' => '</p></div>',
        '#weight' => -1,
      ];
      // Unset the default value of single parent type field.
      unset($form['field_single_parent_type']['widget']['#options'][""]);
      // Make the `field_single_parent_type` visible & required
      // when `field_parent_type` has value `single_parent`.
      $form['field_single_parent_type']['#states'] = [
        'visible' => [
          [':input[name="field_parent_type"]' => ['value' => 'single_parent']],
        ],
        'required' => [
          [':input[name=field_parent_type]' => ['value' => 'single_parent']],
        ],
      ];
      // Show the `group_father_detail` when either father_mother
      // is selected in `field_parent_type` or
      // `father` is selected from `field_single_parent_type`.
      $form['group_father_detail']['#states'] = [
        'visible' => [
          [
            [':input[name="field_parent_type"]' => ['value' => 'father_mother']],
            'or',
            [
              ':input[name="field_parent_type"]' => ['value' => 'single_parent'],
              ':input[name="field_single_parent_type"]' => ['value' => 'father'],
            ],
          ],
        ],
      ];
      // Make the `field_father_name` required based on the above conditions.
      $form['field_father_name']['#states'] = [
        'required' => [
          [
            [':input[name="field_parent_type"]' => ['value' => 'father_mother']],
            'or',
            [
              ':input[name="field_parent_type"]' => ['value' => 'single_parent'],
              ':input[name="field_single_parent_type"]' => ['value' => 'father'],
            ],
          ],
        ],
      ];
      // Make the `field_father_aadhar_number` required.
      $form['field_father_aadhar_number']['#states'] = [
        'required' => [
          [
            [':input[name="field_parent_type"]' => ['value' => 'father_mother']],
            'or',
            [
              ':input[name="field_parent_type"]' => ['value' => 'single_parent'],
              ':input[name="field_single_parent_type"]' => ['value' => 'father'],
            ],
          ],
        ],
      ];
      // Show the `group_mother_detail` when either father_mother
      // is selected in `field_parent_type` or
      // `mother` is selected from `field_single_parent_type`.
      $form['group_mother_detail']['#states'] = [
        'visible' => [
          [
            [':input[name="field_parent_type"]' => ['value' => 'father_mother']],
            'or',
            [
              ':input[name="field_parent_type"]' => ['value' => 'single_parent'],
              ':input[name="field_single_parent_type"]' => ['value' => 'mother'],
            ],
          ],
        ],
      ];
      // Make the `field_mother_name` required based on the above conditions.
      $form['field_mother_name']['#states'] = [
        'required' => [
          [
            [':input[name="field_parent_type"]' => ['value' => 'father_mother']],
            'or',
            [
              ':input[name="field_parent_type"]' => ['value' => 'single_parent'],
              ':input[name="field_single_parent_type"]' => ['value' => 'mother'],
            ],
          ],
        ],
      ];
      // Make the `field_mother_aadhar_number` required.
      $form['field_mother_aadhar_number']['#states'] = [
        'required' => [
          [
            [':input[name="field_parent_type"]' => ['value' => 'father_mother']],
            'or',
            [
              ':input[name="field_parent_type"]' => ['value' => 'single_parent'],
              ':input[name="field_single_parent_type"]' => ['value' => 'mother'],
            ],
          ],
        ],
      ];
      // Show `group_guardian_detail` only when guardian
      // is selected in `field_parent_type`.
      $form['group_guardian_detail']['#states'] = [
        'visible' => [
          [':input[name="field_parent_type"]' => ['value' => 'guardian']],
        ],
      ];
      // Make the `field_guardian_name` required.
      $form['field_guardian_name']['#states'] = [
        'required' => [
          [':input[name="field_parent_type"]' => ['value' => 'guardian']],
        ],
      ];
      // Make the `field_gaurdian_aadhar_number` required.
      $form['field_gaurdian_aadhar_number']['#states'] = [
        'required' => [
          [':input[name="field_parent_type"]' => ['value' => 'guardian']],
        ],
      ];

      // Hide `field_has_siblings` if `field_single_girl_child` or
      // `field_orphan` value is selected.
      $form['field_has_siblings']['#states'] = [
        'invisible' => [
          [':input[name="field_single_girl_child"]' => ['value' => 1]],
          'or',
          [':input[name="field_orphan"]' => ['value' => 1]],
        ],
      ];
      // Hide `field_single_girl_child` if `field_has_siblings` or
      // `field_orphan` value is selected.
      $form['field_single_girl_child']['#states'] = [
        'invisible' => [
          [':input[name="field_orphan"]' => ['value' => 1]],
          'or',
          [':input[name="field_has_siblings"]' => ['value' => 1]],
        ],
      ];
      // Student details tab note.
      $form['student_details_note_container'] = [
        '#type' => 'container',
        '#group' => 'group_student_basic_details',
        '#weight' => isset($form['field_single_girl_child']) ? $form['field_single_girl_child']['#weight'] - 1 : 0,
        'note' => [
          '#type' => 'item',
          '#title' => $this->t('Applicants can choose to avail any one of the following facilities.'),
        ],
      ];
      // Replace the ajax callback and wrapper for `Add More` button.
      $form['field_siblings_details']['widget']['add_more']['add_more_button_siblings']['#ajax']['callback'] = '::multiEventAjaxWrapper';
      $form['field_siblings_details']['widget']['add_more']['add_more_button_siblings']['#ajax']['wrapper'] = 'school-detail-wrapper';
      $form['field_siblings_details']['widget']['add_more']['add_more_button_siblings']['#validate'] = ['::validateSearchSchool'];
      // Removed the `limit_validation_errors` attribute as it restrict
      // values in form_state.
      unset($form['field_siblings_details']['widget']['add_more']['add_more_button_siblings']['#limit_validation_errors']);
      $form['field_has_siblings']['widget']['#ajax'] = $ajaxProperty;

      // Set the default value as current registration year. Also set this field
      // as readonly and disabled.
      $form['field_academic_year']['widget']['#default_value'] = _rte_mis_core_get_current_academic_year();
      $form['field_academic_year']['widget']['#attributes']['readonly'] = 'readonly';
      $form['field_academic_year']['widget']['#attributes']['disabled'] = 'disabled';

      if ($this->entity->hasField('field_student_application_number')) {
        if ($this->entity->isNew()) {
          $form['field_student_application_number']['#access'] = FALSE;
        }
        else {
          $form['field_student_application_number']['widget'][0]['value']['#attributes']['readonly'] = 'readonly';
          $form['field_student_application_number']['widget'][0]['value']['#attributes']['disabled'] = 'disabled';
        }
      }
      if (array_key_exists('#suffix', $form['field_siblings_details']['widget']['add_more'])) {
        unset($form['field_siblings_details']['widget']['add_more']['#suffix']);
      }

      // Fetch school list based on form_state values or on student edit form.
      $available_schools = [];
      if (isset($values['field_date_of_birth'][0]['value']) && $values['field_date_of_birth'][0]['value'] instanceof DrupalDateTime && !empty($values['field_gender'][0]['value']) && !empty($values['field_location'][0]['target_id']) || $this->getRouteMatch()->getRouteName() == 'entity.mini_node.edit_form') {
        // Get the school list.
        $available_schools = $this->getSchoolPreferenceList($form, $form_state);
        // Update the table element with the school list.
        $this->updateSchoolPreferenceElement($form, $form_state, $available_schools['school_preference_data']);
      }
      // Update the option with the school list in for `field_school` field in
      // sibling details paragraph.
      $this->updateSiblingListElement($form, $form_state, $available_schools['sibling_data'] ?? []);
      // Only applicable to anonymous user.
      if (in_array('anonymous', $roles)) {
        // Hide the workflow form and make the transition programmatically in
        // submit method.
        $form['field_student_verification']['#access'] = FALSE;
      }
      // Custom submit handler.
      $form['actions']['submit']['#submit'][] = [$this, 'customSchoolDetailSubmitHandler'];
    }
    return $form;
  }

  /**
   * Get the school preference list.
   *
   * This method get the school list based on gender, DOB, class and location.
   */
  public function getSchoolPreferenceList(array &$form, FormStateInterface $form_state) {
    // Get the entity, mainly used to fetch value in edit mode.
    $miniNode = $form_state->getFormObject()->getEntity();
    $medium = $this->config('rte_mis_school.settings')->get('field_default_options.field_medium') ?? NULL;
    $siblingSchoolData = $schoolPreferenceData = [];
    // Get the values from form_state.
    $values = $form_state->getValues();
    $studentLocation = $values['field_location'][0]['target_id'] ?? $miniNode->get('field_location')->getString() ?? NULL;
    $studentGender = $values['field_gender'][0]['value'] ?? $miniNode->get('field_gender')->getString() ?? NULL;
    $studentDob = $values['field_date_of_birth'][0]['value'] ?? $miniNode->get('field_date_of_birth')->date ?? NULL;
    // Calculate the age in student.
    $studentAgeInMonths = $this->calculateStudentAge($studentDob);
    // Get the age criteria for different class.
    $studentAgeCriteria = $this->config('rte_mis_student.settings')->get('student_age_criteria') ?? [];
    // Get the class satisfying the age criteria.
    $eligibleClasses = array_filter($studentAgeCriteria, fn($value) => $studentAgeInMonths >= (($value['min_age'] ?? 0) * 12) && $studentAgeInMonths <= (($value['max_age'] ?? 0) * 12));
    // Only proceed further if the student age is within the range of permitted
    // age of class.
    if (!empty($eligibleClasses)) {
      $eligibleClasses = array_keys($eligibleClasses);
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
      $rteSeatsFieldName = 'field_rte_student_for_';
      // Further, check if school has preferred entry class selected by user
      // If the class is present then check the particular gender is matching.
      if (!empty($filteredSchools)) {
        foreach ($filteredSchools as $school) {
          $fieldUdiseCodeOption = [];
          $fieldUdiseCodeDefinition = $school->get('field_udise_code')->getFieldDefinition()->getFieldStorageDefinition();
          if ($fieldUdiseCodeDefinition instanceof FieldStorageConfig) {
            $fieldUdiseCodeOption = options_allowed_values($fieldUdiseCodeDefinition, $school);
          }
          foreach ($school->field_entry_class->referencedEntities() as $entryClass) {
            // Education type(girls|boys).
            $schoolEducationType = $entryClass->get('field_education_type')->getString();
            // Entry Class(1st, Nursery)
            $schoolEntryClass = $entryClass->get('field_entry_class')->getString();
            // Add the school, if passes the check of gender and entry class.
            $filteredSchool = $this->filterSchool($studentGender, $eligibleClasses, $schoolEducationType, $schoolEntryClass);
            if ($filteredSchool) {
              $siblingSchoolData[$school->id()] = $school;
              foreach ($medium as $medium_machine_name => $medium_value) {
                if ($entryClass->hasField("$rteSeatsFieldName$medium_machine_name") && (int) $entryClass->get("$rteSeatsFieldName$medium_machine_name")->getString() > 0) {
                  $schoolPreferenceData[] = [
                    'id' => $school->id(),
                    'name' => $school->get('field_school_name')->getString(),
                    'udise_code' => $fieldUdiseCodeOption[$school->get('field_udise_code')->getString()],
                    'medium' => $medium_machine_name,
                    'rte_seat' => (int) $entryClass->get("$rteSeatsFieldName$medium_machine_name")->getString(),
                    'entry_class' => $schoolEntryClass,
                  ];
                }
              }
            }
          }
        }
      }
    }
    return [
      'sibling_data' => $siblingSchoolData,
      'school_preference_data' => $schoolPreferenceData,
    ];
  }

  /**
   * Update the options with available school.
   *
   * This method replaces the options with available school in the
   * `field_school` in sibling details paragraph.
   */
  public function updateSiblingListElement(array &$form, FormStateInterface $form_state, $available_schools) {
    $children = Element::children($form['field_siblings_details']['widget'], TRUE);
    foreach ($children as $child) {
      if (is_numeric($child)) {
        if (!empty($available_schools)) {
          $options = [];
          foreach ($available_schools as $availableSchool) {
            $options[$availableSchool->id()] = $availableSchool->get('field_school_name')->getString();
          }
          $form['field_siblings_details']['widget'][$child]['subform']['field_school']['widget']['#options'] = $options;
        }
        else {
          $form['field_siblings_details']['widget'][$child]['subform']['field_school']['widget']['#options'] = [];
        }

      }
    }
  }

  /**
   * Callback to fill the `field_location` label values.
   */
  public function alterCshsLabels(array &$form, FormStateInterface $form_state) {
    $miniNode = $form_state->getFormObject()->getEntity();
    $currLocationId = $form_state->getValue('field_location')[0]['target_id'] ?? $miniNode->get('field_location')->getString() ?? NULL;
    if ($currLocationId) {
      $labels = [];
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $loadParent = $term_storage->loadAllParents($currLocationId);
      // If the field is categorization field,
      // i.e; Nargiya Nikae/ Gram Panchayat.
      if (count($loadParent) == 3) {
        $term = $term_storage->load($currLocationId);
        if ($term instanceof TermInterface) {
          $type_of_area_value = $term->get('field_type_of_area')->value ?? NULL;
        }
      }
      // For the next field just after categorization field.
      elseif (count($loadParent) == 4) {
        $term = $term_storage->load($currLocationId);
        if ($term instanceof TermInterface) {
          $adjacent_parent = $term_storage->loadParents($currLocationId);
          $adjacent_parent = reset($adjacent_parent);
          if ($adjacent_parent instanceof TermInterface) {
            $type_of_area_value = $adjacent_parent->get('field_type_of_area')->value ?? NULL;
          }
        }
      }
      // For the last field.
      elseif (count($loadParent) == 5) {
        $term = $term_storage->load($currLocationId);
        if ($term instanceof TermInterface) {
          $adjacent_parent = $term_storage->loadParents($currLocationId);
          $prev_adjacent_parent = $term_storage->loadParents(array_key_first($adjacent_parent));
          $prev_adjacent_parent = reset($prev_adjacent_parent);
          if ($prev_adjacent_parent instanceof TermInterface) {
            $type_of_area_value = $prev_adjacent_parent->get('field_type_of_area')->value ?? NULL;
          }
        }
      }
      // Get the core config.
      $location_schema_config = $this->configFactory()->get('rte_mis_core.settings')->get('location_schema');
      $location_schema_tree = $term_storage->loadTree('location_schema', 0, NULL, FALSE);
      // Get the categorization id.
      $categorization_term_id = $location_schema_config[$type_of_area_value] ?? NULL;
      $label_children = $term_storage->loadTree('location_schema', $categorization_term_id, NULL, TRUE);
      foreach ($label_children as $term) {
        $filteredOption = array_values(array_filter($location_schema_tree, function ($obj) use ($term) {
          return ($term->id() == $obj->tid);
        }))[0] ?? NULL;
        if ($filteredOption) {
          $labels[$filteredOption->depth] = $filteredOption->name;
        }
      }
      $existing_label = $form['field_location']['widget'][0]['target_id']['#labels'];
      $labels = array_merge($existing_label, $labels);
      // Sort the array based on depth.
      ksort($labels, 1);
      // Add the labels.
      $form['field_location']['widget'][0]['target_id']['#labels'] = $labels;
    }
  }

  /**
   * Ajax callback to reset school preference.
   */
  public function multiEventAjaxWrapper(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    // Get the triggering element.
    $triggeringElement = $form_state->getTriggeringElement();
    $name = $triggeringElement['#name'] ?? '';
    $triggeringParent = NestedArray::getValue($form, array_slice($triggeringElement['#array_parents'] ?? [], 0, 1));
    // Mark the current tab as active before retuning the wrapper as ajax
    // callback resets active tab.
    $groupId = isset($form[$triggeringParent['#group']]) ? $form[$triggeringParent['#group']]['#id'] : NULL;
    $form[$triggeringParent['#group']]['#open'] = TRUE;
    $form['group_tabs']['#default_tab'] = $groupId;
    $form['group_tabs']['group_tabs__active_tab']['#value'] = $groupId;
    $items = $values['items'];
    // Reset the row in table to none.
    if (!empty($items) && !in_array($name, [
      'field_has_siblings', 'field_siblings_details_siblings_add_more', 'checkbox',
    ])) {
      $form['field_school_preference_wrapper']['items'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('School Name'),
          $this->t('UDISE Code'),
          $this->t('Medium'),
          $this->t('RTE Seat'),
          $this->t('Entry Class'),
          $this->t('Selected'),
        ],
        '#empty' => $this->t('Please search and re-select the school again.'),
      ];
    }
    return $form;
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
  }

  /**
   * Custom submit handler for mini_node student_detail.
   */
  public function customSchoolDetailSubmitHandler(array &$form, FormStateInterface $form_state) {
    $roles = \Drupal::currentUser()->getRoles();
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
          $medium = $schoolPreference['medium_machine_name'] ?? NULL;
          $entryClass = $schoolPreference['entry_class_machine_name'] ?? NULL;
          if ($status) {
            $paragraph = Paragraph::create([
              'type' => 'school_preference',
              'field_school_id' => [
                'target_id' => $id,
              ],
              'field_medium' => $medium,
              'field_entry_class' => $entryClass,
            ]);
            $paragraph->save();
            $targetIds[] = [
              'target_id' => $paragraph->id(),
              'target_revision_id' => $paragraph->getRevisionId(),
            ];
          }
        }
      }
      $miniNode->set('field_school_preferences', $targetIds);

      if (empty($miniNode->get('field_student_application_number')->getString())) {
        $year = date('Y');
        $dob = $miniNode->get('field_date_of_birth')->date->format('my');
        $number = str_pad($miniNode->id(), 4, '0', STR_PAD_LEFT);
        // Create application number RTE | 2024 | MMYY | 0011.
        $code = "RTE$year$dob$number";
        $miniNode->set('field_student_application_number', $code);
      }
      // Make the transition to submit state.
      // This is only applicable for anonymous user.
      if ($miniNode->hasField('field_student_verification') && in_array('anonymous', $roles)) {
        // Get the current state.
        $current_sid = workflow_node_current_state($miniNode, 'field_student_verification');
        if ($current_sid == 'student_workflow_incomplete') {
          $transition = WorkflowTransition::create([
            0 => $current_sid,
            'field_name' => 'field_student_verification',
          ]);
          // Set the target entity.
          $transition->setTargetEntity($miniNode);
          // Set the target state with require details.
          $transition->setValues('student_workflow_submitted', $this->currentUser()->id(), $this->time->getRequestTime(), $this->t('Submitted by User'));
          // Execute the transition and update the student_details entity.
          $transition->executeAndUpdateEntity();
        }
      }
      if (in_array('block_admin', $roles)) {
        $random = new Random();
        setcookie('student-token', $random->string(20), 1, '/', NULL, TRUE, TRUE);
        setcookie('student-phone', $random->string(20), 1, '/', NULL, TRUE, TRUE);
      }
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
   * Get the table rows with the available school list.
   */
  public function updateSchoolPreferenceElement(&$form, FormStateInterface $form_state, $availableSchools) {
    // Prepare the table row.
    if (!empty($availableSchools)) {
      $selectedSchoolPreference = [];
      // Get the entity, mainly used to fetch value in edit mode.
      $miniNode = $form_state->getFormObject()->getEntity();
      $items = $form_state->getValue('items');
      // Get the school id from field. Used in edit form.
      $paragraphIds = $miniNode->get('field_school_preferences')->getValue() ?? [];
      if (!empty($paragraphIds)) {
        // Flatten the array.
        $paragraphIds = array_column($paragraphIds, 'target_id');
        $selectedSchoolPreference = $this->getParagraphValues($paragraphIds);
      }
      $rteMisSchool = $this->config('rte_mis_school.settings') ?? [];
      $medium = $rteMisSchool->get('field_default_options.field_medium') ?? NULL;
      $classOption = $rteMisSchool->get('field_default_options.class_level') ?? [];
      // Sort the school bases on values in form_state, already selected
      // school(entity edit) in available school list.
      $availableSchools = $this->sortPreferenceSchool($selectedSchoolPreference, $availableSchools, $items);
      foreach ($availableSchools as $key => $availableSchool) {
        $status = $items[$key]['status'] ?? NULL;
        $form['field_school_preference_wrapper']['items'][$key] = [
          '#attributes' => [
            'class' => 'draggable',
          ],
          'label' => ['#plain_text' => $availableSchool['name']],
          'udise_code' => [
            '#plain_text' => $availableSchool['udise_code'],
          ],
          'medium' => [
            '#plain_text' => $medium[$availableSchool['medium']],
          ],
          'rte_seat' => [
            '#plain_text' => $availableSchool['rte_seat'],
          ],
          'entry_class' => [
            '#plain_text' => $classOption[$availableSchool['entry_class']] ?? NULL,
          ],
          'status' => [
            '#type' => 'checkbox',
            '#default_value' => $status ?? !empty($selectedSchoolPreference) ? (!empty(array_filter($selectedSchoolPreference, fn($item) => $item['id'] == $availableSchool['id'] && $item['medium'] == $availableSchool['medium'])) ? TRUE : FALSE) : FALSE,
            '#ajax' => [
              'callback' => [$this, 'fetchSchoolPreferenceAjaxCallback'],
              'wrapper' => 'school-preference-wrapper',
              'progress' => [
                'type' => 'fullscreen',
              ],
            ],
          ],
          'weight' => [
            '#type' => 'weight',
            '#title_display' => 'invisible',
            '#default_value' => $key,
            '#attributes' => [
              'class' => [
                'draggable-weight',
                'group-order-weight',
              ],
            ],
          ],
          'id' => [
            '#type' => 'hidden',
            '#value' => $availableSchool['id'],
          ],
          'medium_machine_name' => [
            '#type' => 'hidden',
            '#value' => $availableSchool['medium'],
          ],
          'entry_class_machine_name' => [
            '#type' => 'hidden',
            '#value' => $availableSchool['entry_class'],
          ],
        ];
      }
    }
    else {
      $form['field_school_preference_wrapper']['items']['#empty'] = $this->t('Sorry, There are no school available for the selected Gender, Date of birth and Location.');
    }
  }

  /**
   * Sort the school preference based on entity id.
   */
  private function sortPreferenceSchool($selected_school_preference, $available_schools, $items) {
    $sorted_schools = [];
    if (!empty($items) && !empty($available_schools)) {
      foreach ($items as $key => $preferenceSchoolDetails) {
        if (isset($available_schools[$key])) {
          $sorted_schools[$key] = $available_schools[$key];
          unset($available_schools[$key]);
        }
      }
    }
    if (!empty($selected_school_preference) && !empty($available_schools)) {
      foreach ($selected_school_preference as $preferenceSchoolDetails) {
        $filter_schools = array_filter($available_schools, fn($item) => ($item['id'] == $preferenceSchoolDetails['id'] ?? NULL) && ($item['medium'] == $preferenceSchoolDetails['medium'] ?? NULL));
        if (!empty($filter_schools)) {
          $key = key($filter_schools);
          $sorted_schools[$key] = current($filter_schools);
          unset($available_schools[$key]);
        }
      }
    }

    return ($sorted_schools + $available_schools);
  }

  /**
   * Get the paragraph `school_preference` value.
   */
  private function getParagraphValues($paragraph_ids = []) {
    $school_preference = [];
    if (!empty($paragraph_ids)) {
      foreach ($paragraph_ids as $paragraph_id) {
        $paragraph = Paragraph::load($paragraph_id);
        if ($paragraph instanceof ParagraphInterface) {
          $school_preference[] = [
            'medium' => $paragraph->get('field_medium')->getString(),
            'id' => $paragraph->get('field_school_id')->getString(),
          ];
        }
      }
    }
    return $school_preference;
  }

  /**
   * This method filter the school, if it match the certain criteria.
   */
  private function filterSchool($student_gender = NULL, $eligible_classes = [], $school_education_type = NULL, $school_entry_class = NULL) {
    if (!empty($student_gender) && !empty($eligible_classes) && !empty($school_education_type) && is_numeric($school_entry_class)) {
      // Match the eligible class with school entry class being offered.
      if (in_array($school_entry_class, $eligible_classes)) {
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
