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
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Inserts coach_reporting_session_answer rows.
 */
#[MigrateDestination(
  id: 'crs_coach_reporting_session_answer',
  requirements_met: TRUE,
  destination_module: 'coach_reporting_system',
)]
final class CoachReportingSessionAnswer extends DestinationBase implements ContainerFactoryPluginInterface {

  protected $supportsRollback = TRUE;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, private readonly Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
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
      'id' => [
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
      'sid' => 'Session id (coach_reporting_session.sid).',
      'step_uuid' => 'Questionnaire paragraph UUID.',
      'row_uuid' => 'Question row paragraph UUID.',
      'value' => 'Stored option key / value.',
      'created' => 'Created unix timestamp.',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    if (!$this->database->schema()->tableExists('coach_reporting_session_answer')) {
      throw new \Drupal\migrate\MigrateException('Table coach_reporting_session_answer does not exist. Run database updates for coach_reporting_system.');
    }
    $dest = $row->getDestination();
    $fields = [
      'sid' => (int) ($dest['sid'] ?? 0),
      'step_uuid' => (string) ($dest['step_uuid'] ?? ''),
      'row_uuid' => (string) ($dest['row_uuid'] ?? ''),
      'value' => (string) ($dest['value'] ?? ''),
      'created' => (int) ($dest['created'] ?? \Drupal::time()->getRequestTime()),
    ];
    if ($fields['sid'] <= 0 || $fields['step_uuid'] === '' || $fields['row_uuid'] === '' || $fields['value'] === '') {
      return FALSE;
    }
    if (!empty($old_destination_id_values['id'])) {
      $id = (int) $old_destination_id_values['id'];
      $this->database->update('coach_reporting_session_answer')
        ->fields($fields)
        ->condition('id', $id)
        ->execute();
      return [$id];
    }
    $id = (int) $this->database->insert('coach_reporting_session_answer')
      ->fields($fields)
      ->execute();
    return [$id];
  }

  /**
   * {@inheritdoc}
   */
  public function rollback(array $destination_identifier) {
    $id = (int) ($destination_identifier['id'] ?? 0);
    if ($id > 0) {
      $this->database->delete('coach_reporting_session_answer')
        ->condition('id', $id)
        ->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function rollbackAction() {
    return MigrateIdMapInterface::ROLLBACK_DELETE;
  }

}
