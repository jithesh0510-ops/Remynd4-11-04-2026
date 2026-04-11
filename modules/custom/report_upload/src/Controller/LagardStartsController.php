<?php

namespace Drupal\report_upload\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LagardStartsController extends ControllerBase {

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
      $this->t('Month'),
      $this->t('Target Forecasted'),
      $this->t('Target Achieved'),
      $this->t('Actions'),
    ];

    $query = $this->database->select('qs_emp_lagard_starts', 'l')
      ->fields('l')
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit(20);

    $results = $query->execute();
    $rows = [];

    foreach ($results as $record) {
      $edit_link = Link::fromTextAndUrl($this->t('Edit'),
        Url::fromRoute('report_upload.lagard_starts_edit', ['id' => $record->id])
      )->toString();

      $rows[] = [
        $record->id,
        $record->employee_uid,
        $record->company_uid,
        $record->questionnaire_id,
        $record->month,
        $record->target_forecasted,
        $record->target_achieved,
        $edit_link,
      ];
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No Lagard Starts records found.'),
      'pager' => ['#type' => 'pager'],
    ];
  }

  public function bulkDelete() {
    $ids = \Drupal::request()->request->get('ids');
    if (!empty($ids) && is_array($ids)) {
      $this->database->delete('qs_emp_lagard_starts')
        ->condition('id', $ids, 'IN')
        ->execute();
      $this->messenger()->addStatus($this->t('Selected records deleted.'));
    }
    return $this->redirect('report_upload.lagard_starts_list');
  }
}
