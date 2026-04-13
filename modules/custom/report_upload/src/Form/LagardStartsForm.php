<?php

namespace Drupal\report_upload\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class LagardStartsForm extends FormBase {

  public function getFormId() {
    return 'report_upload_lagard_starts_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['employee_uid'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Employee'),
      '#target_type' => 'user',
      '#required' => TRUE,
    ];

    $form['company_uid'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Company'),
      '#target_type' => 'user',
      '#required' => TRUE,
    ];

    $form['questionnaire_id'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Program (Questionnaire)'),
      '#target_type' => 'node',
      '#selection_settings' => ['target_bundles' => ['questionnaire']],
      '#required' => TRUE,
    ];

    $form['month'] = [
      '#type' => 'date',
      '#title' => $this->t('Month'),
      '#required' => TRUE,
    ];

    $form['target_forecasted'] = [
      '#type' => 'number',
      '#title' => $this->t('Target Forecasted'),
      '#step' => '0.01',
      '#required' => TRUE,
    ];

    $form['target_achieved'] = [
      '#type' => 'number',
      '#title' => $this->t('Target Achieved'),
      '#step' => '0.01',
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    \Drupal::database()->insert('qs_emp_lagard_starts')
      ->fields([
        'employee_uid' => $values['employee_uid'],
        'company_uid' => $values['company_uid'],
        'questionnaire_id' => $values['questionnaire_id'],
        'month' => $values['month'],
        'target_forecasted' => $values['target_forecasted'],
        'target_achieved' => $values['target_achieved'],
        'created' => \Drupal::time()->getCurrentTime(),
      ])
      ->execute();

    $this->messenger()->addStatus($this->t('Lagard Starts record saved successfully.'));
  }

}
