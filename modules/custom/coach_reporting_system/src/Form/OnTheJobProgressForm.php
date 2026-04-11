<?php

namespace Drupal\coach_reporting_system\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\coach_reporting_system\Service\CsvImporter;
use Drupal\coach_reporting_system\Service\FileUploadService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use Drupal\Core\Url;

class OnTheJobProgressForm extends BaseUploadForm {
  use MessengerTrait;

  protected CsvImporter $csv;
  protected FileUploadService $fileUploadService;

  public function getFormId() {
    return 'coach_reporting_on_job_progress';
  }

  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->csv = $container->get('coach_reporting_system.csv_importer');
    $instance->fileUploadService = $container->get('coach_reporting_system.file_upload_service');
    return $instance;
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['class'][] = 'otj-progress-form';

    // Get current user and role-based filtering
    $user_filter = $this->getCurrentUserCompanyFilter();

    $form['otj_company'] = [
      '#type' => 'select',
      '#title' => $this->t('Company Name'),
      '#options' => $this->getCompanyOptions($user_filter['whitelist']),
      '#empty_option' => $this->t('- Select Company -'),
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('otj_company') ?: ($user_filter['is_company'] ? $user_filter['current_uid'] : ''),
      '#disabled' => $user_filter['is_company'],
      '#ajax' => [
        'callback' => '::updateProgramOptions',
        'event' => 'change',
        'wrapper' => 'otj-program-wrapper',
        'progress' => ['type' => 'throbber', 'message' => $this->t('Loading…')],
      ],
    ];

    // Program selection
    $selected_company = $form_state->getValue('otj_company') ?: ($user_filter['is_company'] ? $user_filter['current_uid'] : '');
    $program_options = !empty($selected_company) ? $this->getQuestionnairesByCompany((int) $selected_company) : [];

