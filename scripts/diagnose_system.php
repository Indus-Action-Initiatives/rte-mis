<?php
<?php

/**
 * @file
 * Diagnostic script to check RTE-MIS system configuration.
 * 
 * Run with: lando drush php:script c:\Users\satis\OneDrive\Documents\Desktop\rtemis\rte-mis\scripts\diagnose_system.php
 */

use Drupal\node\Entity\NodeType;

echo "=== RTE-MIS System Diagnostic ===\n\n";

// 1. Check Content Types
echo "1. CONTENT TYPES (Nodes):\n";
echo str_repeat("-", 50) . "\n";
$node_types = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple();
if (empty($node_types)) {
  echo "   ❌ No content types found\n";
} else {
  foreach ($node_types as $type) {
    echo "   ✅ " . $type->id() . " - " . $type->label() . "\n";
    
    // Check fields
    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $type->id());
    echo "      Fields: ";
    $field_names = [];
    foreach ($fields as $field_name => $field) {
      if (strpos($field_name, 'field_') === 0) {
        $field_names[] = $field_name;
      }
    }
    echo implode(', ', $field_names) ?: "none";
    echo "\n";
  }
}
echo "\n";

// 2. Check ECK Entity Types
echo "2. ECK ENTITY TYPES:\n";
echo str_repeat("-", 50) . "\n";
try {
  $eck_types = \Drupal::entityTypeManager()->getStorage('eck_entity_type')->loadMultiple();
  if (empty($eck_types)) {
    echo "   ⚠️  No ECK entity types found\n";
  } else {
    foreach ($eck_types as $type) {
      echo "   ✅ " . $type->id() . " - " . $type->label() . "\n";
      
      // Check bundles
      try {
        $bundles = \Drupal::entityTypeManager()->getStorage('eck_entity_bundle')->loadMultiple();
        $type_bundles = array_filter($bundles, function($bundle) use ($type) {
          return $bundle->getEntityType()->id() === $type->id();
        });
        
        if (!empty($type_bundles)) {
          echo "      Bundles: ";
          foreach ($type_bundles as $bundle) {
            echo $bundle->id() . ", ";
          }
          echo "\n";
        }
      } catch (\Exception $e) {
        echo "      ❌ Error loading bundles: " . $e->getMessage() . "\n";
      }
    }
  }
} catch (\Exception $e) {
  echo "   ❌ ECK not available: " . $e->getMessage() . "\n";
}
echo "\n";

// 3. Check Taxonomy Vocabularies
echo "3. TAXONOMY VOCABULARIES:\n";
echo str_repeat("-", 50) . "\n";
$vocabularies = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple();
if (empty($vocabularies)) {
  echo "   ❌ No vocabularies found\n";
} else {
  foreach ($vocabularies as $vocab) {
    echo "   ✅ " . $vocab->id() . " - " . $vocab->label() . "\n";
    
    // Count terms
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
      'vid' => $vocab->id(),
    ]);
    echo "      Terms: " . count($terms) . "\n";
  }
}
echo "\n";

// 4. Check User Roles
echo "4. USER ROLES:\n";
echo str_repeat("-", 50) . "\n";
$roles = \Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple();
foreach ($roles as $role) {
  if ($role->id() !== 'anonymous' && $role->id() !== 'authenticated') {
    echo "   ✅ " . $role->id() . " - " . $role->label() . "\n";
  }
}
echo "\n";

// 5. Check Routes
echo "5. CUSTOM ROUTES:\n";
echo str_repeat("-", 50) . "\n";
$route_provider = \Drupal::service('router.route_provider');
$routes = $route_provider->getAllRoutes();
foreach ($routes as $route_name => $route) {
  if (strpos($route_name, 'rte_mis') === 0) {
    echo "   ✅ " . $route_name . " -> " . $route->getPath() . "\n";
  }
}
echo "\n";

// 6. Check Module Status
echo "6. RTE-MIS MODULES:\n";
echo str_repeat("-", 50) . "\n";
$module_handler = \Drupal::service('module_handler');
$rte_modules = ['rte_mis_school', 'eck', 'taxonomy'];
foreach ($rte_modules as $module) {
  $status = $module_handler->moduleExists($module) ? "✅ Enabled" : "❌ Disabled";
  echo "   {$status} {$module}\n";
}
echo "\n";

