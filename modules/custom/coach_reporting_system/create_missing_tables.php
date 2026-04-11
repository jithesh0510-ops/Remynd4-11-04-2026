<?php

/**
 * Script to create missing database tables for coach reporting system.
 * Run this script from the Drupal root directory.
 */

use Drupal\Core\Database\Database;

// Bootstrap Drupal
$autoloader = require_once 'autoload.php';
$kernel = \Drupal\Core\DrupalKernel::createFromRequest(\Symfony\Component\HttpFoundation\Request::createFromGlobals(), $autoloader);
$kernel->boot();
$kernel->preHandle(\Symfony\Component\HttpFoundation\Request::createFromGlobals());

$db = Database::getConnection();

echo "Creating missing database tables...\n";

// Create qs_employee_prepost_relation table
if (!$db->schema()->tableExists('qs_employee_prepost_relation')) {
  $schema = [
    'description' => 'Stores pre and post training data for employees',
    'fields' => [
      'id' => [
        'type' => 'serial',
        'not null' => TRUE,
        'description' => 'Primary key',
      ],
      'employee_id' => [
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => TRUE,
        'description' => 'Employee user ID',
      ],
      'pre' => [
        'type' => 'float',
        'description' => 'Pre-training grade',
      ],
      'post' => [
        'type' => 'float',
        'description' => 'Post-training grade',
      ],
      'company_id' => [
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => TRUE,
        'description' => 'Company user ID',
      ],
      'questionnaire_id' => [
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => TRUE,
        'description' => 'Questionnaire node ID',
      ],
      'created' => [
        'type' => 'int',
        'not null' => TRUE,
        'description' => 'Created timestamp',
      ],
    ],
    'primary key' => ['id'],
    'indexes' => [
      'employee_company' => ['employee_id', 'company_id'],
      'questionnaire' => ['questionnaire_id'],
      'created' => ['created'],
    ],
  ];
  
  $db->schema()->createTable('qs_employee_prepost_relation', $schema);
  echo "✅ Created qs_employee_prepost_relation table\n";
} else {
  echo "ℹ️  qs_employee_prepost_relation table already exists\n";
}

// Create qs_emp_lagard_starts table
if (!$db->schema()->tableExists('qs_emp_lagard_starts')) {
  $schema = [
    'description' => 'Stores on-the-job progress data for employees',
    'fields' => [
      'lagard_starts_id' => [
        'type' => 'serial',
        'not null' => TRUE,
        'description' => 'Primary key',
      ],
      'employee_id' => [
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => TRUE,
        'description' => 'Employee user ID',
      ],
      'target_forecasted' => [
        'type' => 'float',
        'description' => 'Target forecasted value',
      ],
      'target_achieved' => [
        'type' => 'float',
        'description' => 'Target achieved value',
      ],
      'created' => [
        'type' => 'varchar',
        'length' => 255,
        'not null' => TRUE,
        'description' => 'Month/period identifier',
      ],
      'company_id' => [
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => TRUE,
        'description' => 'Company user ID',
      ],
      'questionnaire_id' => [
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => TRUE,
        'description' => 'Questionnaire node ID',
      ],
    ],
    'primary key' => ['lagard_starts_id'],
    'indexes' => [
      'employee_company' => ['employee_id', 'company_id'],
      'questionnaire' => ['questionnaire_id'],
      'created' => ['created'],
    ],
  ];
  
  $db->schema()->createTable('qs_emp_lagard_starts', $schema);
  echo "✅ Created qs_emp_lagard_starts table\n";
} else {
  echo "ℹ️  qs_emp_lagard_starts table already exists\n";
}

echo "\n🎉 Database tables creation completed!\n";
echo "You can now use the coaching impact reports.\n";
