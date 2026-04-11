<?php

namespace Drupal\coach_reporting_system\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Coach management: list, import CSV, link to export.
 */
class CoachManageForm extends FormBase {

  public static function create(ContainerInterface $container): self {
    return new static();
  }

  public function getFormId(): string {
    return 'coach_reporting_system_coach_manage';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['export'] = [
      '#type' => 'container',
    ];
    $form['export']['link'] = [
      '#type' => 'link',
      '#title' => $this->t('Export coaches to CSV'),
      '#url' => Url::fromRoute('coach_reporting_system.coach_export'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];
    $form['list'] = $this->buildCoachList();
    $form['import'] = $this->buildImportSection();
    return $form;
  }

  protected function buildCoachList(): array {
    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $uids = $user_storage->getQuery()
      ->condition('status', 1)
      ->condition('roles', 'coach')
      ->accessCheck(TRUE)
      ->sort('name')
      ->execute();
    $rows = [];
    foreach ($user_storage->loadMultiple($uids) as $user) {
      $rows[] = [
        $user->id(),
        ['data' => Link::fromTextAndUrl($user->getDisplayName(), Url::fromRoute('entity.user.edit_form', ['user' => $user->id()]))->toRenderable()],
        $user->getEmail(),
        $user->isActive() ? $this->t('Active') : $this->t('Blocked'),
      ];
    }
    return [
      '#type' => 'table',
      '#header' => [$this->t('ID'), $this->t('Name'), $this->t('Email'), $this->t('Status')],
      '#rows' => $rows,
      '#empty' => $this->t('No coaches found.'),
      '#caption' => $this->t('Coaches. Use "Add Coach" to create one, or import from CSV below.'),
    ];
  }

  protected function buildImportSection(): array {
    $form = [
      '#type' => 'details',
      '#title' => $this->t('Import coaches from CSV'),
      '#open' => TRUE,
      '#description' => $this->t('CSV columns: name, mail, status (0 or 1), company_uids (semicolon-separated user IDs). Match by mail to update.'),
    ];
    $form['csv'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('CSV file'),
      '#upload_location' => 'temporary://',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
        'file_validate_size' => [10 * 1024 * 1024],
      ],
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['import'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import CSV'),
      '#submit' => ['::submitImport'],
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  public function submitImport(array &$form, FormStateInterface $form_state): void {
    $fids = $form_state->getValue('csv', []);
    if (empty($fids)) {
      $this->messenger()->addWarning($this->t('Please upload a CSV file.'));
      return;
    }
    $file = \Drupal::entityTypeManager()->getStorage('file')->load(reset($fids));
    if (!$file) {
      $this->messenger()->addError($this->t('File not found.'));
      return;
    }
    $path = \Drupal::service('file_system')->realpath($file->getFileUri());
    if (!$path || !is_readable($path)) {
      $this->messenger()->addError($this->t('Cannot read file.'));
      return;
    }
    $rows = \Drupal::service('coach_reporting_system.csv_importer')->readAssoc($path);
    if (empty($rows)) {
      $this->messenger()->addWarning($this->t('CSV has no data rows.'));
      return;
    }
    $created = 0;
    $updated = 0;
    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $profile_storage = \Drupal::entityTypeManager()->getStorage('profile');
    foreach ($rows as $row) {
      $mail = trim($row['mail'] ?? $row['email'] ?? '');
      $name = trim($row['name'] ?? '');
      if ($mail === '' || !\Drupal::service('email.validator')->isValid($mail)) {
        continue;
      }
      $existing = $user_storage->loadByProperties(['mail' => $mail]);
      $account = $existing ? reset($existing) : NULL;
      if ($account) {
        if (!$account->hasRole('coach')) {
          $account->addRole('coach');
        }
        if ($name !== '') {
          $account->set('name', $name);
        }
        $account->set('status', isset($row['status']) ? (int) $row['status'] : $account->get('status')->value);
        $account->save();
        $updated++;
        // TODO: set field_company on coach profile from company_uids.
      } else {
        User::create([
          'name' => $name ?: $mail,
          'mail' => $mail,
          'status' => isset($row['status']) ? (int) $row['status'] : 1,
          'roles' => ['coach'],
        ])->save();
        $created++;
      }
    }
    $this->messenger()->addStatus($this->t('Import: @created created, @updated updated.', ['@created' => $created, '@updated' => $updated]));
    $form_state->setRebuild(TRUE);
  }
}
