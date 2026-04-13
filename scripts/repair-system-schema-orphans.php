<?php

/**
 * @file
 * Removes system.schema key/value rows for modules no longer on disk.
 *
 * Fixes: "Module X has an entry in the system.schema key/value storage,
 * but is missing from your site."
 *
 * Usage: ddev drush php:script scripts/repair-system-schema-orphans.php
 */

$modules = [
  'coach_csv_import',
  'company_dropdown',
  'company_dropdown_filter',
  'company_user',
  'employee_csv_import',
  'remynd4_user_type_form',
  'symfony_mailer',
];

$kv = \Drupal::keyValue('system.schema');
$removed = [];

foreach ($modules as $module) {
  if ($kv->has($module)) {
    $kv->delete($module);
    $removed[] = $module;
  }
}

if ($removed) {
  echo 'Removed system.schema entries: ' . implode(', ', $removed) . "\n";
}
else {
  echo "No matching orphaned system.schema entries found.\n";
}

echo "Run: ddev drush cr\n";
