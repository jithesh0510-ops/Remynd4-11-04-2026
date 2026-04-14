<?php

declare(strict_types=1);

namespace Drupal\crs_migrate\Plugin\migrate\process;

use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resolves a Drupal UID from crs_sync_legacy_map (coach|company|employee).
 */
#[MigrateProcess('crs_legacy_uid_from_map')]
final class LegacyUidFromMap extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $map_type = (string) ($this->configuration['map_type'] ?? '');
    if ($map_type === '') {
      throw new MigrateSkipRowException('crs_legacy_uid_from_map requires map_type.');
    }
    $legacy_id = (int) $value;
    if ($legacy_id <= 0) {
      throw new MigrateSkipRowException(sprintf('Invalid legacy id for %s.', $map_type));
    }
    if (!$this->database->schema()->tableExists('crs_sync_legacy_map')) {
      throw new MigrateSkipRowException('Table crs_sync_legacy_map is missing; enable crs_sync and import users first.');
    }
    $uid = (int) $this->database->select('crs_sync_legacy_map', 'm')
      ->fields('m', ['uid'])
      ->condition('type', $map_type)
      ->condition('legacy_id', (string) $legacy_id)
      ->execute()
      ->fetchField();
    if ($uid <= 0) {
      throw new MigrateSkipRowException(sprintf('No crs_sync_legacy_map row for type=%s legacy_id=%d.', $map_type, $legacy_id));
    }
    return $uid;
  }

}
