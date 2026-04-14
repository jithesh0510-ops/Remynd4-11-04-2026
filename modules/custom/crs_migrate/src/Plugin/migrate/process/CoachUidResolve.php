<?php

declare(strict_types=1);

namespace Drupal\crs_migrate\Plugin\migrate\process;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resolves coach Drupal UID from legacy coach_id or first coach for company.
 *
 * Pipeline: usually preceded by get(source: coach_id). If coach_id is empty/0,
 * picks the first coach_id from qs_company_coach_details for company_id, then
 * crs_sync_legacy_map (type=coach).
 */
#[MigrateProcess('crs_coach_uid_resolve')]
final class CoachUidResolve extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly Connection $drupalDb,
    private readonly Connection $legacyDb,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      Database::getConnection('default', 'legacy'),
    );
  }

  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $company_key = (string) ($this->configuration['company_source'] ?? 'company_id');
    $company_legacy = (int) ($row->getSourceProperty($company_key) ?? 0);
    if ($company_legacy <= 0) {
      throw new MigrateSkipRowException('crs_coach_uid_resolve: missing company legacy id.');
    }

    $coach_legacy = (int) $value;
    if ($coach_legacy <= 0) {
      $coach_legacy = $this->firstCoachIdForCompany($company_legacy);
    }
    $coach_legacy = $this->normalizeCoachLegacyId($coach_legacy);
    if ($coach_legacy <= 0) {
      throw new MigrateSkipRowException(sprintf(
        'crs_coach_uid_resolve: no coach for company legacy_id=%d (set coach_id on the row or ensure qs_company_coach_details + crs_sync_legacy_map).',
        $company_legacy
      ));
    }

    if (!$this->drupalDb->schema()->tableExists('crs_sync_legacy_map')) {
      throw new MigrateSkipRowException('crs_sync_legacy_map is missing.');
    }
    $uid = (int) $this->drupalDb->select('crs_sync_legacy_map', 'm')
      ->fields('m', ['uid'])
      ->condition('type', 'coach')
      ->condition('legacy_id', (string) $coach_legacy)
      ->execute()
      ->fetchField();
    if ($uid <= 0) {
      throw new MigrateSkipRowException(sprintf(
        'crs_coach_uid_resolve: no crs_sync_legacy_map row for coach legacy_id=%d.',
        $coach_legacy
      ));
    }
    return $uid;
  }

  /**
   * Aligns with SyncManager::syncCoaches() map key: qs_coach_master.coach_id ?? id.
   */
  private function normalizeCoachLegacyId(int $raw): int {
    if ($raw <= 0) {
      return $raw;
    }
    if (!$this->legacyDb->schema()->tableExists('qs_coach_master')) {
      return $raw;
    }
    $q = $this->legacyDb->select('qs_coach_master', 'cm');
    $q->fields('cm', ['coach_id', 'id']);
    $group = $q->orConditionGroup();
    $group->condition('cm.coach_id', $raw);
    $group->condition('cm.id', $raw);
    if ($this->legacyDb->schema()->fieldExists('qs_coach_master', 'user_id')) {
      $group->condition('cm.user_id', $raw);
    }
    if ($this->legacyDb->schema()->fieldExists('qs_coach_master', 'uid')) {
      $group->condition('cm.uid', $raw);
    }
    $q->condition($group);
    $q->range(0, 1);
    $found = $q->execute()->fetchObject();
    if (!$found) {
      return $raw;
    }
    $normalized = (int) ($found->coach_id ?? $found->id ?? $raw);
    return $normalized > 0 ? $normalized : $raw;
  }

  /**
   * First coach linked to a company in legacy data (deterministic order).
   */
  private function firstCoachIdForCompany(int $company_legacy): int {
    if (!$this->legacyDb->schema()->tableExists('qs_company_coach_details')) {
      return 0;
    }
    $cid = $this->legacyDb->select('qs_company_coach_details', 'ccd')
      ->fields('ccd', ['coach_id'])
      ->condition('company_id', $company_legacy)
      ->orderBy('coach_id')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    return (int) $cid;
  }

}
