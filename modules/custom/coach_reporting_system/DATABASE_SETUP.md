# Database Setup for Coach Reporting System

## Missing Tables Error

If you're getting the error:
```
SQLSTATE[42S02]: Base table or view not found: 1146 Table 'database_name.qs_employee_prepost_relation' doesn't exist
```

This means the required database tables are missing. Follow one of the solutions below:

## Solution 1: Run SQL Script (Recommended)

1. Open your database management tool (phpMyAdmin, MySQL Workbench, etc.)
2. Run the SQL commands from `create_tables.sql` file
3. The tables will be created with proper structure and indexes

## Solution 2: Run PHP Script

1. Navigate to your Drupal root directory
2. Run: `php create_missing_tables.php`
3. The script will check and create missing tables automatically

## Solution 3: Manual Drupal Update

1. Clear Drupal cache: `drush cr`
2. Run: `drush updb`
3. This will execute the install hook and create the tables

## Required Tables

### 1. qs_employee_prepost_relation
Stores pre and post training data for employees.

**Fields:**
- `id` (Primary Key)
- `employee_id` (Employee user ID)
- `pre` (Pre-training grade)
- `post` (Post-training grade)
- `company_id` (Company user ID)
- `questionnaire_id` (Questionnaire node ID)
- `created` (Created timestamp)

### 2. qs_emp_lagard_starts
Stores on-the-job progress data for employees.

**Fields:**
- `lagard_starts_id` (Primary Key)
- `employee_id` (Employee user ID)
- `target_forecasted` (Target forecasted value)
- `target_achieved` (Target achieved value)
- `created` (Month/period identifier)
- `company_id` (Company user ID)
- `questionnaire_id` (Questionnaire node ID)

## Verification

After creating the tables, you can verify they exist by:

1. Checking your database management tool
2. Running: `drush sqlq "SHOW TABLES LIKE 'qs_%'"`
3. The coaching impact reports should now work without errors

## Troubleshooting

If you still get errors:

1. Check database permissions
2. Ensure the tables were created with the correct structure
3. Check Drupal logs: `drush watchdog:show`
4. Verify the table names match exactly (case-sensitive)

## Data Flow

These tables are used by:

1. **Pre/Post Import Form** - Writes to `qs_employee_prepost_relation`
2. **On-the-Job Progress Form** - Writes to `qs_emp_lagard_starts`
3. **Coaching Impact Report** - Reads from both tables to display performance data

Once the tables are created, all forms and reports should work correctly.
