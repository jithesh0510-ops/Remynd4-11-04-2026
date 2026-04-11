# CRS Migrate (Drupal 10/11)

This module provides example migrations for **users** and **profiles** from a legacy MySQL database.
It assumes the following legacy tables:

- `qs_company_master`   → role `company`
- `qs_coach_master`     → role `coach`
- `qs_employee_master`  → role `employee`

> **Adjust the SELECT queries/columns** in the migration YAMLs to match your real schema.

## Setup

1) Add the legacy database connection in `settings.php`:

```php
$databases['legacy']['default'] = [
  'database' => 'LEGACY_DB_NAME',
  'username' => 'LEGACY_DB_USER',
  'password' => 'LEGACY_DB_PASS',
  'host' => '127.0.0.1',
  'port' => '3306',
  'driver' => 'mysql',
  'namespace' => 'Drupal\Core\Database\Driver\mysql',
  'pdo' => [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'],
];
```

2) Ensure **Profile** module bundles exist with these machine names and fields:
- `company` profile: suggested fields: `field_full_name`
- `coach` profile:   fields: `field_full_name`, `field_company` (User reference → company user)
- `employee` profile: fields: `field_full_name`, `field_company` (User ref), `field_coach` (User ref), `field_program` (Node ref, optional)

3) Enable and import config:

```bash
drush en crs_migrate migrate_plus migrate_tools profile -y
drush cim -y
```

4) Run migrations (order matters due to lookups):

```bash
# Users
drush mi crs_users_company
drush mi crs_users_coach
drush mi crs_users_employee

# Profiles (reference users via migration_lookup)
drush mi crs_profile_company
drush mi crs_profile_coach
drush mi crs_profile_employee
```

## Notes
- Passwords are set to a default value (`Temp!ChangeMe123`) for all users. Consider forcing resets.
- The SQL queries alias columns to **expected names**. If your legacy table columns differ, change the `SELECT` parts accordingly.
- For large datasets, use `--limit` and `--feedback` flags with Drush to batch imports.
