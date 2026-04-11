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

class PrePostImportForm extends BaseUploadForm {
  use MessengerTrait;

  protected CsvImporter $csv;
  protected FileUploadService $fileUploadService;

  public static function create(ContainerInterface $container) {
    $o = parent::create($container);
    $o->csv = $container->get('coach_reporting_system.csv_importer');
    $o->fileUploadService = $container->get('coach_reporting_system.file_upload_service');
    return $o;
  }

  public function getFormId() { return 'coach_reporting_prepost_import'; }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['class'][] = 'prepost-import-form';

    // Get current user and role-based filtering
    $user_filter = $this->getCurrentUserCompanyFilter();

    $form['prepost_company'] = [
      '#type' => 'select',
      '#title' => $this->t('Company Name'),
      '#options' => $this->getCompanyOptions($user_filter['whitelist']),
      '#empty_option' => $this->t('- Select Company -'),
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('prepost_company') ?: ($user_filter['is_company'] ? $user_filter['current_uid'] : ''),
      '#disabled' => $user_filter['is_company'],
      '#ajax' => [
        'callback' => '::updateProgramOptions',
        'event' => 'change',
        'wrapper' => 'prepost-program-wrapper',
        'progress' => ['type' => 'throbber', 'message' => $this->t('Loading…')],
      ],
    ];

    // Program selection
    $selected_company = $form_state->getValue('prepost_company') ?: ($user_filter['is_company'] ? $user_filter['current_uid'] : '');
    $program_options = !empty($selected_company) ? $this->getQuestionnairesByCompany((int) $selected_company) : [];

    $form['prepost_program'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'prepost-program-wrapper'],
    ];

    $form['prepost_program']['prepost_program_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Program'),
      '#options' => $program_options,
      '#empty_option' => empty($selected_company) ? $this->t('Select a company first') : $this->t('- Select Program -'),
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('prepost_program_select') ?: '',
      '#states' => [
        'visible' => [':input[name="prepost_company"]' => ['!value' => '']],
        'disabled' => [':input[name="prepost_company"]' => ['value' => '']],
      ],
    ];

    // Pre Skills Assessment
    $form['pre_skills'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Pre Skills Assessment:'),
      '#description' => $this->t('Upload the pre-training skills assessment CSV file.'),
      '#upload_location' => 'temporary://',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
        'file_validate_size' => [25600000], // 25MB
      ],
      '#required' => TRUE,
    ];

    // Post Skills Assessment
    $form['post_skills'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Post Skills Assessment:'),
      '#description' => $this->t('Upload the post-training skills assessment CSV file.'),
      '#upload_location' => 'temporary://',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
        'file_validate_size' => [25600000], // 25MB
      ],
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
      '#url' => Url::fromRoute('coach_reporting_system.download_sample', ['filename' => 'prepost-training-sample.csv']),
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

  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate company and program selection
    $this->validateCompanyProgram($form_state, 'prepost_company', 'prepost_program_select');
    
    // Validate file uploads
    $pre_skills = $form_state->getValue('pre_skills');
    $post_skills = $form_state->getValue('post_skills');

    if (empty($pre_skills) && empty($post_skills)) {
      $form_state->setErrorByName('pre_skills', $this->t('Please upload at least one skills assessment file.'));
      return;
    }

    // Validate file formats and required columns
    $required_columns = ['Serial Number', 'First name', 'Surname', 'Grade/10.00'];
    
    if (!empty($pre_skills)) {
      $this->validateCsvFile($pre_skills, $form_state, 'pre_skills', $required_columns);
    }

    if (!empty($post_skills)) {
      $this->validateCsvFile($post_skills, $form_state, 'post_skills', $required_columns);
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $company_id = $form_state->getValue('prepost_company');
    $program_id = $form_state->getValue('prepost_program_select');
    $pre_skills = $form_state->getValue('pre_skills');
    $post_skills = $form_state->getValue('post_skills');

    $toPath = function($fid) {
      $f = File::load($fid);
      $f->setPermanent()->save();
      return \Drupal::service('file_system')->realpath($f->getFileUri());
    };

    $prePath = $toPath($pre_skills[0]);
    $postPath = $toPath($post_skills[0]);

    $pre = $this->csv->readAssoc($prePath);
    $post = $this->csv->readAssoc($postPath);

    // Build maps employee_id => grade.
    $gradeOf = function(array $rows): array {
      $map = [];
      foreach ($rows as $r) {
        $emp = $r['employee_id'] ?? ($r['serial_number'] ?? NULL);
        $grade = $r['grade'] ?? ($r['grade/10.00'] ?? NULL);
        if ($emp !== NULL && $grade !== NULL && $emp !== '') {
          $emp = is_numeric($emp) ? (int) $emp : 0;
          if ($emp) {
            $map[$emp] = is_numeric($grade) ? (float) $grade : NULL;
          }
        }
      }
      return $map;
    };

    $preMap = $gradeOf($pre);
    $postMap = $gradeOf($post);

    $db = \Drupal::database();
    $ins = 0; $upd = 0; $missingPost = 0; $skippedUser = 0;

    foreach ($preMap as $emp => $preGrade) {
      $postGrade = $postMap[$emp] ?? NULL;
      if ($postGrade === NULL) { $missingPost++; continue; }

      // Validate Drupal user exists and has 'employee' role.
      $account = User::load($emp);
      if (!$account || !$account->hasRole('employee')) {
        $skippedUser++;
        continue;
      }

      $existingId = $db->select('qs_employee_prepost_relation', 'r')
        ->fields('r', ['id'])
        ->condition('employee_id', $emp)
        ->condition('company_id', $company_id)
        ->condition('questionnaire_id', $program_id)
        ->execute()->fetchField();

      $payload = [
        'employee_id' => $emp, 
        'pre' => $preGrade, 
        'post' => $postGrade,
        'company_id' => $company_id,
        'questionnaire_id' => $program_id,
        'created' => time(),
      ];
      
      if ($existingId) {
        $db->update('qs_employee_prepost_relation')->fields($payload)->condition('id', $existingId)->execute();
        $upd++;
      } else {
        $db->insert('qs_employee_prepost_relation')->fields($payload)->execute();
        $ins++;
      }
    }

    // Enhanced success/error messaging
    if ($ins > 0 || $upd > 0) {
      $msg = $this->t('✅ Pre/Post import completed successfully! @i new records inserted, @u records updated.', [
        '@i' => $ins, '@u' => $upd
      ]);
      $this->messenger()->addStatus($msg);
    }
    
    if ($missingPost > 0) {
      $msg = $this->t('⚠️ Warning: @m rows were skipped because post-training grades were missing.', [
        '@m' => $missingPost
      ]);
      $this->messenger()->addWarning($msg);
    }
    
    if ($skippedUser > 0) {
      $msg = $this->t('⚠️ Warning: @s rows were skipped because users were not found or don\'t have the "employee" role.', [
        '@s' => $skippedUser
      ]);
      $this->messenger()->addWarning($msg);
    }
    
    if ($ins === 0 && $upd === 0) {
      $this->messenger()->addError($this->t('❌ No data was imported. Please check your CSV files and try again.'));
    }
  }

}
