<?php

namespace Drupal\crs_sync\Sync;

use Drupal\Core\Entity\EntityFieldManagerInterface;

/**
 * Reports missing field instances required by crs_sync / SyncManager.
 */
class MigrationFieldAudit {

  public function __construct(
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * @return string[]
   *   Human-readable messages for each missing field.
   */
  public function getMissingRequiredFields(): array {
    $missing = [];
    $userFields = [
      'field_first_name', 'field_middle_name', 'field_last_name', 'field_full_name',
      'field_phone_no', 'field_is_delete', 'field_address', 'field_address_1', 'field_website',
    ];
    if (\Drupal::moduleHandler()->moduleExists('feeds')) {
      $userFields[] = 'feeds_item';
    }

    $checks = [
      'user' => [
        'user' => $userFields,
      ],
      'profile' => [
        'company' => [
          'field_company_id', 'field_company_name', 'field_no_of_coach',
          'field_no_of_employees', 'field_no_of_password_generated', 'field_select_questionnaire',
        ],
        'coach' => [
          'field_company', 'field_enable_the_coach_will_see', 'field_see_laggards_to_stars',
          'field_see_previous_date', 'field_see_questionnaire_result', 'field_see_skills_assessment',
        ],
        'employee' => [
          'field_company', 'field_coach', 'field_employee_number', 'field_job_position',
          'field_branch', 'field_view_report',
        ],
      ],
    ];

    foreach ($checks['user']['user'] as $field) {
      $defs = $this->entityFieldManager->getFieldDefinitions('user', 'user');
      if (!isset($defs[$field])) {
        $missing[] = "Missing user field: {$field}";
      }
    }

    if (\Drupal::moduleHandler()->moduleExists('profile')) {
      $ptype_storage = \Drupal::entityTypeManager()->getStorage('profile_type');
      foreach ($checks['profile'] as $bundle => $fields) {
        if (!$ptype_storage->load($bundle)) {
          $missing[] = "Missing profile bundle: {$bundle}";
          continue;
        }
        $defs = $this->entityFieldManager->getFieldDefinitions('profile', $bundle);
        foreach ($fields as $field) {
          if (!isset($defs[$field])) {
            $missing[] = "Missing profile.{$bundle} field: {$field}";
          }
        }
      }
    }
    else {
      $missing[] = 'Enable the Profile module; crs_sync requires company, coach, and employee profile bundles.';
    }

    return $missing;
  }

}
