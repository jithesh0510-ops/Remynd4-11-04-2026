<?php

namespace Drupal\employee_import_history\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use Drupal\employee_import_history\CompanyHelper;

class EmployeeImportHistoryForm extends FormBase {

  public function getFormId() {
    return 'employee_import_history_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['employee_debug_visible'] = [
      '#markup' => '<div style="background:#ffdddd;border:2px solid red;padding:10px;margin:10px 0;font-weight:bold;">EMPLOYEE FORM FILE IS LOADED</div>',
    ];
    $form['company_uid'] = [
      '#type' => 'select',
      '#title' => $this->t('Company Name'),
      '#options' => CompanyHelper::getCompanyOptions(),
      '#required' => TRUE,
    ];

    $form['csv_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Select File'),
      '#upload_location' => 'public://employee_import_history/',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
      '#required' => TRUE,
      '#description' => $this->t('Upload CSV with column: email or mail. Optional: first_name, last_name, name.'),
    ];

    $form['send_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send an email'),
      '#default_value' => 0,
    ];

    $form['actions'] = ['#type' => 'actions'];

    $form['actions']['download_key'] = [
      '#type' => 'markup',
      '#markup' => '<a href="#" class="button js-employee-import-download-key">Download employee Key</a>',
    ];

    $form['actions']['download_sample'] = [
      '#type' => 'markup',
      '#markup' => '<a href="/employee/import-history-upload/download-sample" class="button">Download Sample</a>',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload/Update employee'),
      '#button_type' => 'primary',
    ];

    $form['actions']['history'] = [
      '#type' => 'link',
      '#title' => $this->t('View Import History'),
      '#url' => Url::fromUserInput('/admin/employee-import-history'),
      '#attributes' => ['class' => ['button']],
    ];

    $form['#attached']['html_head'][] = [[
      '#tag' => 'script',
      '#value' => '(function(){document.addEventListener("click",function(e){var a=e.target.closest(".js-employee-import-download-key");if(!a){return;}e.preventDefault();var sel=document.querySelector("[name=\'company_uid\']");if(!sel||!sel.value){alert("Please select a company for Download employee Key.");return;}window.location.href="/employee/import-history-upload/download-key/"+sel.value;});})();',
    ], 'employee_import_history_download_key_js'];

    return $form;
  }


  private function sendEmployeeMailToUser(string $to, array $params): void {
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
      return;
    }

    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'employee_import_history';
    $key = 'employee_import';
    $langcode = \Drupal::currentUser()->getPreferredLangcode();

    $params['subject'] = $params['subject'] ?? 'Employee account created';
    $params['email'] = $params['email'] ?? $to;
    $params['username'] = $params['username'] ?? $to;
    $params['company_name'] = $params['company_name'] ?? '';

    $mailManager->mail($module, $key, $to, $langcode, $params, NULL, TRUE);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $company_uid = (int) $form_state->getValue('company_uid');
    if (empty($company_uid) || !User::load($company_uid)) {
      $form_state->setErrorByName('company_uid', $this->t('Please select a valid company.'));
    }

    $fids = $form_state->getValue('csv_file');
    if (empty($fids[0])) {
      $form_state->setErrorByName('csv_file', $this->t('CSV file is required.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $company_uid = (int) $form_state->getValue('company_uid');
    $send_email = (bool) $form_state->getValue('send_email');
    $fids = $form_state->getValue('csv_file');
    $fid = (int) ($fids[0] ?? 0);

    $company = User::load($company_uid);
    if (!$company) {
      $this->messenger()->addError($this->t('Selected company not found.'));
      return;
    }

    $company_name = CompanyHelper::getCompanyDisplayName($company);

    $file = File::load($fid);
    if (!$file) {
      $this->messenger()->addError($this->t('Uploaded file not found.'));
      return;
    }

    $file->setPermanent();
    $file->save();

    $full_path = \Drupal::service('file_system')->realpath($file->getFileUri());
    if (!$full_path || !file_exists($full_path)) {
      $this->messenger()->addError($this->t('Could not read uploaded CSV file.'));
      return;
    }

    $handle = fopen($full_path, 'r');
    if (!$handle) {
      $this->messenger()->addError($this->t('Failed to open CSV file.'));
      return;
    }

    $header = fgetcsv($handle);
    if (!$header) {
      fclose($handle);
      $this->messenger()->addError($this->t('CSV header row is missing.'));
      return;
    }

    $header = array_map(function ($v) {
      return strtolower(trim((string) $v));
    }, $header);

    $email_index = array_search('email', $header, TRUE);
    if ($email_index === FALSE) {
      $email_index = array_search('mail', $header, TRUE);
    }

    $first_name_index = array_search('first_name', $header, TRUE);
    $last_name_index = array_search('last_name', $header, TRUE);
    $name_index = array_search('name', $header, TRUE);

    if ($email_index === FALSE) {
      fclose($handle);
      $this->messenger()->addError($this->t('Required column email or mail is missing.'));
      return;
    }

    $created = 0;
    $updated = 0;
    $errors = 0;
    $row_number = 1;
    $results = [];

    while (($row = fgetcsv($handle)) !== FALSE) {
      $row_number++;

      try {
        $email = trim((string) ($row[$email_index] ?? ''));
        $first_name = $first_name_index !== FALSE ? trim((string) ($row[$first_name_index] ?? '')) : '';
        $last_name = $last_name_index !== FALSE ? trim((string) ($row[$last_name_index] ?? '')) : '';
        $name = $name_index !== FALSE ? trim((string) ($row[$name_index] ?? '')) : '';

        if ($email === '') {
          $errors++;
          $results[] = [
            'row' => $row_number,
            'email' => '',
            'result' => 'Error',
            'message' => 'Email is empty',
          ];
          continue;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
          $errors++;
          $results[] = [
            'row' => $row_number,
            'email' => $email,
            'result' => 'Error',
            'message' => 'Invalid email format',
          ];
          continue;
        }

        $existing = user_load_by_mail($email);

        if ($existing) {
          if ($existing->hasField('field_company')) {
            $existing->set('field_company', $company_name);
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

          if (!$existing->hasRole('employee')) {
            $existing->addRole('employee');
          }

          $existing->save();
          $updated++;

          if ($send_email) {
            $this->sendEmployeeMailToUser($email, [
              'subject' => 'Employee account created',
              'email' => $email,
              'username' => $existing->getAccountName(),
              'company_name' => $company_name,
            ]);
          }

          $results[] = [
            'row' => $row_number,
            'email' => $email,
            'result' => 'Updated',
            'message' => 'Existing employee updated',
          ];
        }
        else {
          $username = strstr($email, '@', TRUE);
          if (!$username) {
            $username = 'employee_' . time() . '_' . $row_number;
          }

          $base_username = $username;
          $counter = 1;
          while (user_load_by_name($username)) {
            $username = $base_username . '_' . $counter;
            $counter++;
          }

          $password = \Drupal::service('password_generator')->generate(12);

          $user = User::create([
            'name' => $username,
            'mail' => $email,
            'status' => 1,
          ]);
          $user->addRole('employee');

          if ($user->hasField('field_company')) {
            $user->set('field_company', $company_name);
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

          $user->setPassword($password);
          $user->save();
          $created++;

          if ($send_email) {
            $this->sendEmployeeMailToUser($email, [
              'subject' => 'Employee account created',
              'email' => $email,
              'username' => $username,
              'company_name' => $company_name,
            ]);
          }

          $results[] = [
            'row' => $row_number,
            'email' => $email,
            'result' => 'Created',
            'message' => 'New employee created',
          ];
        }
      }
      catch (\Throwable $e) {
        $errors++;
        $results[] = [
          'row' => $row_number,
          'email' => isset($email) ? $email : '',
          'result' => 'Error',
          'message' => $e->getMessage(),
        ];
      }
    }

    fclose($handle);

    \Drupal::database()->insert('employee_import_history')
      ->fields([
        'company_uid' => $company_uid,
        'company_name' => $company_name,
        'fid' => $file->id(),
        'filename' => $file->getFilename(),
        'file_uri' => $file->getFileUri(),
        'created_count' => $created,
        'updated_count' => $updated,
        'error_count' => $errors,
        'result_json' => json_encode($results),
        'created' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();

    if ($send_email) {
      $mailManager = \Drupal::service('plugin.manager.mail');
      $mailManager->mail(
        'employee_import_history',
        'admin_employee_import_mail',
        'jithesh0510@gmail.com',
        \Drupal::currentUser()->getPreferredLangcode(),
        [
          'subject' => 'Employee Import Notification',
          'message' => 'Company: ' . $company_name . "\n"
            . 'File: ' . $file->getFilename() . "\n"
            . 'Created: ' . $created . "\n"
            . 'Updated: ' . $updated . "\n"
            . 'Errors: ' . $errors,
          'company_name' => $company_name,
        ],
        NULL,
        TRUE
      );
    }

    $this->messenger()->addStatus($this->t('Done. Created: @c, Updated: @u, Errors: @e', [
      '@c' => $created,
      '@u' => $updated,
      '@e' => $errors,
    ]));

    $form_state->setRedirectUrl(Url::fromUserInput('/admin/employee-import-history'));
  }

  private function remyndEmployeeImportIsSameFileForCompany(int $company_id, string $uploaded_file_hash): bool {
    if (empty($company_id) || empty($uploaded_file_hash)) {
      return FALSE;
    }

    $connection = \Drupal::database();

    $query = $connection->select('employee_import_history', 'h')
      ->fields('h', ['id', 'company_uid', 'file_uri'])
      ->condition('company_uid', $company_id)
      ->orderBy('id', 'DESC');

    $rows = $query->execute()->fetchAll();

    foreach ($rows as $row) {
      $file_uri = (string) ($row->file_uri ?? '');
      if ($file_uri !== '' && strpos($file_uri, '|hash:') !== FALSE) {
        [, $saved_hash] = explode('|hash:', $file_uri, 2);
        if (trim($saved_hash) === $uploaded_file_hash) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

}
