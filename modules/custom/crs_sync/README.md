# CRS Sync

Custom Drupal 10/11 module to **synchronize users** (companies, coaches, employees) from a legacy PHP database into Drupal user accounts + Profile entities.

## Setup

1. Place this module at `web/modules/custom/crs_sync` and enable it:

```bash
drush en crs_sync -y
drush cr
```

2. Add a legacy database connection called **legacy** in `settings.php`:

```php
$databases['legacy']['default'] = [
  'driver' => 'mysql',
  'database' => 'old_php_db',
  'username' => 'old_user',
  'password' => 'old_pass',
  'host' => '127.0.0.1',
  'port' => '3306',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];
```

3. Ensure the **Profile** module is enabled and you have these profile bundles/fields:

- `company` (bundle)
- `coach` (bundle) with `field_company` (User reference, multiple)
- `employee` (bundle) with `field_company` (User reference) and `field_coach` (User reference, multiple)

## Run

- UI: `/admin/tools/crs-sync`
- Drush:
  ```bash
  drush crs:sync-users companies
  drush crs:sync-users coaches
  drush crs:sync-users employees
  drush crs:sync-users all
  ```

## Legacy tables (expected)

- Companies: `qs_company_master`
- Coaches:   `qs_coach_master`
- Employees: `qs_employee_master`

Columns are auto-detected among common names (`id`, `company_id`, `email`, `name`, etc.). Adjust `SyncManager::pick()` map if your column names differ.
