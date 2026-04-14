<?php

declare(strict_types=1);

namespace Drupal\crs_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Legacy coach questionnaire answers via Drupal DB API (legacy connection).
 *
 * When `qs_answer_master` exists, answer_text_value is merged with answer_value.
 * When it does not, only `qs_coach_submitted_answer.answer_value` is used.
 *
 * @MigrateSource(
 *   id = "crs_coach_submission_answer_source",
 *   source_module = "crs_migrate"
 * )
 */
final class CoachSubmissionAnswer extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $q = $this->select('qs_coach_submitted_answer', 'a');
    $q->join('qs_coach_submitted_session', 's', 'a.session_id = s.session_id');
    $q->addField('a', 'answer_row_id');
    $q->addField('a', 'session_id');
    $q->addField('s', 'questionnaire_id');
    $q->addField('a', 'question_id');
    $conn = $this->getDatabase();
    if ($conn->schema()->tableExists('qs_answer_master')) {
      $q->leftJoin('qs_answer_master', 'am', 'a.answer_text_id = am.answer_text_id');
      $resolved = "TRIM(BOTH ' ' FROM COALESCE(NULLIF(TRIM(a.answer_value), ''), am.answer_text_value, ''))";
    }
    else {
      // Legacy DB may only have coach submission tables; no join to qs_answer_master.
      $resolved = "TRIM(BOTH ' ' FROM COALESCE(NULLIF(TRIM(a.answer_value), ''), ''))";
    }
    $q->addExpression($resolved, 'answer_value');
    $q->addField('s', 'submitted', 'answer_created');
    $q->where("$resolved <> ''");
    return $q;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'answer_row_id' => $this->t('Legacy answer row id.'),
      'session_id' => $this->t('Legacy session id.'),
      'questionnaire_id' => $this->t('Legacy questionnaire id.'),
      'question_id' => $this->t('Legacy question id.'),
      'answer_value' => $this->t('Resolved answer value.'),
      'answer_created' => $this->t('Unix time for answer row (from session submitted).'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'answer_row_id' => [
        'type' => 'integer',
        'unsigned' => TRUE,
      ],
    ];
  }

}
