<?php

namespace Drupal\coach_reporting_system\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\coach_reporting_system\Service\FileUploadService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for Pre and Post Training Analysis Results (WIN).
 */
class WinAnalysisForm extends FormBase {

  protected FileUploadService $fileUploadService;

  public function __construct(FileUploadService $file_upload_service) {
    $this->fileUploadService = $file_upload_service;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('coach_reporting_system.file_upload_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'win_analysis_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['class'][] = 'win-analysis-form';

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
      '#required' => FALSE,
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
      '#required' => FALSE,
    ];

    // Action buttons
    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['box-footer']],
    ];

    $form['actions']['export_cpsm'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export CPSM Analysis sheet'),
      '#name' => 'cpsm',
      '#attributes' => ['class' => ['btn', 'btn-success', 'mb-5']],
      '#submit' => ['::submitExportCpsm'],
    ];

    $form['actions']['download_cpsm_sample'] = [
      '#type' => 'link',
      '#title' => $this->t('Download CPSM Sample File'),
      '#url' => Url::fromRoute('coach_reporting_system.download_sample', ['filename' => 'pre-CPSM.csv']),
      '#attributes' => [
        'class' => ['btn', 'btn-danger', 'mb-5'],
        'target' => '_blank',
      ],
    ];

    $form['actions']['export_csc'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export CSC Analysis sheet'),
      '#name' => 'csc',
      '#attributes' => ['class' => ['btn', 'btn-success', 'mb-5']],
      '#submit' => ['::submitExportCsc'],
    ];

    $form['actions']['download_csc_sample'] = [
      '#type' => 'link',
      '#title' => $this->t('Download CSC Sample File'),
      '#url' => Url::fromRoute('coach_reporting_system.download_sample', ['filename' => 'pre-CSC.csv']),
      '#attributes' => [
        'class' => ['btn', 'btn-danger', 'mb-5'],
        'target' => '_blank',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $pre_skills = $form_state->getValue('pre_skills');
    $post_skills = $form_state->getValue('post_skills');

    // Validate that at least one file is uploaded
    if (empty($pre_skills) && empty($post_skills)) {
      $form_state->setErrorByName('pre_skills', $this->t('Please upload at least one skills assessment file.'));
    }

    // Validate file formats if files are uploaded
    if (!empty($pre_skills)) {
      $this->validateCsvFile($pre_skills, $form_state, 'pre_skills');
    }

    if (!empty($post_skills)) {
      $this->validateCsvFile($post_skills, $form_state, 'post_skills');
    }
  }

  /**
   * Validate CSV file format and content.
   */
  protected function validateCsvFile($fids, FormStateInterface $form_state, $field_name) {
    if (empty($fids)) {
      return;
    }

    $file_storage = \Drupal::entityTypeManager()->getStorage('file');
    foreach ($fids as $fid) {
      $file = $file_storage->load($fid);
      if (!$file) {
        $form_state->setErrorByName($field_name, $this->t('File not found.'));
        continue;
      }

      $file_path = \Drupal::service('file_system')->realpath($file->getFileUri());
      if (!$file_path || !file_exists($file_path)) {
        $form_state->setErrorByName($field_name, $this->t('File path not accessible.'));
        continue;
      }

      // Check file size (max 25MB)
      $file_size = filesize($file_path);
      if ($file_size > 25600000) {
        $form_state->setErrorByName($field_name, $this->t('File size exceeds 25MB limit.'));
        continue;
      }

      // Basic CSV validation
      $handle = fopen($file_path, 'r');
      if (!$handle) {
        $form_state->setErrorByName($field_name, $this->t('Cannot read the uploaded file.'));
        continue;
      }

      $first_row = fgetcsv($handle);
      fclose($handle);

      if (!$first_row) {
        $form_state->setErrorByName($field_name, $this->t('The uploaded file appears to be empty or invalid.'));
        continue;
      }

      // Check for required columns
      $required_columns = ['First name', 'Surname', 'Grade/10.00'];
      $missing_columns = [];
      foreach ($required_columns as $column) {
        if (!in_array($column, $first_row)) {
          $missing_columns[] = $column;
        }
      }

      if (!empty($missing_columns)) {
        $form_state->setErrorByName($field_name, $this->t('Missing required columns: @columns', [
          '@columns' => implode(', ', $missing_columns)
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Default submit handler - not used as we have specific submit handlers
  }

  /**
   * Submit handler for CPSM export.
   */
  public function submitExportCpsm(array &$form, FormStateInterface $form_state) {
    $pre_skills = $form_state->getValue('pre_skills');
    $post_skills = $form_state->getValue('post_skills');

    if (empty($pre_skills) && empty($post_skills)) {
      $this->messenger()->addError($this->t('Please upload at least one skills assessment file.'));
      return;
    }

    // Process the files and generate CPSM analysis
    $this->processWinAnalysis($pre_skills, $post_skills, 'CPSM');
  }

  /**
   * Submit handler for CSC export.
   */
  public function submitExportCsc(array &$form, FormStateInterface $form_state) {
    $pre_skills = $form_state->getValue('pre_skills');
    $post_skills = $form_state->getValue('post_skills');

    if (empty($pre_skills) && empty($post_skills)) {
      $this->messenger()->addError($this->t('Please upload at least one skills assessment file.'));
      return;
    }

    // Process the files and generate CSC analysis
    $this->processWinAnalysis($pre_skills, $post_skills, 'CSC');
  }

  /**
   * Process WIN analysis data.
   */
  protected function processWinAnalysis($pre_skills, $post_skills, $analysis_type) {
    $file_storage = \Drupal::entityTypeManager()->getStorage('file');
    $csv_importer = \Drupal::service('coach_reporting_system.csv_importer');
    
    $pre_data = [];
    $post_data = [];

    // Process pre-skills data
    if (!empty($pre_skills)) {
      foreach ($pre_skills as $fid) {
        $file = $file_storage->load($fid);
        if ($file) {
          $file_path = \Drupal::service('file_system')->realpath($file->getFileUri());
          if ($file_path) {
            $pre_data = array_merge($pre_data, $csv_importer->readAssoc($file_path));
          }
        }
      }
    }

    // Process post-skills data
    if (!empty($post_skills)) {
      foreach ($post_skills as $fid) {
        $file = $file_storage->load($fid);
        if ($file) {
          $file_path = \Drupal::service('file_system')->realpath($file->getFileUri());
          if ($file_path) {
            $post_data = array_merge($post_data, $csv_importer->readAssoc($file_path));
          }
        }
      }
    }

    // Perform analysis based on the type
    $this->generateAnalysisReport($pre_data, $post_data, $analysis_type);
  }

  /**
   * Generate analysis report and trigger download.
   */
  protected function generateAnalysisReport($pre_data, $post_data, $analysis_type) {
    // Create analysis data
    $analysis_data = $this->performAnalysis($pre_data, $post_data, $analysis_type);
    
    // Generate CSV file
    $filename = $analysis_type . '_Analysis_' . date('Y-m-d_H-i-s') . '.csv';
    $this->generateAndDownloadCsv($analysis_data, $filename);
    
    // Log the analysis
    \Drupal::logger('coach_reporting_system')->info('WIN Analysis generated for @type with @pre_count pre records and @post_count post records', [
      '@type' => $analysis_type,
      '@pre_count' => count($pre_data),
      '@post_count' => count($post_data),
    ]);
  }

  /**
   * Perform the actual analysis on the data.
   */
  protected function performAnalysis($pre_data, $post_data, $analysis_type) {
    $analysis_results = [];
    
    // Create a map of employee data for easier lookup
    $pre_map = [];
    $post_map = [];
    
    foreach ($pre_data as $row) {
      $employee_id = $row['Serial Number'] ?? '';
      if ($employee_id) {
        $pre_map[$employee_id] = $row;
      }
    }
    
    foreach ($post_data as $row) {
      $employee_id = $row['Serial Number'] ?? '';
      if ($employee_id) {
        $post_map[$employee_id] = $row;
      }
    }
    
    // Get all unique employee IDs
    $all_employees = array_unique(array_merge(array_keys($pre_map), array_keys($post_map)));
    
    // Perform analysis for each employee
    foreach ($all_employees as $employee_id) {
      $pre_row = $pre_map[$employee_id] ?? [];
      $post_row = $post_map[$employee_id] ?? [];
      
      $analysis_row = [
        'Employee ID' => $employee_id,
        'First Name' => $pre_row['First name'] ?? $post_row['First name'] ?? '',
        'Surname' => $pre_row['Surname'] ?? $post_row['Surname'] ?? '',
        'Pre-Training Grade' => $pre_row['Grade/10.00'] ?? 'N/A',
        'Post-Training Grade' => $post_row['Grade/10.00'] ?? 'N/A',
        'Improvement' => 'N/A',
        'Analysis Type' => $analysis_type,
      ];
      
      // Calculate improvement if both grades are available
      if (isset($pre_row['Grade/10.00']) && isset($post_row['Grade/10.00'])) {
        $pre_grade = floatval($pre_row['Grade/10.00']);
        $post_grade = floatval($post_row['Grade/10.00']);
        $improvement = $post_grade - $pre_grade;
        $analysis_row['Improvement'] = number_format($improvement, 2);
      }
      
      $analysis_results[] = $analysis_row;
    }
    
    return $analysis_results;
  }

  /**
   * Generate CSV file and trigger download.
   */
  protected function generateAndDownloadCsv($data, $filename) {
    if (empty($data)) {
      $this->messenger()->addWarning($this->t('No data available for analysis.'));
      return;
    }
    
    // Create CSV content
    $csv_content = '';
    
    // Add headers
    $headers = array_keys($data[0]);
    $csv_content .= implode(',', $headers) . "\n";
    
    // Add data rows
    foreach ($data as $row) {
      $csv_row = [];
      foreach ($headers as $header) {
        $value = $row[$header] ?? '';
        // Escape commas and quotes in CSV
        if (strpos($value, ',') !== false || strpos($value, '"') !== false) {
          $value = '"' . str_replace('"', '""', $value) . '"';
        }
        $csv_row[] = $value;
      }
      $csv_content .= implode(',', $csv_row) . "\n";
    }
    
    // Create temporary file
    $temp_file = \Drupal::service('file_system')->tempnam('temporary://', 'win_analysis_');
    file_put_contents($temp_file, $csv_content);
    
    // Trigger download
    $this->triggerFileDownload($temp_file, $filename);
  }

  /**
   * Trigger file download using Drupal's response system.
   */
  protected function triggerFileDownload($file_path, $filename) {
    $response = new \Symfony\Component\HttpFoundation\BinaryFileResponse($file_path);
    $response->setContentDisposition('attachment', $filename);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Length', filesize($file_path));
    
    // Set the response and stop further processing
    $response->send();
    exit;
  }

}