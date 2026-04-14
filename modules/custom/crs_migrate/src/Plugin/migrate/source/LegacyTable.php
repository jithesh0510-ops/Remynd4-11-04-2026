<?php

declare(strict_types=1);

namespace Drupal\crs_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Reads rows from a single legacy table using Drupal's database Select API.
 *
 * Replaces migrate_plus "sql" for simple SELECTs (no JOINs). Configure:
 * - table: base table name
 * - alias: (optional) table alias, default "t"
 * - fields: map of source column => property name on the row (alias)
 * - ids: Migrate ID map definitions (same shape as migrate_plus sql source)
 * - expressions: (optional) list of { expression, alias } passed to addExpression().
 *   Expression SQL is trusted (module YAML only); use for scalar subqueries, etc.
 *
 * @MigrateSource(
 *   id = "crs_legacy_table",
 *   source_module = "crs_migrate"
 * )
 */
final class LegacyTable extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $table = (string) ($this->configuration['table'] ?? '');
    if ($table === '') {
      throw new \Drupal\migrate\MigrateException('crs_legacy_table source requires a "table" configuration key.');
    }
    $alias = (string) ($this->configuration['alias'] ?? 't');
    $fields = $this->configuration['fields'] ?? [];
    if (!is_array($fields) || !$fields) {
      throw new \Drupal\migrate\MigrateException('crs_legacy_table source requires a non-empty "fields" map (column => row property name).');
    }
    $query = $this->select($table, $alias);
    foreach ($fields as $column => $property_name) {
      $column = (string) $column;
      $property_name = (string) $property_name;
      $query->addField($alias, $column, $property_name !== $column ? $property_name : NULL);
    }
    foreach ($this->configuration['expressions'] ?? [] as $item) {
      if (!is_array($item)) {
        continue;
      }
      $expr = (string) ($item['expression'] ?? '');
      $as = (string) ($item['alias'] ?? '');
      if ($expr !== '' && $as !== '') {
        $query->addExpression($expr, $as);
      }
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $out = [];
    foreach ($this->configuration['fields'] ?? [] as $column => $property_name) {
      $out[(string) $property_name] = $this->t('Column @col', ['@col' => (string) $column]);
    }
    foreach ($this->configuration['expressions'] ?? [] as $item) {
      if (!is_array($item)) {
        continue;
      }
      $as = (string) ($item['alias'] ?? '');
      if ($as !== '') {
        $out[$as] = $this->t('Expression @a', ['@a' => $as]);
      }
    }
    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids = $this->configuration['ids'] ?? [];
    return is_array($ids) ? $ids : [];
  }

}
