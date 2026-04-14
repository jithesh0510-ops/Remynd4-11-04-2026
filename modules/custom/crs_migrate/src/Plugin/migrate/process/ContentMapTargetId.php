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
 * Loads target_id from crs_sync_content_map (e.g. questionnaire node id).
 */
#[MigrateProcess('crs_content_map_target')]
final class ContentMapTargetId extends ProcessPluginBase implements ContainerFactoryPluginInterface {

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
      throw new MigrateSkipRowException('crs_content_map_target requires map_type.');
    }
    $legacy_id = (int) $value;
    if ($legacy_id <= 0) {
      throw new MigrateSkipRowException(sprintf('Invalid legacy id for content map %s.', $map_type));
    }
    if (!$this->database->schema()->tableExists('crs_sync_content_map')) {
      throw new MigrateSkipRowException('Table crs_sync_content_map is missing; run crs_sync questionnaire import first.');
    }
    $target_id = (int) $this->database->select('crs_sync_content_map', 'm')
      ->fields('m', ['target_id'])
      ->condition('type', $map_type)
      ->condition('legacy_id', $legacy_id)
      ->execute()
      ->fetchField();
    if ($target_id <= 0) {
      throw new MigrateSkipRowException(sprintf('No crs_sync_content_map row for type=%s legacy_id=%d.', $map_type, $legacy_id));
    }
    return $target_id;
  }

}
