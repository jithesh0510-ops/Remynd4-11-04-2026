<?php

declare(strict_types=1);

namespace Drupal\crs_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Employee questionnaire sessions from qs_emp_questionnaire_filling_master.
 *
 * Column names vary between legacy DBs; this plugin picks the first match per
 * role from common aliases. Override via source configuration if needed:
 * - id_column: PK column (default: auto-detect among filling_id, id, …)
 *
 * Source IDs use the real PK column name plus alias "f" so SqlBase can join
 * migrate_map ON f.<pk> = map.sourceid1 (aliases like source_pk break that join).
 *
 * @MigrateSource(
 *   id = "crs_emp_questionnaire_filling_session_source",
 *   source_module = "crs_migrate"
 * )
 */
final class EmpQuestionnaireFillingSession extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $table = 'qs_emp_questionnaire_filling_master';
    $schema = $this->getDatabase()->schema();
    if (!$schema->tableExists($table)) {
      throw new \Drupal\migrate\MigrateException(sprintf('Legacy table %s does not exist.', $table));
    }

    $id_col = $this->resolveIdColumn();
    if ($id_col === '' || !$schema->fieldExists($table, $id_col)) {
      throw new \Drupal\migrate\MigrateException(sprintf('Could not detect primary key column on %s. Set source.id_column in migration YAML.', $table));
    }

    $employee_col = $this->firstExistingColumn($table, ['employee_id', 'emp_id', 'user_id', 'employee_master_id']);
    $company_col = $this->firstExistingColumn($table, ['company_id', 'comp_id', 'company_master_id']);
    $q_col = $this->firstExistingColumn($table, ['questionnaire_id', 'questionnaire_master_id', 'q_id']);
    $coach_col = $this->firstExistingColumn($table, ['coach_id', 'coach_master_id', 'assigned_coach_id']);

    if ($employee_col === NULL || $company_col === NULL || $q_col === NULL) {
      throw new \Drupal\migrate\MigrateException(sprintf(
        'Table %s is missing required columns (need employee, company, questionnaire). Found: id=%s emp=%s co=%s q=%s.',
        $table,
        $id_col,
        $employee_col ?? '—',
        $company_col ?? '—',
        $q_col ?? '—'
      ));
    }

    $alias = 'f';
    $q = $this->select($table, $alias);
    $q->addField($alias, $id_col);
    // Match crs_sync_legacy_map (employee): resolve business employee_id to qs_employee_master.id when possible.
    if ($schema->tableExists('qs_employee_master')) {
      $q->leftJoin('qs_employee_master', 'em', "(em.id = {$alias}.{$employee_col} OR em.employee_id = {$alias}.{$employee_col})");
      $q->addExpression("COALESCE(em.id, CAST({$alias}.{$employee_col} AS UNSIGNED))", 'employee_id');
    }
    else {
      $q->addField($alias, $employee_col, 'employee_id');
    }
    $q->addField($alias, $company_col, 'company_id');
    $q->addField($alias, $q_col, 'questionnaire_id');

    if ($coach_col !== NULL) {
      $q->addField($alias, $coach_col, 'coach_id');
    }
    else {
      $q->addExpression('0', 'coach_id');
    }

    $fill_col = $this->firstExistingColumn($table, ['fill_date', 'filling_date', 'fill_dt', 'date_filled']);
    $submitted_for_fill = $this->firstExistingColumn($table, ['submitted', 'submitted_on', 'submitted_time']);
    if ($fill_col !== NULL) {
      if ($submitted_for_fill !== NULL) {
        $q->addExpression(
          "COALESCE(NULLIF(TRIM({$alias}.{$fill_col}), ''), DATE_FORMAT(FROM_UNIXTIME({$alias}.{$submitted_for_fill}), '%Y-%m-%d'))",
          'fill_date'
        );
      }
      else {
        $q->addExpression("NULLIF(TRIM({$alias}.{$fill_col}), '')", 'fill_date');
      }
    }
    elseif ($submitted_for_fill !== NULL) {
      $q->addExpression("DATE_FORMAT(FROM_UNIXTIME({$alias}.{$submitted_for_fill}), '%Y-%m-%d')", 'fill_date');
    }
    else {
      $q->addExpression("''", 'fill_date');
    }

    $created_col = $this->firstExistingColumn($table, ['created', 'created_on', 'created_time']);
    $submitted_col = $this->firstExistingColumn($table, ['submitted', 'submitted_on', 'submitted_time']);

    if ($created_col !== NULL && $submitted_col !== NULL) {
      $q->addExpression("COALESCE(NULLIF({$alias}.{$created_col}, 0), {$alias}.{$submitted_col})", 'created');
    }
    elseif ($created_col !== NULL) {
      $q->addField($alias, $created_col, 'created');
    }
    elseif ($submitted_col !== NULL) {
      $q->addField($alias, $submitted_col, 'created');
    }
    else {
      $q->addExpression('0', 'created');
    }

    if ($submitted_col !== NULL) {
      $q->addField($alias, $submitted_col, 'submitted');
    }
    else {
      $q->addExpression('0', 'submitted');
    }

    return $q;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $pk = $this->resolveIdColumn();
    return [
      $pk => $this->t('Primary key from filling master (@col).', ['@col' => $pk]),
      'employee_id' => $this->t('Legacy employee id.'),
      'company_id' => $this->t('Legacy company id.'),
      'questionnaire_id' => $this->t('Legacy questionnaire id.'),
      'coach_id' => $this->t('Legacy coach id (0 = resolve from company).'),
      'fill_date' => $this->t('Fill date Y-m-d.'),
      'created' => $this->t('Created unix time.'),
      'submitted' => $this->t('Submitted unix time.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $id_col = $this->resolveIdColumn();
    return [
      $id_col => [
        'type' => 'integer',
        'unsigned' => TRUE,
        'alias' => 'f',
      ],
    ];
  }

  /**
   * Physical PK column on qs_emp_questionnaire_filling_master for map joins.
   */
  private function resolveIdColumn(): string {
    $table = 'qs_emp_questionnaire_filling_master';
    $schema = $this->getDatabase()->schema();
    if (!$schema->tableExists($table)) {
      return 'filling_id';
    }
    $id_col = $this->configuration['id_column'] ?? NULL;
    if (!is_string($id_col) || $id_col === '') {
      $id_col = $this->firstExistingColumn($table, [
        'filling_id',
        'emp_questionnaire_filling_id',
        'id',
        'filling_master_id',
      ]);
    }
    return $id_col ?? 'filling_id';
  }

  private function firstExistingColumn(string $table, array $candidates): ?string {
    $schema = $this->getDatabase()->schema();
    foreach ($candidates as $col) {
      if ($schema->fieldExists($table, $col)) {
        return $col;
      }
    }
    return NULL;
  }

}