// 7. Sample Data Counts
echo "7. CURRENT DATA COUNTS:\n";
echo str_repeat("-", 50) . "\n";

// Count nodes by type
$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$types_to_check = ['student_application', 'allocation', 'reimbursement_claim', 'notification'];
foreach ($types_to_check as $type) {
  try {
    $count = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $type)
      ->count()
      ->execute();
    echo "   {$type}: {$count}\n";
  } catch (\Exception $e) {
    // Type doesn't exist
  }
}

// Count taxonomy terms
if (isset($vocabularies['school'])) {
  $school_count = count(\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
    'vid' => 'school',
  ]));
  echo "   schools (taxonomy): {$school_count}\n";
}
echo "\n";

// 8. Recommendations
echo "8. RECOMMENDATIONS:\n";
echo str_repeat("-", 50) . "\n";

if (empty($node_types)) {
  echo "   ⚠️  Create content types for your data\n";
  echo "      - student_application\n";
  echo "      - allocation\n";
  echo "      - reimbursement_claim\n";
  echo "      - notification\n";
}

$has_student_app = false;
foreach ($node_types as $type) {
  if ($type->id() === 'student_application') {
    $has_student_app = true;
  }
}

if (!$has_student_app && empty($eck_types)) {
  echo "   ⚠️  Either create content types OR set up ECK properly\n";
}

if (isset($vocabularies['school'])) {
  $school_count = count(\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
    'vid' => 'school',
  ]));
  if ($school_count === 0) {
    echo "   ⚠️  Add schools to the 'school' vocabulary\n";
  }
}

