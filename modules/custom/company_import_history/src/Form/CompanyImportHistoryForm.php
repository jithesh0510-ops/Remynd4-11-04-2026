<?php

namespace Drupal\company_import_history\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CompanyImportHistoryForm extends FormBase {

  public function getFormId() {
    return 'company_import_history_form';
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
    ];

    $form['upload_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload'),
      '#attributes' => [
        'class' => ['button'],
        'style' => 'background:#7fe7f2;border-color:#7fe7f2;color:#111;margin-top:8px;',
      ],
    ];

    $form['help_text'] = [
      '#markup' => '<div style="margin:10px 0 5px 0;">Upload file with column: email or mail. Optional: first_name, last_name, name.</div>',
    ];

    $form['send_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send an email'),
      '#default_value' => 0,
    ];

    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'style' => 'display:flex;gap:10px;flex-wrap:wrap;margin-top:15px;',
      ],
    ];

    $form['actions']['download_sample'] = [
      '#type' => 'link',
      '#title' => $this->t('Download Sample'),
      '#url' => Url::fromRoute('company_import_history.download_sample'),
      '#attributes' => [
        'class' => ['button'],
        'style' => 'background:#7fe7f2;border-color:#7fe7f2;color:#111;',
      ],
    ];

    $form['actions']['upload_update'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload/Update company'),
      '#attributes' => [
        'class' => ['button'],
        'style' => 'background:#7fe7f2;border-color:#7fe7f2;color:#111;',
      ],
    ];

    $form['actions']['history_link'] = [
      '#type' => 'link',
      '#title' => $this->t('View Import History'),
      '#url' => Url::fromRoute('company_import_history.import_history'),
      '#attributes' => [
        'class' => ['button'],
        'style' => 'background:#7fe7f2;border-color:#7fe7f2;color:#111;',
      ],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $filename = $this->getUploadedFilename();

    if ($filename === '') {
      $form_state->setErrorByName('import_file', $this->t('Please choose a file.'));
      return;
    }

    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xlsx', 'xls'], TRUE)) {
      $form_state->setErrorByName('import_file', $this->t('Only csv, xlsx, xls files are allowed.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $filename = $this->getUploadedFilename();
    $tmp_path = $this->getUploadedTmpPath();
    $company_name = trim((string) $form_state->getValue('company_name'));
    $send_email = (bool) $form_state->getValue('send_email');
    $created = \Drupal::time()->getRequestTime();

    $rows = $this->readUploadedRows($filename, $tmp_path);

    if (empty($rows) || count($rows) < 2) {
      $this->messenger()->addError($this->t('The uploaded file is empty or invalid.'));
      return;
    }

    $header = array_map('trim', array_shift($rows));
    $email_index = $this->findColumnIndex($header, ['email', 'mail']);
    $first_name_index = $this->findColumnIndex($header, ['first_name']);
    $last_name_index = $this->findColumnIndex($header, ['last_name']);
    $name_index = $this->findColumnIndex($header, ['name']);

    if ($email_index === -1) {
      $this->messenger()->addError($this->t('The file must contain email or mail column.'));
      return;
    }

    $created_count = 0;
    $updated_count = 0;
    $error_count = 0;
    $result_json = [];

    foreach ($rows as $k => $row) {
      $row_number = $k + 2;

      if ($this->rowIsEmpty($row)) {
        continue;
      }

      $email = trim((string) ($row[$email_index] ?? ''));

      if ($email === '') {
        $error_count++;
        $result_json[] = [
          'row' => $row_number,
          'email' => '',
          'result' => 'Error',
          'message' => 'Email is empty',
        ];
        continue;
      }

      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_count++;
        $result_json[] = [
          'row' => $row_number,
          'email' => $email,
          'result' => 'Error',
          'message' => 'Invalid email format',
        ];
        continue;
      }

      $first_name = $first_name_index !== -1 ? trim((string) ($row[$first_name_index] ?? '')) : '';
      $last_name = $last_name_index !== -1 ? trim((string) ($row[$last_name_index] ?? '')) : '';
      $name = $name_index !== -1 ? trim((string) ($row[$name_index] ?? '')) : '';

      try {
        $account = user_load_by_mail($email);

        if ($account) {
          if ($account->hasField('field_company') && $company_name !== '') {
            $account->set('field_company', $company_name);
          }
          if ($account->hasField('field_company_name') && $company_name !== '') {
            $account->set('field_company_name', $company_name);
          }
          if ($account->hasField('field_first_name') && $first_name !== '') {
            $account->set('field_first_name', $first_name);
          }
          if ($account->hasField('field_last_name') && $last_name !== '') {
            $account->set('field_last_name', $last_name);
          }
          if ($account->hasField('field_name') && $name !== '') {
            $account->set('field_name', $name);
          }

          $account->save();
          $updated_count++;

          if ($send_email) {
            $mail_username = $name !== '' ? $name : (strstr($email, '@', TRUE) ?: $email);
            $this->sendCompanyMailToUser(
              $email,
              [
                'subject' => 'Company account created',
                'username' => $mail_username,
                'email' => $email,
                'company_name' => $company_name,
              ],
              $tmp_path,
              $filename
            );
          }

          $result_json[] = [
            'row' => $row_number,
            'email' => $email,
            'result' => 'Updated',
            'message' => 'Existing company updated',
          ];
        }
        else {
          $username = strstr($email, '@', TRUE) ?: $email;
          $password = function_exists('user_password') ? user_password() : bin2hex(random_bytes(8));

          $user = \Drupal\user\Entity\User::create([
            'name' => $username,
            'mail' => $email,
            'status' => 1,
            'pass' => $password,
          ]);

          if ($user->hasField('field_company') && $company_name !== '') {
            $user->set('field_company', $company_name);
          }
          if ($user->hasField('field_company_name') && $company_name !== '') {
            $user->set('field_company_name', $company_name);
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
          $created_count++;

          if ($send_email) {
            $mail_username = $name !== '' ? $name : $username;
            $this->sendCompanyMailToUser(
              $email,
              [
                'subject' => 'Company account created',
                'username' => $mail_username,
                'email' => $email,
                'company_name' => $company_name,
              ],
              $tmp_path,
              $filename
            );
          }

          $result_json[] = [
            'row' => $row_number,
            'email' => $email,
            'result' => 'Created',
            'message' => 'New company created',
          ];
        }
      }
      catch (\Exception $e) {
        $error_count++;
        $result_json[] = [
          'row' => $row_number,
          'email' => $email,
          'result' => 'Error',
          'message' => $e->getMessage(),
        ];
      }
    }

    if ($send_email) {
      $this->sendCompanyMailToAdmin(
        'jithesh0510@gmail.com',
        [
          'subject' => 'Company Import Notification',
          'company_name' => $company_name,
          'message' => 'Selected company: ' . $company_name,
        ],
        $tmp_path,
        $filename
      );
    }

    \Drupal::database()->insert('company_import_history')
      ->fields([
        'company_uid' => 0,
        'company_name' => $company_name,
        'fid' => 0,
        'filename' => $filename,
        'file_uri' => '',
        'created_count' => $created_count,
        'updated_count' => $updated_count,
        'error_count' => $error_count,
        'result_json' => json_encode($result_json),
        'created' => $created,
      ])
      ->execute();

    $this->messenger()->addStatus($this->t('Company import saved to history.'));
    $form_state->setRedirect('company_import_history.import_history');
  }

  protected function buildCompanyAttachments($tmp_path, $filename) {
    if (!empty($tmp_path) && file_exists($tmp_path)) {
      $mime = function_exists('mime_content_type') ? mime_content_type($tmp_path) : FALSE;
      return [[
        'filepath' => $tmp_path,
        'filename' => !empty($filename) ? $filename : basename($tmp_path),
        'filemime' => $mime ?: 'application/octet-stream',
      ]];
    }
    return [];
  }

  protected function sendCompanyMailToUser($to, array $params, $tmp_path, $filename) {
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
      return;
    }

    $params['attachments'] = $this->buildCompanyAttachments($tmp_path, $filename);

    if (empty($params['message'])) {
      $lines = [
        'Hello,',
        '',
        'Your company account has been created.',
      ];
      if (!empty($params['company_name'])) {
        $lines[] = '';
        $lines[] = 'Selected company: ' . $params['company_name'];
      }
      if (!empty($params['username'])) {
        $lines[] = '';
        $lines[] = 'Username: ' . $params['username'];
      }
      $lines[] = '';
      $lines[] = 'Email: ' . $to;
      $params['message'] = implode("\n", $lines);
    }

    \Drupal::logger('company_import_history')->notice('USER MAIL -> @email | company=@company | file=@file', [
      '@email' => $to,
      '@company' => $params['company_name'] ?? '',
      '@file' => !empty($tmp_path) ? $tmp_path : '',
    ]);

    company_import_history_send_direct_mail(
      $to,
      $params['subject'] ?? 'Company account created',
      $params['message'],
      $params['attachments']
    );
  }

  protected function sendCompanyMailToAdmin($to, array $params, $tmp_path, $filename) {
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
      return;
    }

    $params['attachments'] = $this->buildCompanyAttachments($tmp_path, $filename);

    if (empty($params['message'])) {
      $params['message'] = 'Selected company: ' . (!empty($params['company_name']) ? $params['company_name'] : 'N/A');
    }

    \Drupal::logger('company_import_history')->notice('ADMIN MAIL -> company=@company | file=@file', [
      '@company' => $params['company_name'] ?? '',
      '@file' => !empty($tmp_path) ? $tmp_path : '',
    ]);

    company_import_history_send_direct_mail(
      $to,
      $params['subject'] ?? 'Company Import Notification',
      $params['message'],
      $params['attachments']
    );
  }

  protected function getUploadedFilename() {
    if (!empty($_FILES['files']['name']['import_file'])) {
      return $_FILES['files']['name']['import_file'];
    }
    if (!empty($_FILES['import_file']['name'])) {
      return $_FILES['import_file']['name'];
    }
    return '';
  }

  protected function getUploadedTmpPath() {
    if (!empty($_FILES['files']['tmp_name']['import_file'])) {
      return $_FILES['files']['tmp_name']['import_file'];
    }
    if (!empty($_FILES['import_file']['tmp_name'])) {
      return $_FILES['import_file']['tmp_name'];
    }
    return '';
  }

  protected function readUploadedRows($filename, $tmp_path) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $rows = [];

    if ($ext === 'csv') {
      if (($handle = fopen($tmp_path, 'r')) !== FALSE) {
        while (($data = fgetcsv($handle)) !== FALSE) {
          $rows[] = $data;
        }
        fclose($handle);
      }
    }
    elseif (in_array($ext, ['xlsx', 'xls'], TRUE)) {
      $spreadsheet = IOFactory::load($tmp_path);
      $sheet = $spreadsheet->getActiveSheet();
      $rows = $sheet->toArray(NULL, TRUE, TRUE, FALSE);
    }

    return $rows;
  }

  protected function findColumnIndex(array $header, array $candidates) {
    foreach ($header as $i => $column) {
      $normalized = strtolower(trim((string) $column));
      if (in_array($normalized, $candidates, TRUE)) {
        return $i;
      }
    }
    return -1;
  }

  protected function rowIsEmpty(array $row) {
    foreach ($row as $value) {
      if (trim((string) $value) !== '') {
        return FALSE;
      }
    }
    return TRUE;
  }

  protected function getCompanyOptions() {
    $options = [];

    try {
      $connection = \Drupal::database();

      if ($connection->schema()->tableExists('profile__field_company_name')) {
        $query = $connection->select('profile__field_company_name', 'c');
        $query->fields('c', ['field_company_name_value']);
        $query->distinct();
        $query->isNotNull('field_company_name_value');
        $query->condition('field_company_name_value', '', '<>');
        $query->orderBy('field_company_name_value', 'ASC');
        $results = $query->execute()->fetchCol();

        foreach ($results as $value) {
          $value = trim((string) $value);
          if ($value !== '') {
            $options[$value] = $value;
          }
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('company_import_history')->warning($e->getMessage());
    }

    return $options;
  }

}
