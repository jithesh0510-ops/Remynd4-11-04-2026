<?php

/**
 * @file
 * Removes extension entries that no longer exist on disk (fixes update.php errors).
 *
 * Usage: ddev drush php:script scripts/repair-core-extension.php
 */

use Drupal\Core\Config\ConfigFactoryInterface;

$remove_modules = [
  'coach_csv_import',
  'coach_import_history',
  'company_coach_download',
  'company_dropdown',
  'company_import_history',
  'email_otp_login',
  'employee_csv_import',
  'employee_import_history',
  'history_page_ui',
  'mail_login',
  'mailer_transport',
  'mobile_otp_login',
  'remynd4_user_type_form',
  'smtp',
  'symfony_mailer',
  'user_csv_download',
];

$remove_themes = [
  'remind4_admin_kit_initial',
];

/** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
$config_factory = \Drupal::service('config.factory');
$editable = $config_factory->getEditable('core.extension');

$modules = $editable->get('module') ?: [];
$themes = $editable->get('theme') ?: [];

$removed_m = [];
foreach ($remove_modules as $name) {
  if (array_key_exists($name, $modules)) {
    unset($modules[$name]);
    $removed_m[] = $name;
  }
}

$removed_t = [];
foreach ($remove_themes as $name) {
  if (array_key_exists($name, $themes)) {
    unset($themes[$name]);
    $removed_t[] = $name;
  }
}

if (!$removed_m && !$removed_t) {
  echo "Nothing to remove; core.extension already matches.\n";
  return;
}

$editable->set('module', $modules)->set('theme', $themes)->save();

if ($removed_m) {
  echo 'Removed modules: ' . implode(', ', $removed_m) . "\n";
}
if ($removed_t) {
  echo 'Removed themes: ' . implode(', ', $removed_t) . "\n";
}

echo "Saved core.extension. Run: ddev drush cr\n";