    $form['otj_program'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'otj-program-wrapper'],
    ];

    $form['otj_program']['otj_program_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Program'),
      '#options' => $program_options,
      '#empty_option' => empty($selected_company) ? $this->t('Select a company first') : $this->t('- Select Program -'),
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('otj_program_select') ?: '',
      '#states' => [
        'visible' => [':input[name="otj_company"]' => ['!value' => '']],
        'disabled' => [':input[name="otj_company"]' => ['value' => '']],
      ],
    ];

    // Import Employee On the Job Target CSV
    $form['csv'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Import Employee On the Job Target CSV :'),
      '#description' => $this->t('Upload a CSV with columns: employee_id, first_name, last_name, JOB position, target_forecasted, target_achieved'),
      '#required' => TRUE,
      '#upload_location' => 'temporary://',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
        'file_validate_size' => [25600000], // 25MB
      ],
    ];

    // Select Month dropdown
    $form['month'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Month :'),
      '#options' => $this->getMonthOptions(),
      '#default_value' => $form_state->getValue('month') ?: date('F Y'),
      '#required' => TRUE,
    ];

    // Action buttons
    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['box-footer']],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => ['class' => ['btn', 'btn-success']],
    ];

    $form['actions']['download_sample'] = [
      '#type' => 'link',
      '#title' => $this->t('Download Sample File'),
      '#url' => Url::fromRoute('coach_reporting_system.download_sample', ['filename' => 'on-job-progress-sample.csv']),
      '#attributes' => [
        'class' => ['btn', 'btn-danger'],
        'target' => '_blank',
      ],
    ];

    $form['actions']['download_employee_key'] = [
      '#type' => 'link',
      '#title' => $this->t('Download Employee Key'),
      '#url' => Url::fromRoute('coach_reporting_system.download_sample', ['filename' => 'employee-key.csv']),
      '#attributes' => [
        'class' => ['btn', 'btn-success'],
        'target' => '_blank',
      ],
    ];

    return $form;
  }

  /**
   * Get month options for the dropdown.
   */
  protected function getMonthOptions(): array {
    $options = [];
    $current_year = date('Y');
    $current_month = date('n');
    
    // Generate options for current year and next year
    for ($year = $current_year; $year <= $current_year + 1; $year++) {
      for ($month = 1; $month <= 12; $month++) {
        // Skip past months in current year
        if ($year == $current_year && $month < $current_month) {
          continue;
        }
        
        $month_name = date('F', mktime(0, 0, 0, $month, 1, $year));
        $options[$month_name . ' ' . $year] = $month_name . ' ' . $year;
      }
    }
    
    return $options;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate company and program selection
    $this->validateCompanyProgram($form_state, 'otj_company', 'otj_program_select');
    
    // Validate month selection
    $month = $form_state->getValue('month');
    if (empty($month)) {
      $form_state->setErrorByName('month', $this->t('Please select a month.'));
    }
    
    // Validate CSV file upload
    $csv_files = $form_state->getValue('csv');
    if (empty($csv_files)) {
      $form_state->setErrorByName('csv', $this->t('Please upload a CSV file.'));
      return;
    }
    
    // Validate file format and required columns
    $required_columns = ['employee_id', 'first_name', 'last_name', 'JOB position', 'target_forecasted', 'target_achieved'];
    $this->validateCsvFile($csv_files, $form_state, 'csv', $required_columns);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $company_id = $form_state->getValue('otj_company');
    $program_id = $form_state->getValue('otj_program_select');
    $month = $form_state->getValue('month');
    $fid = $form_state->getValue('csv')[0] ?? NULL;
    
    /** @var \Drupal\file\FileInterface $file */
    $file = File::load($fid);
    $file->setPermanent()->save();
    $path = $file->getFileUri();
    $real = \Drupal::service('file_system')->realpath($path);

    $rows = $this->csv->readAssoc($real);

    $db = \Drupal::database();
    $inserted = 0; $updated = 0; $skipped = 0; $errors = [];

    foreach ($rows as $index => $row) {
      $line = $index + 2; // header offset
      $emp_raw = $row['employee_id'] ?? '';
      $emp = is_numeric($emp_raw) ? (int) $emp_raw : 0;
      $forecast = $row['target_forecasted'] ?? NULL;
      $achieved = $row['target_achieved'] ?? NULL;

      if (!$emp || $forecast === NULL || $achieved === NULL) {
        $errors[] = $this->t('Line @line: missing/invalid employee_id or target fields.', ['@line' => $line]);
        continue;
      }

      // Validate Drupal user exists and has 'employee' role.
      $account = User::load($emp);
      if (!$account || !$account->hasRole('employee')) {
        $skipped++;
        continue;
      }

      $record = [
        'employee_id' => $emp,
        'target_forecasted' => is_numeric($forecast) ? $forecast : NULL,
        'target_achieved' => is_numeric($achieved) ? $achieved : NULL,
        'created' => $month,
        'company_id' => $company_id,
        'questionnaire_id' => $program_id,
      ];

      // Upsert by (employee_id, created, company_id, questionnaire_id).
      $query = $db->select('qs_emp_lagard_starts', 'q')
        ->fields('q', ['lagard_starts_id'])
        ->condition('employee_id', $record['employee_id'])
        ->condition('created', $record['created'])
        ->condition('company_id', $record['company_id'])
        ->condition('questionnaire_id', $record['questionnaire_id']);
      
      $exists = $query->execute()->fetchField();

      if ($exists) {
        $db->update('qs_emp_lagard_starts')->fields($record)->condition('lagard_starts_id', $exists)->execute();
        $updated++;
      }
      else {
        $db->insert('qs_emp_lagard_starts')->fields($record)->execute();
        $inserted++;
      }
    }

    // Enhanced success/error messaging
    if ($inserted > 0 || $updated > 0) {
      $msg = $this->t('✅ On-the-Job Progress import completed successfully! @ins new records inserted, @upd records updated.', [
        '@ins' => $inserted, '@upd' => $updated
      ]);
      $this->messenger()->addStatus($msg);
    }
    
    if ($skipped > 0) {
      $msg = $this->t('⚠️ Warning: @s rows were skipped because users were not found or don\'t have the "employee" role.', [
        '@s' => $skipped
      ]);
      $this->messenger()->addWarning($msg);
    }
    
    if (!empty($errors)) {
      foreach ($errors as $error) {
        $this->messenger()->addError($error);
      }
    }
    
    if ($inserted === 0 && $updated === 0 && empty($errors)) {
      $this->messenger()->addError($this->t('❌ No data was imported. Please check your CSV file and try again.'));
    }
  }

}
