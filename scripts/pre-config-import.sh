#!/usr/bin/env bash
# Fixes UUID + shortcut issues then runs config import.
# Run from project root:  ddev exec ./scripts/pre-config-import.sh
# Or from host:           ddev drush config:set system.site uuid 4299c273-e727-45e1-ac14-2657659b5e9c -y && ddev drush ev '\Drupal::entityTypeManager()->getStorage("shortcut_set")->load("default")->delete();' && ddev drush cim -y

set -e
SITE_UUID="4299c273-e727-45e1-ac14-2657659b5e9c"

echo "Setting site UUID to match config/sync..."
drush config:set system.site uuid "$SITE_UUID" -y

echo "Deleting default shortcut set..."
drush ev '\Drupal::entityTypeManager()->getStorage("shortcut_set")->load("default")->delete();'

echo "Running config import..."
drush cim -y

echo "Done."
