<?php
/**
 * Simple import script to create sample taxonomy terms and users.
 *
 * Run with (from project root):
 *   lando drush php:script scripts/import_sample_data.php
 *
 * The script:
 *  - creates two district terms and two block terms (if missing)
 *  - ensures 'school' and 'student' roles exist (create if missing)
 *  - creates two sample school users and two sample student users (if missing)
 *
 * Adjust names/emails/passwords below as needed.
 */

use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Create term in a vocabulary if it does not exist.
 */
function ensure_term($vid, $name) {
  $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
    'vid' => $vid,
    'name' => $name,
  ]);
  if (!empty($terms)) {
    $term = reset($terms);
    print "Term '$name' in vocab '$vid' already exists (tid: {$term->id()}).\n";
    return $term;
  }
  $term = Term::create([
    'name' => $name,
    'vid' => $vid,
    'status' => 1,
  ]);
  $term->save();
  print "Created term '$name' in vocab '$vid' (tid: {$term->id()}).\n";
  return $term;
}

/**
 * Ensure role exists (create if missing).
 */
function ensure_role($role_id, $label) {
  $role = Role::load($role_id);
  if ($role) {
    print "Role '$role_id' already exists.\n";
    return $role;
  }
  $role = Role::create([
    'id' => $role_id,
    'label' => $label,
  ]);
  $role->save();
  print "Created role '$role_id'.\n";
  return $role;
}

/**
 * Ensure a user exists with given username/email; create if missing.
 * Returns the user entity.
 */
function ensure_user(array $values) {
  // $values: ['name'=>, 'mail'=>, 'pass'=>, 'roles'=>[]]
  $existing = user_load_by_mail($values['mail']);
  if ($existing) {
    print "User with email {$values['mail']} already exists (uid: {$existing->id()}).\n";
    // Optionally update roles if missing
    $updated = FALSE;
    foreach ($values['roles'] as $r) {
      if (!in_array($r, $existing->getRoles())) {
        $existing->addRole($r);
        $updated = TRUE;
      }
    }
    if ($updated) {
      $existing->save();
      print "Updated roles for user {$values['mail']}.\n";
    }
    return $existing;
  }

  $user = User::create([
    'name' => $values['name'],
    'mail' => $values['mail'],
    'status' => 1,
    'pass' => $values['pass'],
  ]);
  // Assign roles
  foreach ($values['roles'] as $r) {
    $user->addRole($r);
  }
  $user->save();
  print "Created user {$values['name']} ({$values['mail']}) uid: {$user->id()}.\n";
  return $user;
}

// ---------- Begin import ----------

print "Starting import sample data...\n";

// 1) Terms (adjust vocabulary machine names if yours are different)
$district_vocab = 'district';
$block_vocab = 'block';

// Create sample districts and blocks.
$districtA = ensure_term($district_vocab, 'District A');
$districtB = ensure_term($district_vocab, 'District B');

$block1 = ensure_term($block_vocab, 'Block 1');
$block2 = ensure_term($block_vocab, 'Block 2');

// 2) Roles
ensure_role('school', 'School');
ensure_role('student', 'Student');

// 3) Users: sample schools and students
ensure_user([
  'name' => 'school1',
  'mail' => 'school1@example.com',
  'pass' => 'ChangeMe123!',
  'roles' => ['school'],
]);

ensure_user([
  'name' => 'school2',
  'mail' => 'school2@example.com',
  'pass' => 'ChangeMe123!',
  'roles' => ['school'],
]);

ensure_user([
  'name' => 'student1',
  'mail' => 'student1@example.com',
  'pass' => 'ChangeMe123!',
  'roles' => ['student'],
]);

ensure_user([
  'name' => 'student2',
  'mail' => 'student2@example.com',
  'pass' => 'ChangeMe123!',
  'roles' => ['student'],
]);

print "Sample data import complete. Clear caches if you changed templates or config.\n";
