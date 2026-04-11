<?php

namespace Drupal\report_upload\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class OnJobProgressForm extends FormBase {

  public function getFormId() {
    return 'report_upload_on_job_progress_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['csv_file'] = [
      '#type' => 'file',
      '#title' => $this->t('Upload CSV'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $validators = ['file_validate_extensions' => ['csv']];
    $file = file_save_upload('csv_file', $validators, FALSE, 0);
    if ($file) {
      // Use your CsvParser service here if needed.
      $this->messenger()->addStatus($this->t('CSV file uploaded successfully.'));
    }
    else {
      $this->messenger()->addError($this->t('File upload failed.'));
    }
  }

}
