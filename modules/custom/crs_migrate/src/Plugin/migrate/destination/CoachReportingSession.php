<?php

declare(strict_types=1);

namespace Drupal\crs_migrate\Plugin\migrate\destination;

use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateDestination;
use Drupal\migrate\Plugin\migrate\destination\DestinationBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Row;

/**
 * Inserts/updates coach_reporting_session rows.
 */
#[MigrateDestination(
  id: 'crs_coach_reporting_session',
  requirements_met: TRUE,
  destination_module: 'coach_reporting_system',
)]
final class CoachReportingSession extends DestinationBase implements ContainerFactoryPluginInterface {

  protected $supportsRollback = TRUE;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, private readonly Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(\Symfony\Component\DependencyInjection\ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'sid' => [
        'type' => 'integer',
        'unsigned' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'coach_uid' => 'Coach Drupal UID.',
      'company_uid' => 'Company Drupal UID.',
      'program_nid' => 'Questionnaire node ID.',
      'employee_uid' => 'Employee Drupal UID.',
      'fill_date' => 'Filling date (Y-m-d).',
      'created' => 'Created unix timestamp.',
      'submitted' => 'Submitted unix timestamp.',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    if (!$this->database->schema()->tableExists('coach_reporting_session')) {
      throw new \Drupal\migrate\MigrateException('Table coach_reporting_session does not exist. Run database updates for coach_reporting_system.');
    }
    $dest = $row->getDestination();
    $fill = $dest['fill_date'] ?? NULL;
    if ($fill === '') {
      $fill = NULL;
    }
    $fields = [
      'coach_uid' => (int) ($dest['coach_uid'] ?? 0),
      'company_uid' => (int) ($dest['company_uid'] ?? 0),
      'program_nid' => (int) ($dest['program_nid'] ?? 0),
      'employee_uid' => (int) ($dest['employee_uid'] ?? 0),
      'fill_date' => $fill,
      'created' => (int) ($dest['created'] ?? \Drupal::time()->getRequestTime()),
      'submitted' => isset($dest['submitted']) && $dest['submitted'] !== '' ? (int) $dest['submitted'] : NULL,
    ];
    if ($fields['coach_uid'] <= 0 || $fields['company_uid'] <= 0 || $fields['program_nid'] <= 0 || $fields['employee_uid'] <= 0) {
      return FALSE;
    }
    if (!empty($old_destination_id_values['sid'])) {
      $sid = (int) $old_destination_id_values['sid'];
      $this->database->update('coach_reporting_session')
        ->fields($fields)
        ->condition('sid', $sid)
        ->execute();
      return [$sid];
    }
    $sid = (int) $this->database->insert('coach_reporting_session')
      ->fields($fields)
      ->execute();
    return [$sid];
  }

  /**
   * {@inheritdoc}
   */
  public function rollback(array $destination_identifier) {
    $sid = (int) ($destination_identifier['sid'] ?? 0);
    if ($sid <= 0) {
      return;
    }
    if ($this->database->schema()->tableExists('coach_reporting_session_answer')) {
      $this->database->delete('coach_reporting_session_answer')
        ->condition('sid', $sid)
        ->execute();
    }
    $this->database->delete('coach_reporting_session')
      ->condition('sid', $sid)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function rollbackAction() {
    return MigrateIdMapInterface::ROLLBACK_DELETE;
  }

}
