<?php

namespace Drupal\rte_mis_school\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\cshs\Component\CshsOption;
use Drupal\cshs\Element\CshsElement;
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
      // Load the location detail of current user.
      $location = $user->get('field_location_details')->getString();
      if (!empty($location)) {
        // @todo To select the current user location by default.
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
        ],
      ];

      // Populate the school list based on district & block.
      $form['mapping_wrapper']['school_list'] = [
        '#type' => 'select',
        '#title' => $this->t('Available Schools'),
        '#empty_value' => '_none',
        '#options' => $this->getApprovedSchoolList($form_state) ?? [],
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
          '#no_first_level_none' => TRUE,
          '#required' => TRUE,
          '#labels' => $additional_location_info['labels'] ?? [],
          '#options' => $additional_location_info['options'] ?? [],
          '#ajax' => [
            'callback' => '::schoolMappingCallback',
            'wrapper' => 'mapping-wrapper',
            'event' => 'change',
          ],
        ];

      }

      // Populate the school habitation mapping.
      $form['mapping_wrapper']['school_habitation'] = [
        '#type' => 'multiselect',
        '#title' => $this->t('Habitation Mapping'),
        '#options' => $this->getHabitationList($form_state) ?? [],
      ];

      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Submit Mapping'),
      ];

    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Callback function for the school mapping.
   */
  public function schoolMappingCallback(array &$form, FormStateInterface $form_state) {
    return $form['mapping_wrapper'];
  }

  /**
   * Callback function to get the list of all the approved schools.
   */
  protected function getApprovedSchoolList(FormStateInterface $form_state) {
    // Get the target id of the select inital location.
    $user_input = $form_state->getUserInput();
    if (!empty($user_input['initial_location'])) {
      $target_id = $user_input['initial_location'];
      // Load the list of approved schools based on district & block.
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->loadAllParents($target_id);
      if (!empty($term)) {
        // Load the udise information by doing entity query.
        // @todo Get the list of approved schools.
      }

    }

    return [];
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
      $child_terms = $term_storage->loadChildren($additional_location);
      foreach ($child_terms as $term) {
        // Check if the terms are not having any child then only show the
        // habitation list.
        $child = $term_storage->getChildren($term);
        if (empty($child)) {
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

          // Load all the child elements of the inital location option.
          $initial_location_child_terms = $term_storage->loadTree('location', $initial_location, NULL, TRUE);
          if (!empty($initial_location_child_terms)) {
            // Process all term and create the option for cshs element.
            foreach ($initial_location_child_terms as $term) {
              $filteredOption = array_values(array_filter($location_tree, function ($obj) use ($depth, $term, $type_of_area) {
                // Add the filter of type of area.
                $term_type_of_area = $term->get('field_type_of_area')->getString() ?? NULL;
                return ($term->id() == $obj->id()) && ($obj->depth == $depth) && ($type_of_area === $term_type_of_area);
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
