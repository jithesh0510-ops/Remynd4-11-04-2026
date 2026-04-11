<?php

namespace Drupal\coach_csv_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

class CoachImportForm extends FormBase {

  public function getFormId(): string {
    return 'coach_csv_import_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {

    $selected_company = $form_state->getValue('company_id') ?? \Drupal::request()->query->get('field_company_target_id');

    $form['company_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Company Name'),
      '#options' => $this->loadCompanyOptions(),
      '#required' => TRUE,
    ];

    $form['csv_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Select File'),
      '#upload_location' => 'public://coach_import/',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
      '#required' => TRUE,
      '#description' => $this->t('Upload a CSV file. Required column: email (mail also accepted). Optional: first_name, last_name, name.'),
    ];

    $form['send_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send an email'),
      '#default_value' => 0,
    ];

    $form['actions'] = ['#type' => 'actions'];

    // FIXED: pass selected company to download controller
    $form['actions']['download_key'] = [
      '#type' => 'link',
      '#title' => $this->t('Download coach Key'),
      '#url' => Url::fromRoute('coach_csv_import.download_key', [], [
        'query' => [
          'field_company_target_id' => $selected_company,
        ],
      ]),
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

  public function submitForm(array &$form, FormStateInterface $form_state): void {

    $company_id = (int) $form_state->getValue('company_id');
    $send_email = (bool) $form_state->getValue('send_email');

    $fids = $form_state->getValue('csv_file');
    $fid = is_array($fids) && !empty($fids) ? (int) $fids[0] : 0;

    if (!$fid) {
      $this->messenger()->addError($this->t('No CSV uploaded.'));
      return;
    }

    $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);
    $uri = $file ? $file->getFileUri() : '';

    $path = $uri ? \Drupal::service('file_system')->realpath($uri) : '';

    if (!$path || !is_readable($path)) {
      $this->messenger()->addError($this->t('Uploaded CSV file is not readable.'));
      return;
    }

    $rows = $this->readCsv($path);

    if (!$rows) {
      $this->messenger()->addError($this->t('CSV is empty.'));
      return;
    }

    $created = 0;
    $updated = 0;
    $errors = 0;

    foreach ($rows as $row) {

      $email = trim((string) ($row['email'] ?? $row['mail'] ?? ''));

      if (!$email) {
        $errors++;
        continue;
      }

      $account = user_load_by_mail($email);
      $is_new = FALSE;

      if (!$account) {
        $is_new = TRUE;

        $account = User::create([
          'name' => $email,
          'mail' => $email,
          'status' => 1,
        ]);

        $account->enforceIsNew();
      }

      if (!$account->hasRole('coach')) {
        $account->addRole('coach');
      }

      $this->setCompanyOnProfile((int) $account->id(), $company_id);

      $account->save();

      if ($is_new) {
        $created++;
      } else {
        $updated++;
      }

      if ($send_email) {
        $this->sendSimpleMail($email, $account->getAccountName());
      }
    }

    $this->messenger()->addStatus($this->t('Done. Created: @c, Updated: @u, Errors: @e', [
      '@c' => $created,
      '@u' => $updated,
      '@e' => $errors,
    ]));

    $form_state->setRedirectUrl(Url::fromUserInput('/coach'));
  }

  private function readCsv(string $path): array {

    $out = [];

    if (($h = fopen($path, 'r')) === FALSE) {
      return $out;
    }

    $header = fgetcsv($h);

    if (!$header) {
      fclose($h);
      return $out;
    }

    $header = array_map(fn($v) => strtolower(trim((string) $v)), $header);

    while (($data = fgetcsv($h)) !== FALSE) {

      if (!array_filter($data, fn($v) => trim((string)$v) !== '')) {
        continue;
      }

      $row = [];

      foreach ($header as $idx => $key) {
        $row[$key] = $data[$idx] ?? '';
      }

      $out[] = $row;
    }

    fclose($h);

    return $out;
  }

  private function loadCompanyOptions(): array {

    $opts = ['' => $this->t('- Select -')];

    $uids = \Drupal::entityQuery('user')
      ->condition('status', 1)
      ->condition('roles', 'company')
      ->accessCheck(FALSE)
      ->execute();

    if (empty($uids)) {
      return $opts;
    }

    $users = \Drupal::entityTypeManager()->getStorage('user')->loadMultiple($uids);

    foreach ($users as $u) {
      if ($u instanceof UserInterface) {
        $opts[$u->id()] = $u->getDisplayName();
      }
    }

    return $opts;
  }

  private function setCompanyOnProfile(int $uid, int $company_id): void {

    try {

      $storage = \Drupal::entityTypeManager()->getStorage('profile');
      $profiles = $storage->loadByProperties(['uid' => $uid]);

      $profile = $profiles ? reset($profiles) : NULL;

      if ($profile && $profile->hasField('field_company')) {
        $profile->set('field_company', $company_id);
        $profile->save();
      }

    } catch (\Throwable $e) {}
  }

  private function sendSimpleMail(string $to, string $username): void {

    try {

      $params = [
        'subject' => 'Coach account updated',
        'body' => [
          "Hello {$username},",
          "",
          "Your coach account has been created/updated.",
        ],
      ];

      \Drupal::service('plugin.manager.mail')->mail(
        'coach_csv_import',
        'coach_import',
        $to,
        \Drupal::languageManager()->getDefaultLanguage()->getId(),
        $params
      );

    } catch (\Throwable $e) {}
  }

}