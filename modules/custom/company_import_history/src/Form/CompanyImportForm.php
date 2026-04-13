<?php

namespace Drupal\company_import_history\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

class CompanyImportForm extends FormBase {

  public function getFormId() {
    return 'company_import_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['enctype'] = 'multipart/form-data';

    $form['company_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Company Name'),
      '#options' => $this->getCompanyOptions(),
      '#empty_option' => $this->t('- Select -'),
      '#required' => FALSE,
    ];

    $form['import_file'] = [
      '#type' => 'file',
      '#title' => $this->t('Select File'),
      '#description' => $this->t('Allowed: csv, xlsx, xls'),
      '#required' => TRUE,
    ];

    $form['send_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send an email'),
      '#default_value' => 0,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
    ];

    $form['actions']['history'] = [
      '#type' => 'link',
      '#title' => $this->t('History'),
      '#url' => Url::fromRoute('company_import_history.import_history'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  protected function getCompanyOptions(): array {
    $options = [];
    $query = \Drupal::entityTypeManager()->getStorage('user')->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1);
    $uids = $query->execute();

    if (!empty($uids)) {
      $users = User::loadMultiple($uids);
      foreach ($users as $user) {
        if ($user->hasField('field_company') && !$user->get('field_company')->isEmpty()) {
          $value = trim((string) $user->get('field_company')->value);
          if ($value !== '') {
            $options[$value] = $value;
          }
        }
        elseif ($user->hasField('field_company_name') && !$user->get('field_company_name')->isEmpty()) {
          $value = trim((string) $user->get('field_company_name')->value);
          if ($value !== '') {
            $options[$value] = $value;
          }
        }
      }
    }

    asort($options);
    return $options;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $validators = ['file_validate_extensions' => ['csv xlsx xls']];
    $file = file_save_upload('import_file', $validators, 'temporary://', 0);

    if (!$file) {
      $form_state->setErrorByName('import_file', $this->t('Please upload a valid file.'));
      return;
    }

    $form_state->set('uploaded_file_realpath', \Drupal::service('file_system')->realpath($file->getFileUri()));
  }

  private function readSheetRows(string $filepath): array {
    $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

    if ($ext === 'csv') {
      $rows = [];
      if (($handle = fopen($filepath, 'r')) !== FALSE) {
        while (($data = fgetcsv($handle)) !== FALSE) {
          $rows[] = $data;
        }
        fclose($handle);
      }
      return $rows;
    }

    if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
      $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filepath);
      return $spreadsheet->getActiveSheet()->toArray(NULL, TRUE, TRUE, FALSE);
    }

    return [];
  }

  private function resolveAttachmentPath(string $filepath = ''): string {
    if (!empty($filepath) && file_exists($filepath)) {
      return $filepath;
    }

    $candidates = [];
    foreach ([
      'sites/default/files/company_import_history',
      'sites/default/files',
      'temporary://',
    ] as $dir) {
      $real = str_starts_with($dir, 'temporary://') ? \Drupal::service('file_system')->realpath($dir) : $dir;
      if ($real && is_dir($real)) {
        foreach (['csv', 'xlsx', 'xls'] as $ext) {
          foreach (glob($real . '/*.' . $ext) ?: [] as $f) {
            if (is_file($f)) {
              $candidates[$f] = filemtime($f);
            }
          }
        }
      }
    }

    if (empty($candidates)) {
      return '';
    }

    arsort($candidates);
    return (string) array_key_first($candidates);
  }

  private function buildAttachments(string $filepath): array {
    $filepath = $this->resolveAttachmentPath($filepath);
    if (!empty($filepath) && file_exists($filepath)) {
      $mime = function_exists('mime_content_type') ? mime_content_type($filepath) : FALSE;
      return [[
        'filepath' => $filepath,
        'filename' => basename($filepath),
        'filemime' => $mime ?: 'application/octet-stream',
      ]];
    }
    return [];
  }

  private function sendCompanyMailToUser(string $to, array $params, string $filepath = ''): void {
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
      return;
    }

    $company_name = $params['company_name'] ?? '';
    $username = $params['username'] ?? '';

    $params['subject'] = $params['subject'] ?? 'Company account created';
    $params['email'] = $params['email'] ?? $to;
    $params['attachments'] = $params['attachments'] ?? $this->buildAttachments($filepath);

    if (empty($params['message'])) {
      $lines = [
        'Hello,',
        '',
        'Your company account has been created.',
      ];
      if ($company_name !== '') {
        $lines[] = '';
        $lines[] = 'Selected company: ' . $company_name;
      }
      if ($username !== '') {
        $lines[] = '';
        $lines[] = 'Username: ' . $username;
      }
      $lines[] = '';
      $lines[] = 'Email: ' . $to;
      $params['message'] = implode("\n", $lines);
    }

    \Drupal::logger('company_import_history')->notice('USER MAIL -> @email | company=@company | file=@file', [
      '@email' => $to,
      '@company' => $company_name,
      '@file' => $this->resolveAttachmentPath($filepath),
    ]);

    company_import_history_send_direct_mail(
      $to,
      $params['subject'],
      $params['message'],
      $params['attachments']
    );
  }

  private function sendCompanyMailToAdmin(string $to, array $params, string $filepath = ''): void {
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
      return;
    }

    $company_name = $params['company_name'] ?? '';
    $params['subject'] = $params['subject'] ?? 'Company Import Notification';
    $params['attachments'] = $params['attachments'] ?? $this->buildAttachments($filepath);

    if (empty($params['message'])) {
      $params['message'] = 'Selected company: ' . ($company_name !== '' ? $company_name : 'N/A');
    }

    \Drupal::logger('company_import_history')->notice('ADMIN MAIL -> company=@company | file=@file', [
      '@company' => $company_name,
      '@file' => $this->resolveAttachmentPath($filepath),
    ]);

    company_import_history_send_direct_mail(
      $to,
      $params['subject'],
      $params['message'],
      $params['attachments']
    );
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected_company = trim((string) $form_state->getValue('company_name'));
    $send_email = (bool) $form_state->getValue('send_email');
    $filepath = (string) $form_state->get('uploaded_file_realpath');

    $rows = $this->readSheetRows($filepath);
    if (empty($rows) || count($rows) < 2) {
      \Drupal::messenger()->addError($this->t('The uploaded file is empty or invalid.'));
      return;
    }

    $header = array_map(fn($item) => strtolower(trim((string) $item)), $rows[0]);

    $email_index = array_search('email', $header);
    $first_name_index = array_search('first_name', $header);
    $last_name_index = array_search('last_name', $header);
    $name_index = array_search('name', $header);

    if ($email_index === FALSE) {
      \Drupal::messenger()->addError($this->t('The file must contain an email column.'));
      return;
    }

    $created = 0;
    $updated = 0;
    $errors = 0;
    $results = [];

    foreach (array_slice($rows, 1) as $i => $row) {
      $row_number = $i + 2;

      $email = isset($row[$email_index]) ? trim((string) $row[$email_index]) : '';
      $first_name = ($first_name_index !== FALSE && isset($row[$first_name_index])) ? trim((string) $row[$first_name_index]) : '';
      $last_name = ($last_name_index !== FALSE && isset($row[$last_name_index])) ? trim((string) $row[$last_name_index]) : '';
      $name = ($name_index !== FALSE && isset($row[$name_index])) ? trim((string) $row[$name_index]) : '';

      if ($email === '') {
        $errors++;
        $results[] = ['row' => $row_number, 'email' => '', 'result' => 'Error', 'message' => 'Email is empty'];
        continue;
      }

      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors++;
        $results[] = ['row' => $row_number, 'email' => $email, 'result' => 'Error', 'message' => 'Invalid email format'];
        continue;
      }

      $existing = user_load_by_mail($email);

      if ($existing) {
        if ($existing->hasField('field_company') && $selected_company !== '') {
          $existing->set('field_company', $selected_company);
        }
        if ($existing->hasField('field_first_name') && $first_name !== '') {
          $existing->set('field_first_name', $first_name);
        }
        if ($existing->hasField('field_last_name') && $last_name !== '') {
          $existing->set('field_last_name', $last_name);
        }
        if ($existing->hasField('field_name') && $name !== '') {
          $existing->set('field_name', $name);
        }

        $existing->save();
        $updated++;

        if ($send_email) {
          $mail_username = $name !== '' ? $name : (strstr($email, '@', TRUE) ?: $email);
          $this->sendCompanyMailToUser(
            $email,
            [
              'subject' => 'Company account created',
              'username' => $mail_username,
              'email' => $email,
              'company_name' => $selected_company,
            ],
            $filepath
          );
        }

        $results[] = ['row' => $row_number, 'email' => $email, 'result' => 'Updated', 'message' => 'Existing company updated'];
      }
      else {
        $username = strstr($email, '@', TRUE) ?: $email;
        $password = user_password();

        $user = User::create([
          'name' => $username,
          'mail' => $email,
          'status' => 1,
          'pass' => $password,
        ]);

        if ($user->hasField('field_company') && $selected_company !== '') {
          $user->set('field_company', $selected_company);
        }
        if ($user->hasField('field_first_name') && $first_name !== '') {
          $user->set('field_first_name', $first_name);
        }
        if ($user->hasField('field_last_name') && $last_name !== '') {
          $user->set('field_last_name', $last_name);
        }
        if ($user->hasField('field_name') && $name !== '') {
          $user->set('field_name', $name);
        }

        $user->save();
        $created++;

        if ($send_email) {
          $mail_username = $name !== '' ? $name : $username;
          $this->sendCompanyMailToUser(
            $email,
            [
              'subject' => 'Company account created',
              'username' => $mail_username,
              'email' => $email,
              'company_name' => $selected_company,
            ],
            $filepath
          );
        }

        $results[] = ['row' => $row_number, 'email' => $email, 'result' => 'Created', 'message' => 'New company created'];
      }
    }

    if ($send_email) {
      $this->sendCompanyMailToAdmin(
        'jithesh0510@gmail.com',
        [
          'subject' => 'Company Import Notification',
          'company_name' => $selected_company,
          'message' => 'Selected company: ' . $selected_company,
        ],
        $filepath
      );
    }

    $connection = \Drupal::database();
    if ($connection->schema()->tableExists('company_import_history')) {
      $connection->insert('company_import_history')
        ->fields([
          'company_name' => $selected_company,
          'file_name' => basename($filepath),
          'created' => \Drupal::time()->getRequestTime(),
          'created_count' => $created,
          'updated_count' => $updated,
          'error_count' => $errors,
          'details' => serialize($results),
        ])
        ->execute();
    }

    \Drupal::messenger()->addStatus($this->t('Company import completed. Created: @c, Updated: @u, Errors: @e', [
      '@c' => $created,
      '@u' => $updated,
      '@e' => $errors,
    ]));

    $form_state->setRedirect('company_import_history.import_history');
  }

}
