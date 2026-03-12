<?php

namespace Drupal\remynd4_user_actions\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class CoachImportCsvForm extends FormBase {

  public function getFormId() {
    return 'coach_import_csv_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $companies = $this->getCompanies();

    $form['company'] = [
      '#type' => 'select',
      '#title' => $this->t('Company Name'),
      '#options' => $companies,
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
    ];

    $form['csv_file'] = [
      '#type' => 'file',
      '#title' => $this->t('Select File'),
    ];

    $form['send_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send an email'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['download_key'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download coach Key'),
      '#submit' => ['::downloadCoachKey'],
    ];

    $form['actions']['download_sample'] = [
      '#type' => 'link',
      '#title' => $this->t('Download Sample'),
      '#url' => Url::fromUserInput('/coach/importcsv/sample'),
      '#attributes' => ['class' => ['button']],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload/Update coach'),
    ];

    return $form;

  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addMessage('CSV upload logic runs here.');
  }

  public function downloadCoachKey(array &$form, FormStateInterface $form_state) {

    $company = $form_state->getValue('company');

    if (!$company) {
      $this->messenger()->addError('Please select a company first.');
      return;
    }

    $url = Url::fromUserInput('/coach/importcsv/coach-key', [
      'query' => [
        'company' => $company
      ]
    ]);

    $form_state->setRedirectUrl($url);

  }

  private function getCompanies() {

    $options = [];

    $profiles = \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->loadByProperties(['type' => 'company']);

    foreach ($profiles as $profile) {

      $uid = $profile->getOwnerId();
      $options[$uid] = $profile->label();

    }

    return $options;

  }

}
