<?php

namespace Drupal\report_upload\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class PrePostRelationForm extends FormBase {

  public function getFormId() {
    return 'report_upload_prepost_relation_form';
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

    $form['pre_score'] = [
      '#type' => 'number',
      '#title' => $this->t('Pre Score'),
      '#step' => '0.01',
      '#required' => TRUE,
    ];

    $form['post_score'] = [
      '#type' => 'number',
      '#title' => $this->t('Post Score'),
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
    \Drupal::database()->insert('qs_employee_prepost_relation')
      ->fields([
        'employee_uid' => $values['employee_uid'],
        'company_uid' => $values['company_uid'],
        'questionnaire_id' => $values['questionnaire_id'],
        'pre_score' => $values['pre_score'],
        'post_score' => $values['post_score'],
        'created' => \Drupal::time()->getCurrentTime(),
      ])
      ->execute();

    $this->messenger()->addStatus($this->t('PrePost Relation record saved successfully.'));
  }

}
