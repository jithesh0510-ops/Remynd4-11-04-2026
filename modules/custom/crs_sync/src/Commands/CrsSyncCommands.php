<?php

namespace Drupal\crs_sync\Commands;

use Drush\Commands\DrushCommands;
use Drupal\crs_sync\Sync\SyncManager;

class CrsSyncCommands extends DrushCommands {

  protected SyncManager $syncManager;

  public function __construct(SyncManager $sync_manager) {
    parent::__construct();
    $this->syncManager = $sync_manager;
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

}
