<?php

declare(strict_types=1);

namespace Drupal\crs_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Legacy coach questionnaire sessions via Drupal DB API (legacy connection).
 *
 * @MigrateSource(
 *   id = "crs_coach_submission_session_source",
 *   source_module = "crs_migrate"
 * )
 */
final class CoachSubmissionSession extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $q = $this->select('qs_coach_submitted_session', 's');
    $q->addField('s', 'session_id');
    $q->addField('s', 'coach_id');
    $q->addField('s', 'employee_id');
    $q->addField('s', 'company_id');
    $q->addField('s', 'questionnaire_id');
    $q->addExpression(
      "COALESCE(NULLIF(TRIM(s.fill_date), ''), DATE_FORMAT(FROM_UNIXTIME(s.submitted), '%Y-%m-%d'))",
      'fill_date'
    );
    $q->addExpression('COALESCE(NULLIF(s.created, 0), s.submitted)', 'created');
    $q->addField('s', 'submitted');
    return $q;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'session_id' => $this->t('Legacy session id.'),
      'coach_id' => $this->t('Legacy coach id.'),
      'employee_id' => $this->t('Legacy employee id.'),
      'company_id' => $this->t('Legacy company id.'),
      'questionnaire_id' => $this->t('Legacy questionnaire id.'),
      'fill_date' => $this->t('Fill date (Y-m-d).'),
      'created' => $this->t('Created unix time.'),
      'submitted' => $this->t('Submitted unix time.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'session_id' => [
        'type' => 'integer',
        'unsigned' => TRUE,
      ],
    ];
  }

}