echo "\n";
echo "=== Diagnostic Complete ===\n";
echo "\nNext steps:\n";
echo "1. Copy the DashboardController.php artifact to your module\n";
echo "2. Run: lando drush cr\n";
echo "3. Visit: https://rte-mis.lndo.site/school/dashboard\n";
```// filepath: c:\Users\satis\OneDrive\Documents\Desktop\rtemis\rte-mis\scripts\diagnose_system.php
<?php

/**
 * @file
 * Diagnostic script to check RTE-MIS system configuration.
 * 
 * Run with: lando drush php:script c:\Users\satis\OneDrive\Documents\Desktop\rtemis\rte-mis\scripts\diagnose_system.php
 */

use Drupal\node\Entity\NodeType;

echo "=== RTE-MIS System Diagnostic ===\n\n";

// 1. Check Content Types
echo "1. CONTENT TYPES (Nodes):\n";
echo str_repeat("-", 50) . "\n";
$node_types = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple();
if (empty($node_types)) {
  echo "   ❌ No content types found\n";
} else {
  foreach ($node_types as $type) {
    echo "   ✅ " . $type->id() . " - " . $type->label() . "\n";
    
    // Check fields
    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $type->id());
    echo "      Fields: ";
    $field_names = [];
    foreach ($fields as $field_name => $field) {
      if (strpos($field_name, 'field_') === 0) {
        $field_names[] = $field_name;
      }
    }
    echo implode(', ', $field_names) ?: "none";
    echo "\n";
  }
}
echo "\n";

// 2. Check ECK Entity Types
echo "2. ECK ENTITY TYPES:\n";
echo str_repeat("-", 50) . "\n";
try {
  $eck_types = \Drupal::entityTypeManager()->getStorage('eck_entity_type')->loadMultiple();
  if (empty($eck_types)) {
    echo "   ⚠️  No ECK entity types found\n";
  } else {
    foreach ($eck_types as $type) {
      echo "   ✅ " . $type->id() . " - " . $type->label() . "\n";
      
      // Check bundles
      try {
        $bundles = \Drupal::entityTypeManager()->getStorage('eck_entity_bundle')->loadMultiple();
        $type_bundles = array_filter($bundles, function($bundle) use ($type) {
          return $bundle->getEntityType()->id() === $type->id();
        });
        
        if (!empty($type_bundles)) {
          echo "      Bundles: ";
          foreach ($type_bundles as $bundle) {
            echo $bundle->id() . ", ";
          }
          echo "\n";
        }
      } catch (\Exception $e) {
        echo "      ❌ Error loading bundles: " . $e->getMessage() . "\n";
      }
    }
  }
} catch (\Exception $e) {
  echo "   ❌ ECK not available: " . $e->getMessage() . "\n";
}
echo "\n";

// 3. Check Taxonomy Vocabularies
echo "3. TAXONOMY VOCABULARIES:\n";
echo str_repeat("-", 50) . "\n";
$vocabularies = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple();
if (empty($vocabularies)) {
  echo "   ❌ No vocabularies found\n";
} else {
  foreach ($vocabularies as $vocab) {
    echo "   ✅ " . $vocab->id() . " - " . $vocab->label() . "\n";
    
    // Count terms
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
      'vid' => $vocab->id(),
    ]);
    echo "      Terms: " . count($terms) . "\n";
  }
}
echo "\n";

// 4. Check User Roles
echo "4. USER ROLES:\n";
echo str_repeat("-", 50) . "\n";
$roles = \Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple();
foreach ($roles as $role) {
  if ($role->id() !== 'anonymous' && $role->id() !== 'authenticated') {
    echo "   ✅ " . $role->id() . " - " . $role->label() . "\n";
  }
}
echo "\n";

// 5. Check Routes
echo "5. CUSTOM ROUTES:\n";
echo str_repeat("-", 50) . "\n";
$route_provider = \Drupal::service('router.route_provider');
$routes = $route_provider->getAllRoutes();
foreach ($routes as $route_name => $route) {
  if (strpos($route_name, 'rte_mis') === 0) {
    echo "   ✅ " . $route_name . " -> " . $route->getPath() . "\n";
  }
}
echo "\n";

// 6. Check Module Status
echo "6. RTE-MIS MODULES:\n";
echo str_repeat("-", 50) . "\n";
$module_handler = \Drupal::service('module_handler');
$rte_modules = ['rte_mis_school', 'eck', 'taxonomy'];
foreach ($rte_modules as $module) {
  $status = $module_handler->moduleExists($module) ? "✅ Enabled" : "❌ Disabled";
  echo "   {$status} {$module}\n";
}
echo "\n";

// 7. Sample Data Counts
echo "7. CURRENT DATA COUNTS:\n";
echo str_repeat("-", 50) . "\n";

// Count nodes by type
$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$types_to_check = ['student_application', 'allocation', 'reimbursement_claim', 'notification'];
foreach ($types_to_check as $type) {
  try {
    $count = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $type)
      ->count()
      ->execute();
    echo "   {$type}: {$count}\n";
  } catch (\Exception $e) {
    // Type doesn't exist
  }
}

// Count taxonomy terms
if (isset($vocabularies['school'])) {
  $school_count = count(\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
    'vid' => 'school',
  ]));
  echo "   schools (taxonomy): {$school_count}\n";
}
echo "\n";

// 8. Recommendations
echo "8. RECOMMENDATIONS:\n";
echo str_repeat("-", 50) . "\n";

if (empty($node_types)) {
  echo "   ⚠️  Create content types for your data\n";
  echo "      - student_application\n";
  echo "      - allocation\n";
  echo "      - reimbursement_claim\n";
  echo "      - notification\n";
}

$has_student_app = false;
foreach ($node_types as $type) {
  if ($type->id() === 'student_application') {
    $has_student_app = true;
  }
}

if (!$has_student_app && empty($eck_types)) {
  echo "   ⚠️  Either create content types OR set up ECK properly\n";
}

if (isset($vocabularies['school'])) {
  $school_count = count(\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
    'vid' => 'school',
  ]));
  if ($school_count === 0) {
    echo "   ⚠️  Add schools to the 'school' vocabulary\n";
  }
}

echo "\n";
echo "=== Diagnostic Complete ===\n";
echo "\nNext steps:\n";
echo "1. Copy the DashboardController.php artifact to your module\n";
echo "2. Run: lando drush cr\n";
echo "3. Visit: https://rte-mis.lndo.site/school/dashboard\n";