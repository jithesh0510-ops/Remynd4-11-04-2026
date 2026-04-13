<?php

namespace Drupal\remynd4_coach_tools\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

final class CoachImportV2Form extends FormBase {

  public function getFormId(): string {
    return 'remynd4_coach_tools_coach_import_v2_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $company_options = $this->loadCompanyOptions();

    $form['company'] = [
      '#type' => 'select',
      '#title' => $this->t('Company Name'),
      '#options' => $company_options,
      '#empty_option' => $this->t('- Select company -'),
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
      '#required' => FALSE,
    ];

    $form['actions'] = ['#type' => 'actions'];

    $form['actions']['upload'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload/Update coach'),
      '#button_type' => 'primary',
    ];

    $form['download_sample'] = [
      '#type' => 'link',
      '#title' => $this->t('Download Sample'),
      '#url' => Url::fromRoute('remynd4_coach_tools.coach_import_sample_v2'),
      '#attributes' => ['class' => ['button']],
    ];

    $form['download_coach_key'] = [
      '#type' => 'link',
      '#title' => $this->t('Download coach Key'),
      '#url' => Url::fromRoute('remynd4_coach_tools.coach_key_v2', [], [
        'query' => ['company' => $form_state->getValue('company') ?? ''],
      ]),
      '#attributes' => [
        'class' => ['button'],
        'id' => 'download-coach-key-v2',
      ],
    ];

    $form['#attached']['html_head'][] = [
      [
        '#type' => 'html_tag',
        '#tag' => 'script',
        '#value' => '
(function(){
  function updateLink(){
    var sel = document.querySelector("select[name=company]");
    var a = document.getElementById("download-coach-key-v2");
    if(!sel || !a) return;
    var base = a.getAttribute("href").split("?")[0];
    if(sel.value){
      a.setAttribute("href", base + "?company=" + encodeURIComponent(sel.value));
    } else {
      a.setAttribute("href", base);
    }
  }
  document.addEventListener("change", function(e){
    if(e.target && e.target.name === "company"){ updateLink(); }
  });
  document.addEventListener("DOMContentLoaded", updateLink);
})();
',
      ],
      'remynd4_coach_tools_inlinejs',
    ];

    $form['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('<front>'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addStatus($this->t('Form submitted. (Import logic not wired in v2.)'));
  }

  private function loadCompanyOptions(): array {
    $db = \Drupal::database();

    $uids = $db->select('user__roles', 'ur')
      ->fields('ur', ['entity_id'])
      ->condition('ur.roles_target_id', 'company')
      ->execute()
      ->fetchCol();

    if (!$uids) {
      return [];
    }

    $rows = $db->select('users_field_data', 'u')
      ->fields('u', ['uid', 'mail'])
      ->condition('u.uid', $uids, 'IN')
      ->execute()
      ->fetchAll();

    $options = [];
    foreach ($rows as $r) {
      $options[(int) $r->uid] = $r->mail ?: ('UID ' . $r->uid);
    }

    asort($options);
    return $options;
  }

}
