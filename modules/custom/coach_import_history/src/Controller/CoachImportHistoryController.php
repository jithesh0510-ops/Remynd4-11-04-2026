<?php

namespace Drupal\coach_import_history\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\Response;
use Drupal\user\Entity\User;

class CoachImportHistoryController extends ControllerBase {

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
      'file' => $this->t('File'),
      'details' => $this->t('Details'),
      'operations' => $this->t('Operations'),
    ];

    $rows = [];

    if ($connection->schema()->tableExists('coach_import_history')) {
      $results = $connection->select('coach_import_history', 'c')
        ->fields('c')
        ->orderBy('id', 'DESC')
        ->execute();

      foreach ($results as $record) {
        $details_markup = 'No updates';

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

        $file_markup = '-';
        if (!empty($record->file_uri)) {
          $uri = $record->file_uri;
          if (strpos($uri, 'public://') === 0) {
            $relative = substr($uri, strlen('public://'));
            $file_url = '/sites/default/files/' . str_replace('%2F', '/', rawurlencode($relative));
            $file_markup = '<a href="' . htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8') . '" target="_blank">Download</a>';
          }
          else {
            $file_markup = htmlspecialchars(basename($uri), ENT_QUOTES, 'UTF-8');
          }
        }
        elseif (!empty($record->filename)) {
          $file_markup = htmlspecialchars($record->filename, ENT_QUOTES, 'UTF-8');
        }

        $delete_url = Url::fromRoute('coach_import_history.delete', ['id' => $record->id])->toString();
        $delete_markup = '<a class="button" style="background:#d9534f;border-color:#d9534f;color:#fff;" href="' . htmlspecialchars($delete_url, ENT_QUOTES, 'UTF-8') . '" onclick="return confirm(\'Delete this import history row?\');">Delete</a>';

        $rows[] = [
          'id' => $record->id ?? '-',
          'company' => !empty($record->company_name) ? $record->company_name : (!empty($record->company_uid) ? $record->company_uid : '-'),
          'filename' => $record->filename ?? '-',
          'created_count' => $record->created_count ?? 0,
          'updated_count' => $record->updated_count ?? 0,
          'error_count' => $record->error_count ?? 0,
          'created' => !empty($record->created) ? \Drupal::service('date.formatter')->format($record->created, 'custom', 'Y-m-d - H:i:s') : '-',
          'file' => ['data' => ['#markup' => $file_markup]],
          'details' => ['data' => ['#markup' => $details_markup]],
          'operations' => ['data' => ['#markup' => $delete_markup]],
        ];
      }
    }

    $new_import_url = Url::fromRoute('coach_import_history.import_form')->toString();

    $build['new_import'] = [
      '#markup' => '<p><a class="button button--primary" style="display:inline-block;margin-bottom:15px;" href="' . htmlspecialchars($new_import_url, ENT_QUOTES, 'UTF-8') . '">New Import</a></p>',
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No coach import history found.'),
    ];

    return $build;
  }

  public function downloadSample() {
    $csv = "email,first_name,last_name,name\nsample@example.com,John,Doe,John Doe\n";
    $response = new Response($csv);
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="coach_import_sample.csv"');
    return $response;
  }

  public function downloadKey($company_uid = 0) {
    $company_uid = (int) $company_uid;
    $rows = [];

    if ($company_uid > 0) {
      $connection = Database::getConnection();

      $query = $connection->select('profile', 'p');
      $query->fields('p', ['uid']);
      $query->innerJoin('profile__field_company', 'fc', 'fc.entity_id = p.profile_id');
      $query->condition('fc.field_company_target_id', $company_uid);

      $or = $query->orConditionGroup()
        ->condition('p.type', 'coach')
        ->condition('p.type', 'coach_profile');
      $query->condition($or);

      $uids = $query->execute()->fetchCol();
      $uids = array_unique(array_map('intval', $uids));

      if (!empty($uids)) {
        $coach_uids = \Drupal::entityQuery('user')
          ->accessCheck(FALSE)
          ->condition('uid', $uids, 'IN')
          ->condition('status', 1)
          ->condition('roles', 'coach')
          ->execute();

        foreach ($coach_uids as $uid) {
          $user = User::load($uid);
          if (!$user) {
            continue;
          }

          $first_name = $user->hasField('field_first_name') && !$user->get('field_first_name')->isEmpty()
            ? (string) $user->get('field_first_name')->value : '';

          $last_name = $user->hasField('field_last_name') && !$user->get('field_last_name')->isEmpty()
            ? (string) $user->get('field_last_name')->value : '';

          $name = $user->hasField('field_name') && !$user->get('field_name')->isEmpty()
            ? (string) $user->get('field_name')->value
            : trim($first_name . ' ' . $last_name);

          $rows[] = [
            (string) $user->getEmail(),
            $first_name,
            $last_name,
            $name,
          ];
        }
      }
    }

    $csv = "email,first_name,last_name,name\n";
    foreach ($rows as $row) {
      $csv .= implode(',', array_map(function ($v) {
        $v = (string) $v;
        $v = str_replace('"', '""', $v);
        return '"' . $v . '"';
      }, $row)) . "\n";
    }

    $filename = 'coach_key_' . $company_uid . '.csv';

    $response = new Response($csv);
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    return $response;
  }

  public function deleteImport($id) {
    $connection = Database::getConnection();

    if ($connection->schema()->tableExists('coach_import_history')) {
      $connection->delete('coach_import_history')
        ->condition('id', (int) $id)
        ->execute();
      \Drupal::messenger()->addStatus('Import history row deleted.');
    }

    return $this->redirect('coach_import_history.import_history');
  }

}
