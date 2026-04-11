<?php

namespace Drupal\coach_reporting_system\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Employee management: list, import CSV, link to export.
 */
class EmployeeManageForm extends FormBase {

  public static function create(ContainerInterface $container): self {
    return new static();
  }

  public function getFormId(): string {
    return 'coach_reporting_system_employee_manage';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['export'] = [
      '#type' => 'container',
    ];
    $form['export']['link'] = [
      '#type' => 'link',
      '#title' => $this->t('Export employees to CSV'),
      '#url' => Url::fromRoute('coach_reporting_system.employee_export'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];
    $form['list'] = $this->buildEmployeeList();
    $form['import'] = $this->buildImportSection();
    return $form;
  }

  protected function buildEmployeeList(): array {
    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $uids = $user_storage->getQuery()
      ->condition('status', 1)
      ->condition('roles', 'employee')
      ->accessCheck(TRUE)
      ->sort('name')
      ->execute();
    $rows = [];
    $profile_storage = \Drupal::entityTypeManager()->getStorage('profile');
    foreach ($user_storage->loadMultiple($uids) as $user) {
      $company = $coach = $program = '';
      $profiles = $profile_storage->loadByUser($user, 'employee', TRUE);
      if (is_array($profiles)) {
        $profiles = $profiles ? [reset($profiles)] : [];
      } else {
        $profiles = $profiles ? [$profiles] : [];
      }
      foreach ($profiles as $profile) {
        if (!$profile) {
          continue;
        }
        if ($profile->hasField('field_company') && !$profile->get('field_company')->isEmpty()) {
          $company = $profile->get('field_company')->entity ? $profile->get('field_company')->entity->getDisplayName() : $profile->get('field_company')->target_id;
        }
        if ($profile->hasField('field_coach') && !$profile->get('field_coach')->isEmpty()) {
          $coach = $profile->get('field_coach')->entity ? $profile->get('field_coach')->entity->getDisplayName() : $profile->get('field_coach')->target_id;
        }
        if ($profile->hasField('field_program') && !$profile->get('field_program')->isEmpty()) {
          $program = $profile->get('field_program')->entity ? $profile->get('field_program')->entity->label() : $profile->get('field_program')->target_id;
        }
      }
      $rows[] = [
        $user->id(),
        ['data' => Link::fromTextAndUrl($user->getDisplayName(), Url::fromRoute('entity.user.edit_form', ['user' => $user->id()]))->toRenderable()],
        $user->getEmail(),
        $company,
        $coach,
        $program,
      ];
    }
    return [
      '#type' => 'table',
      '#header' => [$this->t('ID'), $this->t('Name'), $this->t('Email'), $this->t('Company'), $this->t('Coach'), $this->t('Program')],
      '#rows' => $rows,
      '#empty' => $this->t('No employees found.'),
      '#caption' => $this->t('Employees. Import from CSV below or create via Add user with employee role.'),
    ];
  }

  protected function buildImportSection(): array {
    $form = [
      '#type' => 'details',
      '#title' => $this->t('Import employees from CSV'),
      '#open' => TRUE,
      '#description' => $this->t('CSV columns: name, mail, status, company_uid, coach_uid, program_nid. Match by mail to update.'),
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
    foreach ($rows as $row) {
      $mail = trim($row['mail'] ?? $row['email'] ?? '');
      $name = trim($row['name'] ?? '');
      if ($mail === '' || !\Drupal::service('email.validator')->isValid($mail)) {
        continue;
      }
      $existing = $user_storage->loadByProperties(['mail' => $mail]);
      $account = $existing ? reset($existing) : NULL;
      if ($account) {
        if (!$account->hasRole('employee')) {
          $account->addRole('employee');
        }
        if ($name !== '') {
          $account->set('name', $name);
        }
        $account->set('status', isset($row['status']) ? (int) $row['status'] : $account->get('status')->value);
        $account->save();
        $updated++;
        // TODO: set employee profile field_company, field_coach, field_program.
      } else {
        User::create([
          'name' => $name ?: $mail,
          'mail' => $mail,
          'status' => isset($row['status']) ? (int) $row['status'] : 1,
          'roles' => ['employee'],
        ])->save();
        $created++;
      }
    }
    $this->messenger()->addStatus($this->t('Import: @created created, @updated updated.', ['@created' => $created, '@updated' => $updated]));
    $form_state->setRebuild(TRUE);
  }
}
