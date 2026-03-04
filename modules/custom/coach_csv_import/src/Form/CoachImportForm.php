<?php

namespace Drupal\coach_csv_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\user\Entity\User;

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

    $form['csv_file'] = [
      '#type' => 'file',
      '#title' => $this->t('Select File'),
      '#description' => $this->t("Upload a CSV file. Required column: email (mail also accepted). Optional: first_name, last_name, name."),
      '#required' => TRUE,
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
      '#url' => \Drupal\Core\Url::fromRoute('coach_csv_import.download_key'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    $form['actions']['download_sample'] = [
      '#type' => 'link',
      '#title' => $this->t('Download Sample'),
      '#url' => \Drupal\Core\Url::fromRoute('coach_csv_import.download_sample'),
      '#attributes' => ['class' => ['button']],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => \Drupal\Core\Url::fromUserInput('/coach'),
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
    $files = \Drupal::request()->files->get('files');
    if (empty($files['csv_file'])) {
      $form_state->setErrorByName('csv_file', $this->t('Please choose a CSV file.'));
      return;
    }
    $name = $files['csv_file']->getClientOriginalName();
    if (!preg_match('/\.csv$/i', $name)) {
      $form_state->setErrorByName('csv_file', $this->t('Only CSV files are allowed.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $company_id = (int) $form_state->getValue('company_id');
    $send_email = (bool) $form_state->getValue('send_email');

    $files = \Drupal::request()->files->get('files');
    $tmp = $files['csv_file']->getRealPath();

    $created = 0;
    $updated = 0;
    $errors = 0;

    if (!$tmp || !is_readable($tmp)) {
      $this->messenger()->addError($this->t('Unable to read uploaded file.'));
      return;
    }

    $fh = fopen($tmp, 'r');
    if (!$fh) {
      $this->messenger()->addError($this->t('Unable to open uploaded file.'));
      return;
    }

    $header = fgetcsv($fh);
    if (!$header) {
      fclose($fh);
      $this->messenger()->addError($this->t('CSV is empty.'));
      return;
    }

    $map = [];
    foreach ($header as $i => $h) {
      $k = strtolower(trim((string) $h));
      $map[$k] = $i;
    }

    // Require email (or accept legacy mail).
    $email_key = isset($map['email']) ? 'email' : (isset($map['mail']) ? 'mail' : NULL);
    if (!$email_key) {
      fclose($fh);
      $this->messenger()->addError($this->t("CSV must contain a column named 'email' (mail also accepted)."));
      return;
    }

    $first_key = isset($map['first_name']) ? 'first_name' : NULL;
    $last_key  = isset($map['last_name']) ? 'last_name' : NULL;
    $name_key  = isset($map['name']) ? 'name' : NULL;

    while (($row = fgetcsv($fh)) !== FALSE) {
      $email = trim((string) ($row[$map[$email_key]] ?? ''));
      if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors++;
        continue;
      }

      $first = $first_key ? trim((string) ($row[$map[$first_key]] ?? '')) : '';
      $last  = $last_key  ? trim((string) ($row[$map[$last_key]] ?? ''))  : '';
      $name  = $name_key  ? trim((string) ($row[$map[$name_key]] ?? ''))  : '';

      if (!$name) $name = trim($first . ' ' . $last);
      if (!$name) $name = $email;

      $account = user_load_by_mail($email);
      $is_new = FALSE;

      if (!$account) {
        $account = User::create([
          'name' => $email,
          'mail' => $email,
          'status' => 1,
        ]);
        $account->addRole('coach');
        $account->setPassword(user_password(16));
        $is_new = TRUE;
      }
      else {
        if (!$account->hasRole('coach')) {
          $account->addRole('coach');
        }
      }

      if ($account->hasField('field_first_name') && $first) $account->set('field_first_name', $first);
      if ($account->hasField('field_last_name') && $last)   $account->set('field_last_name', $last);
      if ($account->hasField('field_full_name') && $name)   $account->set('field_full_name', $name);

      try {
        $account->save();
      }
      catch (\Throwable $e) {
        $errors++;
        continue;
      }

      // Set field_company on the user's profile (any existing profile).
      try {
        $profile_storage = \Drupal::entityTypeManager()->getStorage('profile');
        $profiles = $profile_storage->loadByProperties(['uid' => $account->id()]);
        $profile = $profiles ? reset($profiles) : NULL;

        if ($profile && $profile->hasField('field_company')) {
          $profile->set('field_company', ['target_id' => $company_id]);
          $profile->save();
        }
      }
      catch (\Throwable $e) {
        // Ignore.
      }

      if ($send_email) {
        $this->sendSimpleMail($email, $name);
      }

      if ($is_new) $created++; else $updated++;
    }

    fclose($fh);

    $this->messenger()->addStatus($this->t('Done. Created: @c, Updated: @u, Errors: @e', [
      '@c' => $created,
      '@u' => $updated,
      '@e' => $errors,
    ]));

    $form_state->setRedirectUrl(\Drupal\Core\Url::fromUserInput('/coach'));
  }

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

  /**
   * Build dropdown from companies referenced by COACH users,
   * but display a real company name (from the referenced company's profile fields),
   * not the user email.
   */
  private function loadCompanyOptions(): array {
    $opts = ["" => $this->t("- Select -")];

    $fs = FieldStorageConfig::loadByName('profile', 'field_company');
    $target_type = $fs ? $fs->getSetting('target_type') : NULL;

    $ids = \Drupal::database()->query("
      SELECT DISTINCT fc.field_company_target_id
      FROM {profile__field_company} fc
      INNER JOIN {profile} pr ON pr.profile_id = fc.entity_id
      INNER JOIN {user__roles} ur ON ur.entity_id = pr.uid
      WHERE ur.roles_target_id = :role
        AND fc.field_company_target_id IS NOT NULL
    ", [':role' => 'coach'])->fetchCol();

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return $opts;

    $labels = [];

    // If field_company points to USER (common in your DB), show company profile name.
    if ($target_type === 'user') {
      foreach ($ids as $uid) {
        $labels[$uid] = $this->companyNameFromCompanyUser($uid) ?: ("Company #" . $uid);
      }
    }
    else {
      // Normal target types (node/term/profile/etc).
      $entities = [];
      if ($target_type) {
        try {
          $entities = \Drupal::entityTypeManager()->getStorage($target_type)->loadMultiple($ids);
        }
        catch (\Throwable $e) {
          $entities = [];
        }
      }

      foreach ($ids as $id) {
        $label = NULL;
        if ($target_type && isset($entities[$id])) {
          $label = $this->resolveCompanyLabel($target_type, $entities[$id]);
        }
        $labels[$id] = $label ?: ("Company #" . $id);
      }
    }

    natcasesort($labels);
    return ["" => $this->t("- Select -")] + $labels;
  }

  private function companyNameFromCompanyUser(int $uid): ?string {
    try {
      $u = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
      if (!$u) return NULL;

      // Try all profiles for this user and pull common "company name" fields.
      $profile_storage = \Drupal::entityTypeManager()->getStorage('profile');
      $profiles = $profile_storage->loadByProperties(['uid' => $uid]);
      if ($profiles) {
        foreach ($profiles as $p) {
          $name = $this->companyNameFromProfile($p);
          if ($name) return $name;
        }
      }

      // Fallback (still better than blank): user display name/email.
      $dn = method_exists($u, 'getDisplayName') ? trim((string) $u->getDisplayName()) : '';
      if ($dn !== '') return $dn;
      if (method_exists($u, 'getEmail')) return trim((string) $u->getEmail());
    }
    catch (\Throwable $e) {}

    return NULL;
  }

  private function resolveCompanyLabel(string $target_type, $entity): ?string {
    if ($target_type === 'profile') {
      return $this->companyNameFromProfile($entity);
    }

    // node/term/etc -> label should be fine.
    $l = method_exists($entity, 'label') ? $entity->label() : NULL;
    $l = $l ? trim((string) $l) : '';
    return $l !== '' ? $l : NULL;
  }

  private function companyNameFromProfile($profile): ?string {
    $candidates = [
      'field_company_name',
      'field_business_name',
      'field_organization',
      'field_org_name',
      'field_org',
      'field_name',
      'field_title',
    ];

    foreach ($candidates as $f) {
      if (method_exists($profile, 'hasField') && $profile->hasField($f) && !$profile->get($f)->isEmpty()) {
        $v = trim((string) $profile->get($f)->value);
        if ($v !== '') return $v;
      }
    }

    // Never show "Coach #1234" style labels.
    return NULL;
  }

}
