<?php

namespace Drupal\remynd4_user_actions\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

final class CoachImportForm extends FormBase {

  public function getFormId(): string {
    return 'remynd4_user_actions_coach_import_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#prefix'] = '<div id="coach-import-wrapper">';
    $form['#suffix'] = '</div>';

    $form['company'] = [
      '#type' => 'select',
      '#title' => $this->t('Company Name'),
      '#options' => $this->buildCompanyOptions(),
      '#default_value' => $form_state->getValue('company') ?? '',
      '#ajax' => [
        'callback' => '::ajaxRebuild',
        'event' => 'change',
        'wrapper' => 'coach-import-wrapper',
      ],
    ];

    // Buttons / links block.
    $form['downloads'] = [
      '#type' => 'container',
      '#attributes' => ['style' => 'margin:10px 0; display:flex; gap:12px; flex-wrap:wrap;'],
    ];

    $form['downloads']['sample'] = [
      '#type' => 'link',
      '#title' => $this->t('Download Coach Import Sample'),
      '#url' => Url::fromRoute('remynd4_user_actions.coach_import_sample'),
      '#attributes' => ['class' => ['button', 'button--small']],
    ];

    $company_uid = (int) ($form_state->getValue('company') ?? 0);
    if ($company_uid > 0) {
      $form['downloads']['coach_key'] = [
        '#type' => 'link',
        '#title' => $this->t('Download Coach Key'),
        '#url' => Url::fromRoute('remynd4_user_actions.coach_import_key', [], ['query' => ['company' => $company_uid]]),
        '#attributes' => ['class' => ['button', 'button--small']],
      ];
    }
    else {
      $form['downloads']['coach_key'] = [
        '#type' => 'markup',
        '#markup' => '<em>Select a company to enable “Download Coach Key”.</em>',
      ];
    }

    // Your existing CSV upload + options can stay below.
    // (If you already have fields like csv, send_email, submit etc. keep them as-is in your file.)
    // If this file previously contained them, re-add them below this comment.

    $form['csv'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('CSV file'),
      '#upload_location' => 'public://coach_import/',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
    ];

    $form['send_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send email'),
      '#default_value' => (int) ($form_state->getValue('send_email') ?? 0),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import/Update coach'),
    ];

    return $form;
  }

  public function ajaxRebuild(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  private function buildCompanyOptions(): array {
    $options = ['' => $this->t('- Select -')];

    $uids = \Drupal::entityQuery('user')
      ->condition('status', 1)
      ->condition('roles', 'company')
      ->accessCheck(FALSE)
      ->execute();

    if (empty($uids)) {
      return $options;
    }

    /** @var \Drupal\user\Entity\User[] $users */
    $users = \Drupal\user\Entity\User::loadMultiple($uids);

    foreach ($users as $u) {
      $label = '';

      // Prefer company profile referenced from company user.
      if ($u->hasField('company_profiles') && !$u->get('company_profiles')->isEmpty() && $u->get('company_profiles')->entity) {
        $p = $u->get('company_profiles')->entity;

        // Try common "real name" fields on profile.
        foreach ([
          'field_company_name',
          'field_company_title',
          'field_organization_name',
          'field_org_name',
          'field_name',
          'name',
          'field_title',
        ] as $f) {
          if ($p->hasField($f) && !$p->get($f)->isEmpty()) {
            $label = (string) ($p->get($f)->value ?? $p->get($f)->getString());
            $label = trim($label);
            if ($label !== '') {
              break;
            }
          }
        }

        // Fallback to profile label.
        if ($label === '') {
          $label = (string) $p->label();
        }
      }

      // Final fallback.
      if ($label === '') {
        $label = (string) $u->getDisplayName();
      }

      $options[$u->id()] = $label;
    }

    asort($options);
    // keep "- Select -" first
    $select = $options[''];
    unset($options['']);
    return ['' => $select] + $options;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Keep your existing submit logic here if you already had it.
    // For now, just show confirmation that file was saved.
    $company_uid = (int) $form_state->getValue('company');
    $fids = $form_state->getValue('csv');
    $fid = is_array($fids) && !empty($fids[0]) ? (int) $fids[0] : 0;
    $send = (bool) $form_state->getValue('send_email');

    if ($fid) {
      $file = \Drupal\file\Entity\File::load($fid);
      if ($file) {
        $file->setPermanent();
        $file->save();
      }
    }

    $this->messenger()->addStatus($this->t('File saved (FID: @fid). Company UID: @cid. Send email: @send', [
      '@fid' => $fid,
      '@cid' => $company_uid,
      '@send' => $send ? 'Yes' : 'No',
    ]));
  }

}
