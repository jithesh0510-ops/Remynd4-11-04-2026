<?php

namespace Drupal\crs_sync\Commands;

use Drush\Commands\DrushCommands;
use Drupal\crs_sync\Sync\MigrationFieldAudit;
use Drupal\crs_sync\Sync\MigrationResetService;
use Drupal\crs_sync\Sync\SyncManager;

class CrsSyncCommands extends DrushCommands {

  public function __construct(
    protected SyncManager $syncManager,
    protected MigrationResetService $migrationReset,
    protected MigrationFieldAudit $fieldAudit,
  ) {
    parent::__construct();
  }

  /**
   * Sync users from the legacy database.
   *
   * @command crs:sync-users
   * @aliases crs-sync
   * @param string $type The type to sync: companies|coaches|employees|all
   */
  public function syncUsers(string $type = 'all') {
    $allowed = ['companies', 'coaches', 'employees', 'all'];
    if (!in_array($type, $allowed, TRUE)) {
      $this->logger()->error(dt('Unknown type: @t', ['@t' => $type]));
      return;
    }

    switch ($type) {
      case 'companies':
        $c = $this->syncManager->syncCompanies();
        $this->logger()->success(dt('Companies synced: @c', ['@c' => $c]));
        break;
      case 'coaches':
        $c = $this->syncManager->syncCoaches();
        $this->logger()->success(dt('Coaches synced: @c', ['@c' => $c]));
        break;
      case 'employees':
        $c = $this->syncManager->syncEmployees();
        $this->logger()->success(dt('Employees synced: @c', ['@c' => $c]));
        break;
      case 'all':
        $total = 0;
        $total += $this->syncManager->syncCompanies();
        $total += $this->syncManager->syncCoaches();
        $total += $this->syncManager->syncEmployees();
        $this->logger()->success(dt('All user types synced. Total records: @c', ['@c' => $total]));
        break;
    }
  }

  /**
   * Remove questionnaire nodes, related paragraphs, legacy maps, and migrated users.
   *
   * @command crs:purge
   * @option yes Skip confirmation (same as -y).
   * @usage drush crs:purge -y
   */
  public function purge(array $options = ['yes' => FALSE]): void {
    if (empty($options['yes'])) {
      if (!$this->io()->confirm(dt('Delete questionnaires, assignment paragraphs, crs_sync map tables, and users with company/coach/employee roles (not uid 1)?'))) {
        $this->logger()->notice(dt('Aborted.'));
        return;
      }
    }
    $summary = $this->migrationReset->purgeAll();
    foreach ($summary as $key => $value) {
      $this->output()->writeln(dt('@k: @v', ['@k' => $key, '@v' => $value]));
    }
    $this->logger()->success(dt('CRS purge finished.'));
  }

  /**
   * Print legacy DB connection info and whether qs_company_master exists.
   *
   * @command crs:legacy-test
   */
  public function legacyTest(): void {
    $d = $this->syncManager->legacyDiagnostics();
    $this->output()->writeln($d['detail']);
    if ($d['ok']) {
      $this->logger()->success(dt('Legacy database OK.'));
    }
    else {
      $this->logger()->error(dt('Legacy database check failed.'));
    }
  }

  /**
   * List user/profile fields required by crs_sync that are missing from config.
   *
   * @command crs:verify-fields
   */
  public function verifyFields(): void {
    $missing = $this->fieldAudit->getMissingRequiredFields();
    if (!$missing) {
      $this->logger()->success(dt('All required fields for crs_sync are present.'));
      return;
    }
    foreach ($missing as $line) {
      $this->logger()->warning($line);
    }
    $this->logger()->error(dt('Fix field configuration (or run config import) before migrating.'));
  }

}
