<?php

declare(strict_types=1);

namespace Drupal\crs_migrate\Plugin\migrate\process;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resolves a paragraph UUID from crs_sync_content_map (paragraph target).
 */
#[MigrateProcess('crs_paragraph_uuid_from_content_map')]
final class ParagraphUuidFromContentMap extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $map_type = (string) ($this->configuration['map_type'] ?? '');
    if ($map_type === '') {
      throw new MigrateSkipRowException('crs_paragraph_uuid_from_content_map requires map_type.');
    }
    $legacy_id = (int) $value;
    if ($legacy_id <= 0) {
      throw new MigrateSkipRowException(sprintf('Invalid legacy id for %s.', $map_type));
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
      throw new MigrateSkipRowException(sprintf('No content map for %s legacy_id=%d.', $map_type, $legacy_id));
    }
    $storage = $this->entityTypeManager->getStorage('paragraph');
    $paragraph = $storage->load($target_id);
    if (!$paragraph) {
      throw new MigrateSkipRowException(sprintf('Paragraph %d missing for map %s.', $target_id, $map_type));
    }
    return $paragraph->uuid();
  }

}
