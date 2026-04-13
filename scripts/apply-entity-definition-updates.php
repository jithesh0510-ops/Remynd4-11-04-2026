<?php

/**
 * @file
 * Applies pending entity / field storage definition updates (status report).
 *
 * Usage: ddev drush php:script scripts/apply-entity-definition-updates.php
 */

use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;

$edm = \Drupal::entityDefinitionUpdateManager();
$efm = \Drupal::service('entity_field.manager');
$repository = \Drupal::service('entity.last_installed_schema.repository');

$complete_change_list = $edm->getChangeList();
if (!$complete_change_list) {
  echo "No entity definition updates pending.\n";
  return;
}

\Drupal::entityTypeManager()->clearCachedDefinitions();
$efm->clearCachedFieldDefinitions();
$complete_change_list = $edm->getChangeList();

foreach ($complete_change_list as $entity_type_id => $change_list) {
  if (!empty($change_list['entity_type'])) {
    $op = $change_list['entity_type'];
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $field_storage_definitions = $efm->getFieldStorageDefinitions($entity_type_id);

    if ($op === EntityDefinitionUpdateManagerInterface::DEFINITION_CREATED) {
      \Drupal::service('entity_type.listener')->onEntityTypeCreate($entity_type);
      echo "Entity type created: $entity_type_id\n";
    }
    elseif ($op === EntityDefinitionUpdateManagerInterface::DEFINITION_UPDATED) {
      $original = $repository->getLastInstalledDefinition($entity_type_id);
      $original_field_storage_definitions = $repository->getLastInstalledFieldStorageDefinitions($entity_type_id);
      \Drupal::service('entity_type.listener')->onFieldableEntityTypeUpdate(
        $entity_type,
        $original,
        $field_storage_definitions,
        $original_field_storage_definitions
      );
      echo "Entity type updated: $entity_type_id\n";
    }
  }

  if (!empty($change_list['field_storage_definitions'])) {
    $storage_definitions = $efm->getFieldStorageDefinitions($entity_type_id);
    $original_storage_definitions = $repository->getLastInstalledFieldStorageDefinitions($entity_type_id);

    foreach ($change_list['field_storage_definitions'] as $field_name => $change) {
      $sd = $storage_definitions[$field_name] ?? NULL;
      $osd = $original_storage_definitions[$field_name] ?? NULL;
      $listener = \Drupal::service('field_storage_definition.listener');

      switch ($change) {
        case EntityDefinitionUpdateManagerInterface::DEFINITION_CREATED:
          $listener->onFieldStorageDefinitionCreate($sd);
          echo "Field storage created: $entity_type_id.$field_name\n";
          break;

        case EntityDefinitionUpdateManagerInterface::DEFINITION_UPDATED:
          $listener->onFieldStorageDefinitionUpdate($sd, $osd);
          echo "Field storage updated: $entity_type_id.$field_name\n";
          break;

        case EntityDefinitionUpdateManagerInterface::DEFINITION_DELETED:
          $listener->onFieldStorageDefinitionDelete($osd);
          echo "Field storage deleted: $entity_type_id.$field_name\n";
          break;
      }
    }
  }
}

if (!\Drupal::entityDefinitionUpdateManager()->needsUpdates()) {
  echo "Done. Run: ddev drush cr\n";
}
else {
  echo "Warning: further updates may be required; check /admin/reports/status\n";
}
