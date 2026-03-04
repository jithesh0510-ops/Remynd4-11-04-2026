<?php

namespace Drupal\remynd4_user_actions\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

final class CoachImportCsvForm extends FormBase {

  public function getFormId(): string {
    return 'remynd4_user_actions_coach_importcsv_form';
  }

  private function companyLabel(User $u): string {
    // Prefer "company profile" label if Profile module exists and company owns profiles.
    if (\Drupal::entityTypeManager()->hasDefinition('profile')) {
      try {
        $profiles = \Drupal::entityTypeManager()->getStorage('profile')->loadByProperties(['uid' => $u->id()]);
        if ($profiles) {
          /** @var \Drupal\profile\Entity\Profile $p */
          $p = reset($profiles);
          // If profile has a company name field, prefer it.
          foreach (['field_company_name','field_name','field_company','company_name','name'] as $f) {
            if ($p->hasField($f) && !$p->get($f)->isEmpty()) {
              $v = $p->get($f)->value;
              if ($v) return (string) $v;
            }
          }
          // Fallback to profile label.
          $label = $p->label();
          if ($label) return (string) $label;
        }
      } catch (\Throwable $e) {}
    }

    // Fallbacks: display name -> username -> uid
    $dn = $u->getDisplayName();
    if ($dn) return $dn;
    return 'Company '.$u->id();
  }

  private function getCompanyOptions(): array {
    // If your company role machine name is NOT 'company', change it here.
    $uids = \Drupal::entityQuery('user')
      ->condition('status', 1)
      ->condition('roles', 'company')
      ->accessCheck(FALSE)
      ->execute();

    $opts = [];
    if ($uids) {
      $users = User::loadMultiple($uids);
      foreach ($users as $u) {
        $opts[$u->id()] = $this->companyLabel($u);
      }
      asort($opts);
    }
    return $opts;
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $req = \Drupal::request();
    $company_q = (int) $req->query->get('company');

    $companies = $this->getCompanyOptions();
    $default_company = $company_q ?: (empty($companies) ? 0 : (int) array_key_first($companies));

    $form['company'] = [
      '#type' => 'select',
      '#title' => $this->t('Company Name'),
      '#options' => $companies,
      '#default_value' => $default_company,
      '#required' => TRUE,
    ];

    $form['file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Select File'),
      '#upload_location' => 'public://coach_import/',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
      '#required' => FALSE,
    ];

    $form['send_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send an email'),
      '#default_value' => 0,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload/Update coach'),
      '#button_type' => 'primary',
    ];

    $company_for_links = $default_company ?: 0;

    $form['downloads'] = [
      '#type' => 'container',
      '#attributes' => ['style' => 'margin-top:20px; display:flex; gap:10px; flex-wrap:wrap;'],
    ];

    $form['downloads']['coach_key'] = [
      '#type' => 'link',
      '#title' => $this->t('Download coach Key'),
      '#url' => Url::fromUserInput('/coach/importcsv/coach-key', ['query' => ['company' => $company_for_links]]),
      '#attributes' => ['class' => ['button']],
    ];

    $form['downloads']['sample'] = [
      '#type' => 'link',
      '#title' => $this->t('Download Sample'),
      '#url' => Url::fromUserInput('/coach/importcsv/sample'),
      '#attributes' => ['class' => ['button']],
    ];

    $form['downloads']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromUserInput('/coach'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addStatus($this->t('Form submitted.'));
  }
}
