<?php

namespace Drupal\company_simple\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

class ImportForm extends FormBase {

  public function getFormId() {
    return 'company_simple_import_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['csv_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload CSV File'),
      '#upload_location' => 'public://imports/',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

    $fid = $form_state->getValue('csv_file')[0];
    $file = File::load($fid);

    $file->setPermanent();
    $file->save();

    \Drupal::messenger()->addMessage('File uploaded to public://imports/');
  }

}
