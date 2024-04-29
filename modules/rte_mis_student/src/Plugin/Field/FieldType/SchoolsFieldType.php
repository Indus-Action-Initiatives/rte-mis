<?php

namespace Drupal\rte_mis_student\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'schools_weight' field type.
 *
 * @FieldType(
 *   id = "school_weight_field_type",
 *   label = @Translation("School Weight Field"),
 *   description = @Translation("Stores the school id and weight of school."),
 *   default_formatter = "school_weight_field_formatter",
 *   default_widget = "school_weight_widget",
 * )
 */
class SchoolsFieldType extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['school'] = DataDefinition::create('string')
      ->setLabel(t('School'))
      ->setRequired(FALSE);

    $properties['weight'] = DataDefinition::create('integer')
      ->setLabel(t('Weight'))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'school' => [
          'description' => 'School',
          'type' => 'varchar',
          'length' => 255,
        ],
        'weight' => [
          'description' => 'Weight of School',
          'type' => 'int',
          'size' => 'tiny',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $school = $this->get('school')->getValue();
    $weight = $this->get('weight')->getValue();
    return empty($school) || empty($weight);
  }

}
