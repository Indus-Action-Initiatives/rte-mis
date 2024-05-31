<?php

namespace Drupal\rte_mis_school\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\cshs\Component\CshsOption;
use Drupal\cshs\Element\CshsElement;
use Drupal\eck\EckEntityInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This class create a form for school habitation mapping.
 */
class SchoolMappingForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs the service objects.
   *
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, AccountInterface $current_user) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'school_mapping_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Show the location field data and prefilled the data based on the user
    // location details.
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    if ($user instanceof UserInterface) {
      $roles = $user->getRoles();
      // Attach library to restrict district/block admin's locations.
      $form['#attached']['library'][] = 'rte_mis_core/disable_cshs_select';
      // Load the location detail of current user.
      $location = $user->get('field_location_details')->getString();
      if ($user->hasRole('block_admin')) {
        if (!empty($location)) {
          // Update the default initial location in form state user input.
          // This is needed to allow ajax to work on next set of field as
          // location field will be pre-filled with values for block admin.
          $user_input = $form_state->getUserInput();
          $user_input['initial_location'] = $location;
          $form_state->setUserInput($user_input);
        }
        // Pass role to js.
        $form['#attached']['drupalSettings']['role'] = 'block';
      }
      elseif ($user->hasRole('district_admin')) {
        // Pass role to js.
        $form['#attached']['drupalSettings']['role'] = 'district';
      }

      // Get the rte_mis_core settings.
      $configSettings = $this->configFactory()->get('rte_mis_core.settings');
      // Get the categorization setting from config.
      $categorizationDepth = $configSettings->get('location_schema.depth');

      $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
      $locationTerms = $termStorage->loadTree('location', 0, $categorizationDepth, TRUE);

      // Get the list of options that we will show to the user.
      $options = [];
      foreach ($locationTerms as $term) {
        $options[(int) $term->id()] = new CshsOption($term->label(), (int) $term->parent->target_id == 0 ? NULL : $term->parent->target_id);
      }

      $locationParentTerms = $termStorage->loadTree('location_schema', 0, $categorizationDepth, TRUE);
      $labels = [];
      foreach ($locationParentTerms as $term) {
        $labels[] = $term->label();
      }

      // Get the rte_mis_school settings.
      $schoolConfigSettings = $this->configFactory()->get('rte_mis_school.settings');

      $form['mapping_wrapper'] = [
        '#type' => 'fieldset',
        '#attributes' => [
          'id' => ['mapping-wrapper'],
        ],
        '#tree' => FALSE,
      ];

      $form['mapping_wrapper']['initial_location'] = [
        '#type' => CshsElement::ID,
        '#label' => $this->t('Location'),
        '#no_first_level_none' => TRUE,
        '#required' => TRUE,
        '#labels' => $labels,
        '#options' => $options ?? [],
        '#ajax' => [
          'callback' => '::schoolMappingCallback',
          'wrapper' => 'mapping-wrapper',
          'event' => 'change',
          'progress' => ['type' => 'fullscreen'],
        ],
        '#wrapper_attributes' => [
          'class' => ['location-details'],
        ],
        '#default_value' => !empty($location) ? $location : NULL,
      ];
      // Populate the school list based on district & block.
      $form['mapping_wrapper']['school_list'] = [
        '#type' => 'select',
        '#required' => TRUE,
        '#title' => $this->t('Available Schools'),
        '#options' => $this->getApprovedSchoolList($form_state) ?? [],
        '#ajax' => [
          'callback' => '::schoolMappingCallback',
          'wrapper' => 'mapping-wrapper',
          'event' => 'change',
          'progress' => ['type' => 'fullscreen'],
        ],
      ];

      // Urban or Rural select list.
      $form['mapping_wrapper']['type_of_area'] = [
        '#type' => 'select',
        '#required' => TRUE,
        '#title' => $this->t('Type of Area'),
        '#options' => $schoolConfigSettings->get('field_default_options.field_type_of_area') ?? [],
        '#ajax' => [
          'callback' => '::schoolMappingCallback',
          'wrapper' => 'mapping-wrapper',
          'event' => 'change',
          'progress' => ['type' => 'fullscreen'],
        ],
      ];

      // Show the additional location information only if all the other info
      // is provided by the end user.
      $user_input = $form_state->getUserInput() ?? [];
      if (count($user_input) > 0
        && !empty($user_input['initial_location'])
        && !empty($user_input['type_of_area'])) {
        // Get the additional location information to show the list of
        // habitation.
        $additional_location_info = $this->getAdditionalLocationInfo($form_state) ?? [];
        $form['mapping_wrapper']['additional_location'] = [
          '#type' => CshsElement::ID,
          '#label' => $this->t('Additional Location'),
          '#labels' => $additional_location_info['labels'] ?? [],
          '#options' => $additional_location_info['options'] ?? [],
          '#ajax' => [
            'callback' => '::schoolMappingCallback',
            'wrapper' => 'mapping-wrapper',
            'event' => 'change',
            'progress' => ['type' => 'fullscreen'],
          ],
        ];

      }

      // Populate the school habitation mapping.
      $habitations_list = $this->getHabitationList($form_state);
      if (!empty($habitations_list)) {
        $form['mapping_wrapper']['school_habitation'] = [
          '#type' => 'multiselect',
          '#title' => $this->t('Habitation Mapping'),
          '#options' => $habitations_list ?? [],
          '#default_value' => $this->getExistingHabitationMappings($form_state) ?? [],
        ];
      }

      // Hide the submit button and logs markup for district admin.
      if (!array_intersect($roles, ['district_admin', 'state_admin'])) {
        $form['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Submit Mapping'),
        ];
        $form['mapping_html'] = [
          '#type' => 'markup',
          '#weight' => 100,
          '#markup' => $this->t('Looking for the habitation mapping logs? @url', [
            '@url' => Link::fromTextAndUrl('Click Here', Url::fromRoute('view.school_mapping_logs.page_1', [], [
              'attributes' => [
                'target' => '_blank',
              ],
            ]))->toString(),
          ]),
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate if the current user location & the location selected in the form
    // is same.
    $initial_location = $form_state->getValue('initial_location');
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    if ($user instanceof UserInterface) {
      // Load the location detail of current user.
      $location = $user->get('field_location_details')->getString();
      if (!empty($location)) {
        // Check if both the locations are same or not.
        if ($location !== $initial_location) {
          $form_state->setErrorByName('initial_location', $this->t('Invalid location configured.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get the list of selected options of habitation and store the same in
    // habitation reference field of school.
    $school_habitations = $form_state->getValue('school_habitation');
    $approved_school = $form_state->getValue('school_list');
    if (!empty($approved_school)) {
      // Load the school mini node.
      $school = $this->entityTypeManager->getStorage('mini_node')->load($approved_school);
      if ($school instanceof EckEntityInterface) {
        $target_ids = [];
        $existing = [];
        // Get the list of existing habitations, So that we can track if any of
        // the existing habitation mapping is getting removed or not.
        $existing_habitation = $school->get('field_habitations');
        if (!empty($existing_habitation)) {
          $existing_habitation = $existing_habitation->getValue();
          foreach ($existing_habitation as $habitation) {
            $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($habitation['target_id']);
            if ($term instanceof TermInterface) {
              $existing[] = $term->label();
            }
          }
        }

        $new_habitation = [];
        // Get the list of updated habitations.
        foreach ($school_habitations as $habitation) {
          $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($habitation);
          if ($term instanceof TermInterface) {
            $target_ids[] = ['target_id' => $habitation];
            $new_habitation[] = $term->label();
          }
        }

        $this->logger('Mapping Changes')->info($this->t('The habitation mapping for @school is changed from @existing to @new', [
          '@school' => $school->get('field_school_name')->getString(),
          '@existing' => implode(',', $existing),
          '@new' => implode(',', $new_habitation),
        ]));

        $school->set('field_habitations', $target_ids);
        $school->save();
      }
    }
    // Show the message saying the habitation mapping is saved.
    $this->messenger()->addStatus($this->t('Habitation mapping is saved successfully.'));
  }

  /**
   * Callback function for the school mapping.
   */
  public function schoolMappingCallback(array &$form, FormStateInterface $form_state) {
    return $form['mapping_wrapper'];
  }

  /**
   * Callback function to get the list of existing habitation mapping.
   */
  protected function getExistingHabitationMappings(FormStateInterface $form_state) {
    $school_habitations = [];
    $user_input = $form_state->getUserInput();
    $approved_school = $user_input['school_list'] ?? [];
    if (!empty($approved_school)
      && !empty($user_input['initial_location'])
      && !empty($user_input['additional_location'])) {
      $school = $this->entityTypeManager->getStorage('mini_node')->load($approved_school);
      if ($school instanceof EckEntityInterface) {
        $habitations = $school->get('field_habitations');
        if (!empty($habitations)) {
          $habitations = $habitations->getValue();
          // Traverse throught the list of habitations and prepare the options.
          foreach ($habitations as $habitation) {
            // Load the habitation term and get the name.
            $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($habitation['target_id']);
            if ($term instanceof TermInterface) {
              $school_habitations[] = $habitation['target_id'];
            }
          }
        }
      }
    }

    return $school_habitations;
  }

  /**
   * Callback function to get the list of all the approved schools.
   */
  protected function getApprovedSchoolList(FormStateInterface $form_state) {
    // Get the target id of the select inital location.
    $user_input = $form_state->getUserInput();
    $approved_schools = [];

    if (!empty($user_input['initial_location'])) {
      $target_id = $user_input['initial_location'];
      // Load the list of approved schools based on district & block.
      $schools = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
        'vid' => 'school',
        'field_location' => $target_id,
      ]);

      $school_items = [];
      if (!empty($schools)) {
        // Get the list of all the schools which are tagged to desired location.
        foreach ($schools as $school) {
          $school_items[] = $school->id();
        }
      }

      // Now filter out the list of schools which are approved.
      foreach ($school_items as $school_udise) {
        $school_node = $this->entityTypeManager->getStorage('mini_node')->loadByProperties([
          'field_udise_code' => $school_udise,
          'status' => 1,
        ]);

        if (!empty($school_node)) {
          $school_node = reset($school_node);
          // Check if the school is approved.
          $approved = $school_node->get('field_school_verification')->getString();
          if ($approved === 'school_registration_verification_approved_by_deo') {
            $approved_schools[$school_node->id()] = $school_node->get('field_school_name')->getString();
          }
        }
      }

    }

    return $approved_schools;
  }

  /**
   * Callback function to get the list of applicable habitation.
   */
  protected function getHabitationList(FormStateInterface $form_state) {
    // Get the type of area selected by the user.
    $user_input = $form_state->getUserInput();
    $type_of_area = $user_input['type_of_area'] ?? NULL;
    $additional_location = $user_input['additional_location'] ?? NULL;

    $options = [];
    if (!empty($type_of_area) && !empty($additional_location)) {
      // Get the rte_mis_core settings.
      $configSettings = $this->configFactory()->get('rte_mis_core.settings');
      // Get the urban & rural term information.
      $area_map['urban'] = $configSettings->get('location_schema.urban');
      $area_map['rural'] = $configSettings->get('location_schema.rural');

      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $location_categorization_tree = $term_storage->loadTree('location_schema', $area_map[$type_of_area]);
      // Get the child with the maximum depth and compare the same with the
      // location term items.
      $max_depth = 0;
      foreach ($location_categorization_tree as $term) {
        if ($term->depth > $max_depth) {
          $max_depth = $term->depth;
        }
      }
      // Adding the parent height in the max depth.
      $max_depth += 1;

      // Load the full location schema tree to calculate the actual depth of the
      // term.
      $location_schema_tree = $term_storage->loadTree('location_schema');
      foreach ($location_schema_tree as $term) {
        if ($term->tid == $area_map[$type_of_area]) {
          $max_depth += $term->depth;
        }
      }

      // Load all the terms of location to get the depth of the items.
      $location_tree = $term_storage->loadTree('location');
      $term_depth = [];
      foreach ($location_tree as $term) {
        $term_depth[$term->tid] = $term->depth;
      }

      $child_terms = $term_storage->loadChildren($additional_location);
      foreach ($child_terms as $term) {
        // Check if the terms are not having any child then only show the
        // habitation list.
        $child = $term_storage->getChildren($term);
        if (empty($child) && $term_depth[$term->id()] === $max_depth) {
          $options[(int) $term->id()] = $term->label();
        }
      }
    }

    return $options;
  }

  /**
   * Callback function to get the data for additional location field.
   */
  protected function getAdditionalLocationInfo(FormStateInterface $form_state) {
    // Get the type of area selected by the user.
    $user_input = $form_state->getUserInput();
    $type_of_area = $user_input['type_of_area'] ?? NULL;
    $initial_location = $user_input['initial_location'] ?? NULL;

    $options = [
      'options' => [],
      'labels' => [],
    ];

    // Return from here if any of the data is missing.
    if (empty($type_of_area) || empty($initial_location)) {
      return $options;
    }

    if (!empty($type_of_area)) {
      // Get the rte_mis_core settings.
      $configSettings = $this->configFactory()->get('rte_mis_core.settings');
      // Get the urban & rural term information.
      $area_map['urban'] = $configSettings->get('location_schema.urban');
      $area_map['rural'] = $configSettings->get('location_schema.rural');

      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

      $location_schema_tree = $term_storage->loadTree('location_schema');
      // Load `location` vocabulary.
      $location_tree = $term_storage->loadTree('location', 0, NULL, TRUE);
      $schema_term_info = [];
      foreach ($location_schema_tree as $term) {
        // Also store the depth in the same array, will be used in location.
        $schema_term_info[$term->tid] = [
          'depth' => $term->depth,
        ];
      }
      // Get the depth information of the urban term.
      $term = $term_storage->loadByProperties([
        'vid' => 'location_schema',
        'tid' => $area_map[$type_of_area],
      ]);
      $term = reset($term);

      if ($term instanceof TermInterface) {
        $depth = $schema_term_info[$term->id()]['depth'];
        // Add the current term in the options list.
        $options['labels'][] = $term->label();
        if (!empty($depth)) {
          // Load all the child elements of schema to get the label info.
          $child_terms = $term_storage->loadChildren($term->id());
          foreach ($child_terms as $term) {
            // Don't add the last element in the label as we will show the
            // habitation list in a separate select list.
            $child = $term_storage->getChildren($term);
            if (!empty($child)) {
              $options['labels'][] = $term->label();
            }
          }

          // Fetch the terms that are tagged as U/R based on selected term.
          $location_categorization_terms = $term_storage->loadByProperties([
            'vid' => 'location',
            'field_type_of_area' => $type_of_area,
            'parent' => $initial_location,
          ]);

          $unprocessed_location_terms = $location_categorization_terms;

          if (!empty($location_categorization_terms)) {
            // Fetch all the children of the U/R selected in previous step.
            foreach ($location_categorization_terms as $term) {
              $location_child_terms = $term_storage->loadTree('location', $term->id(), NULL, TRUE);
              $unprocessed_location_terms = array_merge($unprocessed_location_terms, $location_child_terms);
            }
            // Process all term and create the option for cshs element.
            foreach ($unprocessed_location_terms as $term) {
              $filteredOption = array_values(array_filter($location_tree, function ($obj) use ($depth, $term) {
                return ($term->id() == $obj->id()) && ($obj->depth == $depth);
              }))[0] ?? NULL;
              // We will have to remove the parent target id for the elements at
              // the categorization depth.
              if ($filteredOption) {
                $options['options'][(int) $filteredOption->id()] = new CshsOption($filteredOption->label());
              }
              elseif ($term_storage->getChildren($term)) {
                $options['options'][(int) $term->id()] = new CshsOption($term->label(), (int) $term->parent->target_id == 0 ? NULL : $term->parent->target_id);
              }
            }
          }
        }
      }
    }

    return $options;
  }

}
