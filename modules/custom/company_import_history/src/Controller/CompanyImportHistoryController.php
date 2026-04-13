<?php

namespace Drupal\company_import_history\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\Response;

class CompanyImportHistoryController extends ControllerBase {

  public function listing() {
    $connection = Database::getConnection();

    $header = [
      'id' => $this->t('ID'),
      'company' => $this->t('Company'),
      'filename' => $this->t('File Name'),
      'created_count' => $this->t('Created'),
      'updated_count' => $this->t('Updated'),
      'error_count' => $this->t('Errors'),
      'created' => $this->t('Imported On'),
      'details' => $this->t('Details'),
      'operations' => $this->t('Operations'),
    ];

    $rows = [];

    if ($connection->schema()->tableExists('company_import_history')) {
      $results = $connection->select('company_import_history', 'c')
        ->fields('c')
        ->orderBy('id', 'DESC')
        ->execute();

      foreach ($results as $record) {
        $details_markup = '-';

        if (!empty($record->result_json)) {
          $decoded = Json::decode($record->result_json);
          if (is_array($decoded) && !empty($decoded)) {
            $items = [];
            foreach ($decoded as $item) {
              if (is_array($item)) {
                $row = $item['row'] ?? '';
                $email = $item['email'] ?? '';
                $result = $item['result'] ?? '';
                $message = $item['message'] ?? '';
                $line = trim("Row {$row} | {$email} | {$result} | {$message}");
                if ($line !== '') {
                  $items[] = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
                }
              }
              elseif (is_string($item) && trim($item) !== '') {
                $items[] = htmlspecialchars(trim($item), ENT_QUOTES, 'UTF-8');
              }
            }

            if (!empty($items)) {
              $details_markup = '<details><summary><strong>View</strong></summary><div style="white-space:pre-wrap;max-width:700px;">' . implode("<br>", $items) . '</div></details>';
            }
          }
        }

        $delete_url = Url::fromRoute('company_import_history.delete', ['id' => $record->id])->toString();
        $delete_markup = '<a class="button" style="background:#d9534f;border-color:#d9534f;color:#fff;" href="' . htmlspecialchars($delete_url, ENT_QUOTES, 'UTF-8') . '" onclick="return confirm(\'Delete this import history row?\');">Delete</a>';

        $rows[] = [
          'id' => $record->id ?? '-',
          'company' => !empty($record->company_name) ? $record->company_name : (!empty($record->company_uid) ? $record->company_uid : '-'),
          'filename' => $record->filename ?? '-',
          'created_count' => $record->created_count ?? 0,
          'updated_count' => $record->updated_count ?? 0,
          'error_count' => $record->error_count ?? 0,
          'created' => !empty($record->created) ? \Drupal::service('date.formatter')->format($record->created, 'short') : '-',
          'details' => ['data' => ['#markup' => $details_markup]],
          'operations' => ['data' => ['#markup' => $delete_markup]],
        ];
      }
    }

    $new_import_url = Url::fromRoute('company_import_history.import_form')->toString();

    $build['new_import'] = [
      '#markup' => '<p><a class="button button--primary" style="display:inline-block;margin-bottom:15px;" href="' . htmlspecialchars($new_import_url, ENT_QUOTES, 'UTF-8') . '">New Import</a></p>',
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No company import history found.'),
    ];

    return $build;
  }

  public function downloadSample() {
    $csv = "email,first_name,last_name,name\nsample@example.com,John,Doe,John Doe\n";

    $response = new Response($csv);
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="company_import_sample.csv"');

    return $response;
  }

  public function downloadKey($company_uid = 0) {
    return $this->redirect('company_import_history.import_form');
  }

  public function deleteImport($id) {
    $connection = Database::getConnection();

    if ($connection->schema()->tableExists('company_import_history')) {
      $connection->delete('company_import_history')
        ->condition('id', (int) $id)
        ->execute();
      \Drupal::messenger()->addStatus('Import history row deleted.');
    }

    return $this->redirect('company_import_history.import_history');
  }

}
