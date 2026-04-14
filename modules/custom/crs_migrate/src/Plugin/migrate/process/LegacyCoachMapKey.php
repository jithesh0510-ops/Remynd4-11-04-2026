<?php

declare(strict_types=1);

namespace Drupal\crs_migrate\Plugin\migrate\process;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Normalizes a submission “coach” reference to the legacy id stored in crs_sync_legacy_map.
 *
 * crs_sync syncCoaches() maps type=coach with legacy_id = (int) (qs_coach_master.coach_id
 * ?? qs_coach_master.id). Legacy submission tables may store coach_id, row id, or a
 * user id column on qs_coach_master — this plugin resolves via qs_coach_master first.
 */
#[MigrateProcess('crs_legacy_coach_map_key')]
final class LegacyCoachMapKey extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly Connection $legacyDb,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      Database::getConnection('default', 'legacy'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $raw = (int) $value;
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

}
