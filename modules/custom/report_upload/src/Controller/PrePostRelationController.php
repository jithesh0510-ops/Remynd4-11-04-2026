<?php

namespace Drupal\report_upload\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PrePostRelationController extends ControllerBase {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('database'));
  }

  public function list() {
    $header = [
      $this->t('ID'),
      $this->t('Employee'),
      $this->t('Company'),
      $this->t('Program'),
      $this->t('Pre Score'),
      $this->t('Post Score'),
      $this->t('Actions'),
    ];

    $query = $this->database->select('qs_employee_prepost_relation', 'p')
      ->fields('p')
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit(20);

    $results = $query->execute();
    $rows = [];

    foreach ($results as $record) {
      $edit_link = Link::fromTextAndUrl($this->t('Edit'),
        Url::fromRoute('report_upload.prepost_relation_edit', ['id' => $record->id])
      )->toString();

      $rows[] = [
        $record->id,
        $record->employee_uid,
        $record->company_uid,
        $record->questionnaire_id,
        $record->pre_score,
        $record->post_score,
        $edit_link,
      ];
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No PrePost Relation records found.'),
      'pager' => ['#type' => 'pager'],
    ];
  }

  public function bulkDelete() {
    $ids = \Drupal::request()->request->get('ids');
    if (!empty($ids) && is_array($ids)) {
      $this->database->delete('qs_employee_prepost_relation')
        ->condition('id', $ids, 'IN')
        ->execute();
      $this->messenger()->addStatus($this->t('Selected records deleted.'));
    }
    return $this->redirect('report_upload.prepost_relation_list');
  }
}
