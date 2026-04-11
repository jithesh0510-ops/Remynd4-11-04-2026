# Config import (cim) – pre-import steps

Before running `ddev drush cim -y`, run these once so the import can succeed:

## 1. Match site UUID to sync storage

If the site UUID does not match the config in `config/sync/system.site.yml`, set it:

```bash
ddev drush config:set system.site uuid 4299c273-e727-45e1-ac14-2657659b5e9c -y
```

(Use the `uuid` value from `config/sync/system.site.yml`.)

## 2. Remove default shortcut set

Existing shortcut entities block the import. Delete the default shortcut set:

```bash
ddev drush ev '\Drupal::entityTypeManager()->getStorage("shortcut_set")->load("default")->delete();'
```

## 3. Run config import

```bash
ddev drush cim -y
```

---

**Summary one-liner** (after a fresh DB or when you see UUID/shortcut errors):

```bash
ddev drush config:set system.site uuid 4299c273-e727-45e1-ac14-2657659b5e9c -y && \
ddev drush ev '\Drupal::entityTypeManager()->getStorage("shortcut_set")->load("default")->delete();' && \
ddev drush cim -y
```
