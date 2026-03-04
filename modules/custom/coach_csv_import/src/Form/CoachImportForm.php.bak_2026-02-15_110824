<?php

namespace Drupal\coach_csv_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use Drupal\Core\Url;

class CoachImportForm extends FormBase {

  public function getFormId() {
    return 'coach_csv_import_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['company_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Company Name'),
      '#options' => $this->loadCompanyOptions(),
      '#required' => TRUE,
    ];

    $form['csv'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Select File'),
      '#upload_location' => 'public://coach_import/',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
      '#required' => TRUE,
      '#description' => $this->t('Upload a CSV file. Required column: mail. Optional: name, first_name, last_name.'),
    ];

    $form['send_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send an email'),
      '#default_value' => 0,
    ];

    $form['actions'] = ['#type' => 'actions'];

    $form['actions']['download_key'] = [
      '#type' => 'link',
      '#title' => $this->t('Download coach Key'),
      '#url' => Url::fromRoute('coach_csv_import.download_key'),
      '#attributes' => ['class' => ['button']],
    ];

    $form['actions']['download_sample'] = [
      '#type' => 'link',
      '#title' => $this->t('Download Sample'),
      '#url' => Url::fromRoute('coach_csv_import.download_sample'),
      '#attributes' => ['class' => ['button']],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromUserInput('/coach'),
      '#attributes' => ['class' => ['button']],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload/Update coach'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $fids = $form_state->getValue('csv');
    if (empty($fids[0])) {
      $form_state->setErrorByName('csv', $this->t('Please upload a CSV file.'));
      return;
    }
    $file = File::load($fids[0]);
    if (!$file) {
      $form_state->setErrorByName('csv', $this->t('Uploaded file not found.'));
      return;
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $company_id = (int) $form_state->getValue('company_id');
    $send_email = (bool) $form_state->getValue('send_email');

    $fids = $form_state->getValue('csv');
    $file = File::load($fids[0]);
    $file->setPermanent();
    $file->save();

    $uri = $file->getFileUri();
    $path = \Drupal::service('file_system')->realpath($uri);

    $created = 0;
    $updated = 0;
    $errors = 0;

    if (!file_exists($path)) {
      $this->messenger()->addError($this->t('CSV file could not be read.'));
      return;
    }

    if (($handle = fopen($path, 'r')) === FALSE) {
      $this->messenger()->addError($this->t('Unable to open CSV.'));
      return;
    }

    $header = fgetcsv($handle);
    if (!$header) {
      fclose($handle);
      $this->messenger()->addError($this->t('CSV is empty.'));
      return;
    }

    $map = [];
    foreach ($header as $i => $h) {
      $map[strtolower(trim($h))] = $i;
    }

    if (!isset($map['mail'])) {
      fclose($handle);
      $this->messenger()->addError($this->t('CSV must contain a "mail" column.'));
      return;
    }

    while (($row = fgetcsv($handle)) !== FALSE) {
      $mail = trim($row[$map['mail']] ?? '');
      if ($mail === '' || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        $errors++;
        continue;
      }

      $first = trim($row[$map['first_name']] ?? '');
      $last  = trim($row[$map['last_name']] ?? '');
      $name  = trim($row[$map['name']] ?? '');

      if ($name === '') {
        $name = trim($first . ' ' . $last);
      }
      if ($name === '') {
        $name = $mail;
      }

      $existing = user_load_by_mail($mail);
      $user = NULL;

      if ($existing) {
        $user = User::load($existing->id());
        $updated++;
      }
      else {
        $user = User::create();
        $user->setPassword(\Drupal::service('password_generator')->generate());
        $user->enforceIsNew();
        $user->setEmail($mail);
        $user->setUsername($mail);
        $created++;
      }

      // Basic fields if they exist.
      if ($user->hasField('field_first_name') && $first !== '') {
        $user->set('field_first_name', $first);
      }
      if ($user->hasField('field_last_name') && $last !== '') {
        $user->set('field_last_name', $last);
      }
      if ($user->hasField('field_name') && $name !== '') {
        $user->set('field_name', $name);
      }

      // Company assignment: field_company may exist on user.
      if ($company_id > 0 && $user->hasField('field_company')) {
        $user->set('field_company', $company_id);
      }

      // Ensure coach role.
      $roles = $user->getRoles();
      if (!in_array('coach', $roles, TRUE)) {
        $user->addRole('coach');
      }

      $user->activate();
      $user->save();

      if ($send_email) {
        $this->sendSimpleMail($mail, $name);
      }
    }

    fclose($handle);

    $this->messenger()->addStatus($this->t('Done. Created: @c, Updated: @u, Errors: @e', [
      '@c' => $created,
      '@u' => $updated,
      '@e' => $errors,
    ]));
  }

  /**
   * Company dropdown options.
   * We try to use company profile label (real name). Fallback: "Company #ID".
   */
  private function loadCompanyOptions(): array {
    $opts = ['' => $this->t('- Select -')];

    // Companies are users too in your system. We list users who have role "company".
    $uids = \Drupal::entityQuery('user')
      ->condition('status', 1)
      ->condition('roles', 'company')
      ->accessCheck(FALSE)
      ->execute();

    if (empty($uids)) {
      return $opts;
    }

    $storage_user = \Drupal::entityTypeManager()->getStorage('user');
    $storage_profile = \Drupal::entityTypeManager()->getStorage('profile');

    $users = $storage_user->loadMultiple($uids);

    $labels = [];
    foreach ($users as $u) {
      $label = '';

      // Try profile label for this company user.
      $profiles = $storage_profile->loadByProperties(['uid' => $u->id()]);
      if (!empty($profiles)) {
        $p = reset($profiles);
        if (method_exists($p, 'label')) {
          $label = (string) $p->label();
        }

        // Try common profile fields for company name.
        if ($label === '') {
          foreach (['field_company_name','field_business_name','field_organization','field_name','field_title'] as $fn) {
            if ($p->hasField($fn) && !$p->get($fn)->isEmpty()) {
              $label = (string) $p->get($fn)->value;
              break;
            }
          }
        }
      }

      if ($label === '') {
        $label = $u->getDisplayName();
      }
      if ($label === '' || $label === $u->getAccountName()) {
        $label = 'Company #' . $u->id();
      }

      $labels[$u->id()] = $label;
    }

    natcasesort($labels);
    foreach ($labels as $id => $label) {
      $opts[$id] = $label;
    }

    return $opts;
  }

  /**
   * Safe mail: uses our module hook_mail, no token replacement crash.
   */
  private function sendSimpleMail(string $to, string $username): void {
    $params = [
      'subject' => 'Coach account updated',
      'message' => "Hello {$username},\n\nYour coach account has been created/updated.\n\nThanks.",
    ];

    \Drupal::service('plugin.manager.mail')->mail(
      'coach_csv_import',
      'coach_import',
      $to,
      \Drupal::languageManager()->getDefaultLanguage()->getId(),
      $params
    );
  }

}
