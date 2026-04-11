<?php

namespace Drupal\coach_reporting_system\Form;

use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Coach Reporting form:
 * 1) Company → 2) Program (Questionnaire) → 3) Coach → 4) Employee → Report.
 *
 * - Uses Select2 if present.
 * - On "View Results Online" it rebuilds and renders the report under the form.
 */
class ReportForm extends BaseUploadForm {

  // ---- Employee profile wiring (edit if your machine names differ) ----
  private const EMPLOYEE_PROFILE_TYPE   = 'employee';
  private const EMPLOYEE_FIELD_COMPANY  = 'field_company';   // references User (Company)
  private const EMPLOYEE_FIELD_COACH    = 'field_coach';     // references User (Coach)
  private const EMPLOYEE_FIELD_PROGRAM  = 'field_program';   // references Node (Questionnaire)

  public static function create(ContainerInterface $container): self {
    return parent::create($container);
  }

  /** {@inheritdoc} */
  public function getFormId(): string {
    return 'coach_reporting_system_report_form';
  }

  /** {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Persist selections across AJAX rebuilds.
    $selected_company  = $form_state->getValue('company') ?: '';
    $selected_program  = $form_state->getValue('program') ?: '';
    $selected_coach    = $form_state->getValue('coach') ?: '';
    $selected_employee = $form_state->getValue('employee') ?: '';
    $selected_status   = $form_state->getValue('employee_status') ?: 'active';

    $current_user = \Drupal::currentUser();
    $current_uid  = (int) $current_user->id();
    $roles        = $current_user->getRoles(TRUE);

    $is_admin    = in_array('administrator', $roles, TRUE) || $current_user->hasPermission('administer users');
    $is_company  = !$is_admin && in_array('company', $roles, TRUE);
    $is_coach    = !$is_admin && in_array('coach', $roles, TRUE);
    $is_employee = !$is_admin && in_array('employee', $roles, TRUE);

    $form['#attributes']['id'] = 'coach-reporting-system-report-form';
    $form['#cache']['max-age'] = 0;

    $use_select2 = \Drupal::moduleHandler()->moduleExists('select2');
    if ($use_select2) {
      $form['#attached']['library'][] = 'select2/select2';
    }

    // Attach professional form styling
    $form['#attached']['library'][] = 'coach_reporting_system/report_form';

    // ----- Company options (filtered by role) -----
    $company_whitelist = NULL;
    if ($is_company) {
      $company_whitelist = [$current_uid];
      $selected_company = (string) $current_uid;
    }
    elseif ($is_coach) {
      $company_whitelist = $this->getCompaniesForCoach($current_uid);
      if (!$selected_company && count($company_whitelist) === 1) {
        $selected_company = (string) reset($company_whitelist);
      }
    }
    elseif ($is_employee) {
      $company_whitelist = $this->getCompaniesForEmployee($current_uid);
      if (!$selected_company && count($company_whitelist) === 1) {
        $selected_company = (string) reset($company_whitelist);
      }
    }

    // 1) Company (Select2 when available, else core select)
    $form['company'] = [
      '#type' => $use_select2 ? 'select2' : 'select',
      '#title' => $this->t('Select Company'),
      '#options' => $this->getCompanyOptions($company_whitelist),
      '#empty_option' => $this->t('- Select company -'),
      '#placeholder' => $this->t('- Select company -'),
      '#default_value' => $selected_company,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateDependents',
        'event' => 'change',
        'wrapper' => 'dependent-wrapper',
        'progress' => ['type' => 'throbber', 'message' => $this->t('Loading…')],
      ],
      '#disabled' => $is_company,
      '#description' => $is_company
        ? $this->t('Locked to your company account.')
        : ($is_coach ? $this->t('Companies assigned to you.') : ($is_employee ? $this->t('Companies you belong to.') : '')),
    ];
    if ($use_select2) {
      $form['company']['#select2'] = ['allowClear' => TRUE, 'width' => 'resolve'];
    }

    // Dependents container.
    $form['dependents'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'dependent-wrapper'],
      '#states' => ['visible' => [':input[name="company"]' => ['!value' => '']]],
    ];

    // 2) Program (Questionnaire) – only programs that have data in report table
    $program_options = [];
    if (!empty($selected_company)) {
      $all_programs = $this->getQuestionnairesByCompany((int) $selected_company);
      $program_nids_with_data = $this->getProgramNidsWithReportData((int) $selected_company);
      $program_options = array_intersect_key($all_programs, array_flip($program_nids_with_data));
    }
    $form['dependents']['program'] = [
      '#type' => $use_select2 ? 'select2' : 'select',
      '#title' => $this->t('Select a Program'),
      '#description' => $this->t('Programs with report data for the selected company.'),
      '#options' => $program_options,
      '#empty_option' => empty($selected_company) ? $this->t('Select a company first') : (empty($program_options) ? $this->t('No programs with report data') : $this->t('- Select program -')),
      '#placeholder' => empty($selected_company) ? $this->t('Select a company first') : $this->t('- Select program -'),
      '#required' => TRUE,
      '#default_value' => $selected_program,
      '#states' => [
        'visible' => [':input[name="company"]' => ['!value' => '']],
        'disabled' => [':input[name="company"]' => ['value' => '']],
      ],
      '#ajax' => ['callback' => '::updateDependents', 'event' => 'change', 'wrapper' => 'dependent-wrapper'],
    ];
    if ($use_select2) {
      $form['dependents']['program']['#select2'] = ['allowClear' => TRUE, 'width' => 'resolve'];
    }

    // 3) Coach – based on company + program; only coaches with report data; message if none
    $coach_options = [];
    if (!empty($selected_company) && !empty($selected_program)) {
      $coach_uids_with_data = $this->getCoachUidsWithReportData((int) $selected_company, (int) $selected_program);
      $all_coaches = $this->getCoachByCompany((int) $selected_company);
      foreach ($coach_uids_with_data as $uid) {
        if (isset($all_coaches[$uid])) {
          $coach_options[$uid] = $all_coaches[$uid];
        }
      }
      $coach_options = ['all' => $this->t('All Coaches')] + $coach_options;

      if ($is_coach) {
        $selected_coach = (string) $current_uid;
        $coach_options = isset($coach_options[$current_uid])
          ? [$current_uid => $coach_options[$current_uid]]
          : [$current_uid => $this->t('You')];
      }
      elseif ($is_employee) {
        $allowed = $this->getEmployeeCoaches($current_uid, (int) $selected_company);
        $whitelist = array_flip($allowed);
        $filtered = ['all' => $this->t('All Coaches')];
        foreach ($coach_options as $k => $v) {
          if ($k === 'all' || isset($whitelist[$k])) {
            $filtered[$k] = $v;
          }
        }
        $coach_options = $filtered;
      }
    }
    if (empty($coach_options)) {
      $form['dependents']['coach'] = [
        '#type' => 'container',
        '#states' => ['visible' => [':input[name="program"]' => ['!value' => '']]],
        'message' => [
          '#markup' => '<div class="messages messages--warning">' . $this->t('No coaches available for the selected company and program.') . '</div>',
          '#weight' => 0,
        ],
      ];
    }
    else {
      $form['dependents']['coach'] = [
        '#type' => $use_select2 ? 'select2' : 'select',
        '#title' => $this->t('Select a Coach'),
        '#options' => $coach_options,
        '#empty_option' => $this->t('- Select Coach -'),
        '#placeholder' => $this->t('- Select Coach -'),
        '#required' => TRUE,
        '#default_value' => $selected_coach,
        '#states' => [
          'visible' => [':input[name="program"]' => ['!value' => '']],
          'disabled' => [':input[name="program"]' => ['value' => '']],
        ],
        '#ajax' => ['callback' => '::updateDependents', 'event' => 'change', 'wrapper' => 'dependent-wrapper'],
        '#disabled' => $is_coach,
        '#description' => $is_coach
          ? $this->t('Locked to your coach account.')
          : $this->t('Based on company and program. Choose "All Coaches" or a specific coach.'),
      ];
      if ($use_select2) {
        $form['dependents']['coach']['#select2'] = ['allowClear' => TRUE, 'width' => 'resolve'];
      }
    }

    // 4) Employee (based on company, program, coach / all coaches)
    $employee_options = [];
    $selected_status = $selected_status ?: 'active';
    if (!empty($selected_company) && !empty($selected_program) && $selected_coach !== '') {
      $coach_filter = NULL;
      if ($selected_coach !== 'all' && is_numeric($selected_coach)) {
        $coach_filter = (int) $selected_coach;
      }
      $employee_options = $this->getEmployeeByCompanyCoachProgram(
        (int) $selected_company, $coach_filter, (int) $selected_program, $selected_status
      );
    }
    if ($is_employee && !empty($employee_options)) {
      $employee_options = array_intersect_key($employee_options, [$current_uid => TRUE]);
    }
    $form['dependents']['employee'] = [
      '#type' => $use_select2 ? 'select2' : 'select',
      '#title' => $this->t('Select an Employee'),
      '#description' => $this->t('Filtered by Company, Program, and Coach selection.'),
      '#options' => $employee_options,
      '#empty_option' => empty($selected_coach) ? $this->t('Select a coach first') : $this->t('- Select Employee -'),
      '#placeholder' => empty($selected_coach) ? $this->t('Select a coach first') : $this->t('- Select Employee -'),
      '#required' => TRUE,
      '#default_value' => $selected_employee ?: ($is_employee ? (string) $current_uid : ''),
      '#states' => [
        'visible' => [':input[name="coach"]' => ['!value' => '']],
        'disabled' => [':input[name="coach"]' => ['value' => '']],
      ],
      '#ajax' => ['callback' => '::updateDependents', 'event' => 'change', 'wrapper' => 'dependent-wrapper'],
    ];
    if ($use_select2) {
      $form['dependents']['employee']['#select2'] = ['allowClear' => TRUE, 'width' => 'resolve'];
    }

    // Select Active/Inactive Employee
    $form['dependents']['employee_status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select Active/Inactive Employee'),
      '#options' => ['active' => $this->t('Active'), 'inactive' => $this->t('Inactive')],
      '#default_value' => $selected_status,
      '#states' => [
        'visible' => [':input[name="coach"]' => ['!value' => '']],
        'disabled' => [':input[name="coach"]' => ['value' => '']],
      ],
      '#ajax' => ['callback' => '::updateDependents', 'event' => 'change', 'wrapper' => 'dependent-wrapper'],
    ];

    // Select Date: Latest or Report overtime (from date to date)
    $report_type_default = $form_state->getValue('report_type') ?: 'latest';
    $form['dependents']['report_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select Date'),
      '#options' => [
        'latest'   => $this->t('Latest'),
        'overtime' => $this->t('Report overtime (from date to date)'),
      ],
      '#default_value' => $report_type_default,
      '#states' => ['visible' => [':input[name="employee"]' => ['!value' => '']]],
    ];

    // Report content checkboxes (always build container, use #states for visibility)
    $form['dependents']['report_content'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['report-content-container']],
      '#states' => [
        'visible' => [
          ':input[name="employee"]' => ['!value' => ''],
          ':input[name="report_type"]' => ['!value' => ''],
        ],
      ],
    ];

    // Individual report type checkboxes
    $report_options = [
      'per_person' => $this->t('Per Person'),
      'competency_analysis' => $this->t('Competency analysis'),
      'on_job_performance' => $this->t('On-The-Job Performance Results'),
      'coaching_impact' => $this->t('Coaching Impact on Performance'),
      'one_to_one_coaching' => $this->t('One to One Coaching'),
    ];

    foreach ($report_options as $key => $label) {
      $form['dependents']['report_content'][$key] = [
        '#type' => 'checkbox',
        '#title' => $label,
        '#attributes' => ['class' => ['individual']],
        '#default_value' => $form_state->getValue($key) ?: 0,
      ];
    }

    // Date range (if datetime_range exists, use daterange; else simple dates).
    $use_daterange = \Drupal::moduleHandler()->moduleExists('datetime_range');
    if ($use_daterange) {
      $default_dr = $form_state->getValue('date_range') ?: [];
      $form['dependents']['date_range'] = [
        '#type' => 'daterange',
        '#title' => $this->t('Date range'),
        '#date_timezone' => date_default_timezone_get(),
        '#date_date_element' => 'date',
        '#date_time_element' => 'none',
        '#default_value' => [
          'value' => $default_dr['value'] ?? '',
          'end_value' => $default_dr['end_value'] ?? '',
        ],
        '#states' => [
          'visible' => [
            ':input[name="employee"]' => ['!value' => ''],
            ':input[name="report_type"]' => ['value' => 'overtime'],
          ],
          'required' => [':input[name="report_type"]' => ['value' => 'overtime']],
        ],
      ];
    }
    else {
      $form['dependents']['date_range'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['crs-daterange-fallback']],
        '#states' => [
          'visible' => [
            ':input[name="employee"]' => ['!value' => ''],
            ':input[name="report_type"]' => ['value' => 'overtime'],
          ],
        ],
      ];
      $form['dependents']['date_range']['from_date'] = [
        '#type' => 'date',
        '#title' => $this->t('From'),
        '#default_value' => $form_state->getValue(['date_range', 'from_date']) ?: '',
        '#states' => ['required' => [':input[name="report_type"]' => ['value' => 'overtime']]],
      ];
      $form['dependents']['date_range']['to_date'] = [
        '#type' => 'date',
        '#title' => $this->t('To'),
        '#default_value' => $form_state->getValue(['date_range', 'to_date']) ?: '',
        '#states' => ['required' => [':input[name="report_type"]' => ['value' => 'overtime']]],
      ];
    }

    // Actions: buttons always enabled; validation runs on submit via AJAX.
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['view_online'] = [
      '#type' => 'submit',
      '#value' => $this->t('View Report'),
      '#submit' => ['::submitViewOnline'],
      '#attributes' => ['class' => ['button', 'button--primary', 'report-action', 'view-online']],
    ];
    $form['actions']['download_report'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download Report'),
      '#submit' => ['::submitDownloadReport'],
      '#attributes' => ['class' => ['button', 'button--secondary', 'report-action', 'download-report']],
    ];

    // Auto-trigger initial AJAX for preselected values and add Select All functionality.
    $inline_js = <<<JS
(function (Drupal, once) {
  Drupal.behaviors.crsAutoInit = {
    attach: function (context) {
      once('crsAutoInit', 'body', context).forEach(function () {
        function trigger(selector) {
          var el = context.querySelector(selector);
          if (el && el.value && el.value !== '') {
            setTimeout(function(){ try { el.dispatchEvent(new Event('change', { bubbles: true })); } catch(e){} }, 0);
          }
        }
        trigger('select[name="company"]');
        trigger('select[name="program"]');
        trigger('select[name="coach"]');
        trigger('select[name="employee"]');
        var radios = context.querySelectorAll('input[name="employee_status"]');
        var checked = Array.prototype.slice.call(radios).find(function(r){ return r.checked; });
        if (checked) {
          setTimeout(function(){ try { checked.dispatchEvent(new Event('change', { bubbles: true })); } catch(e){} }, 0);
        }
      });
    }
  };

  // Form validation and date range validation
  Drupal.behaviors.crsFormValidation = {
    attach: function (context) {
      once('crsFormValidation', 'form', context).forEach(function (form) {
        // Check if this is the report form
        if (!form.id || form.id !== 'coach-reporting-system-report-form') {
          return;
        }
        form.addEventListener('submit', function(e) {
          // Only validate if form is actually being submitted (not AJAX)
          var submitButton = e.submitter || document.activeElement;
          if (!submitButton || (submitButton.type !== 'submit' && submitButton.tagName !== 'BUTTON')) {
            return; // Let Drupal handle it
          }

          var reportType = form.querySelector('input[name="report_type"]:checked');
          var reportContentContainer = form.querySelector('.report-content-container');
          
          // Only validate report content if container is visible and report type is selected
          if (reportType && reportContentContainer && reportContentContainer.offsetParent !== null) {
            var reportContent = form.querySelectorAll('.individual:checked');
            if (reportContent.length === 0) {
              e.preventDefault();
              alert(Drupal.t('Please select at least one report content option.'));
              return false;
            }
          }

          // Validate date range only for overtime reports
          if (reportType && reportType.value === 'overtime') {
            var fromDate, toDate;
            
            // Check for daterange field
            var dateRange = form.querySelector('input[name="date_range[value]"]');
            var dateRangeEnd = form.querySelector('input[name="date_range[end_value]"]');
            
            if (dateRange && dateRangeEnd) {
              fromDate = dateRange.value;
              toDate = dateRangeEnd.value;
            } else {
              // Fallback to separate date fields
              var fromField = form.querySelector('input[name="date_range[from_date]"]');
              var toField = form.querySelector('input[name="date_range[to_date]"]');
              if (fromField && toField) {
                fromDate = fromField.value;
                toDate = toField.value;
              }
            }

            // Only validate if date range fields are visible
            var dateRangeContainer = form.querySelector('.form-item--daterange, .crs-daterange-fallback');
            if (dateRangeContainer && dateRangeContainer.offsetParent !== null) {
              if (!fromDate || !toDate) {
                e.preventDefault();
                alert(Drupal.t('Please select both start and end dates for overtime reports.'));
                return false;
              }

              if (fromDate > toDate) {
                e.preventDefault();
                alert(Drupal.t('Start date must be before or equal to end date.'));
                return false;
              }
            }
          }
        });
      });
    }
  };
})(Drupal, once);
JS;
    $form['#attached']['library'][] = 'core/drupal';
    $form['#attached']['library'][] = 'core/once';
    $form['#attached']['html_head'][] = [
      [
        '#type' => 'html_tag',
        '#tag' => 'script',
        '#attributes' => ['type' => 'text/javascript'],
        '#markup' => $inline_js,
      ],
      'crs_autoinit_inline_js',
    ];

    // Report will be displayed on a separate page via redirect

    return $form;
  }

  /**
   * AJAX callback to refresh dependent controls.
   */
  public function updateDependents(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    
    // Add status messages for empty dependent fields
    $selected_company = $form_state->getValue('company');
    $selected_program = $form_state->getValue('program');
    $selected_coach = $form_state->getValue('coach');
    
    if (empty($selected_company)) {
      $form['dependents']['#prefix'] = '<div class="messages messages--warning">' . 
        $this->t('Please select a company to continue.') . '</div>';
    }
    elseif (empty($selected_program)) {
      $all_programs = $this->getQuestionnairesByCompany((int) $selected_company);
      $program_nids_with_data = $this->getProgramNidsWithReportData((int) $selected_company);
      $program_options = array_intersect_key($all_programs, array_flip($program_nids_with_data));
      if (empty($program_options)) {
        $form['dependents']['#prefix'] = '<div class="messages messages--warning">' . 
          $this->t('No programs with report data for the selected company.') . '</div>';
      }
    }
    elseif (empty($selected_coach) || $selected_coach === '') {
      $coach_uids_with_data = $this->getCoachUidsWithReportData((int) $selected_company, (int) $selected_program);
      $all_coaches = $this->getCoachByCompany((int) $selected_company);
      $coach_options = [];
      foreach ($coach_uids_with_data as $uid) {
        if (isset($all_coaches[$uid])) {
          $coach_options[$uid] = $all_coaches[$uid];
        }
      }
      if (empty($coach_options)) {
        $form['dependents']['#prefix'] = '<div class="messages messages--warning">' . 
          $this->t('No coaches available for the selected company and program.') . '</div>';
      }
    }
    
    return $form['dependents'];
  }

  /**
   * Program nids that have at least one submitted session for the company.
   */
  protected function getProgramNidsWithReportData(int $company_uid): array {
    $db = \Drupal::database();
    $nids = $db->select('coach_reporting_session', 's')
      ->fields('s', ['program_nid'])
      ->condition('company_uid', $company_uid)
      ->isNotNull('submitted')
      ->distinct()
      ->execute()
      ->fetchCol();
    return array_map('intval', array_values($nids));
  }

  /**
   * Coach UIDs that have at least one submitted session for company + program.
   */
  protected function getCoachUidsWithReportData(int $company_uid, int $program_nid): array {
    $db = \Drupal::database();
    $uids = $db->select('coach_reporting_session', 's')
      ->fields('s', ['coach_uid'])
      ->condition('company_uid', $company_uid)
      ->condition('program_nid', $program_nid)
      ->isNotNull('submitted')
      ->isNotNull('coach_uid')
      ->distinct()
      ->execute()
      ->fetchCol();
    return array_map('intval', array_filter(array_values($uids)));
  }

  /**
   * Whether there is at least one submitted session for the given selection.
   */
  protected function hasReportData(int $company_uid, int $program_nid, string $coach, int $employee_uid): bool {
    $db = \Drupal::database();
    $q = $db->select('coach_reporting_session', 's')
      ->fields('s', ['sid'])
      ->condition('company_uid', $company_uid)
      ->condition('program_nid', $program_nid)
      ->condition('employee_uid', $employee_uid)
      ->isNotNull('submitted');
    if ($coach !== '' && $coach !== 'all') {
      $q->condition('coach_uid', (int) $coach);
    }
    $q->range(0, 1);
    return (bool) $q->execute()->fetchField();
  }

  /** {@inheritdoc} */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $company = $form_state->getValue('company');
    $program = $form_state->getValue('program');
    $coach = $form_state->getValue('coach');
    $employee = $form_state->getValue('employee');
    $report_type = $form_state->getValue('report_type') ?: 'latest';

    if (empty($company)) {
      $form_state->setErrorByName('company', $this->t('Company selection is required.'));
    }
    if (empty($program)) {
      $form_state->setErrorByName('program', $this->t('Program selection is required.'));
    }
    if ($coach === '' || $coach === null) {
      $form_state->setErrorByName('coach', $this->t('Coach selection is required.'));
    }
    if (empty($employee)) {
      $form_state->setErrorByName('employee', $this->t('Employee selection is required.'));
    }

    if ($report_type === 'overtime') {
      $dr = $form_state->getValue('date_range') ?: [];
      $from = null;
      $to = null;
      if (isset($dr['value'], $dr['end_value'])) {
        $from = substr((string) $dr['value'], 0, 10);
        $to = substr((string) $dr['end_value'], 0, 10);
      } elseif (isset($dr['from_date'], $dr['to_date'])) {
        $from = $dr['from_date'];
        $to = $dr['to_date'];
      }
      if (empty($from) || empty($to)) {
        $form_state->setErrorByName('date_range', $this->t('Date range is required for overtime reports.'));
      } elseif ($from > $to) {
        $form_state->setErrorByName('date_range', $this->t('Start date must be before or equal to end date.'));
      }
    }

    $report_options = ['per_person', 'competency_analysis', 'on_job_performance', 'coaching_impact', 'one_to_one_coaching'];
    $report_content = [];
    foreach ($report_options as $option) {
      if ($form_state->getValue($option)) {
        $report_content[] = $option;
      }
    }
    if (empty($report_content)) {
      $form_state->setErrorByName('report_content', $this->t('Please select at least one report content option.'));
    }
  }

  /** {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Default submit is unused; we use specific submit handlers below.
  }

  public function submitViewOnline(array &$form, FormStateInterface $form_state): void {
    $company = $form_state->getValue('company');
    $program = $form_state->getValue('program');
    $coach = $form_state->getValue('coach');
    $employee = $form_state->getValue('employee');
    $report_type = $form_state->getValue('report_type') ?: 'latest';

    $report_options = ['per_person', 'competency_analysis', 'on_job_performance', 'coaching_impact', 'one_to_one_coaching'];
    $report_content = [];
    foreach ($report_options as $option) {
      if ($form_state->getValue($option)) {
        $report_content[] = $option;
      }
    }

    $params = [
      'company'        => $company,
      'program'        => $program,
      'coach'          => $coach ?: 'all',
      'employee'       => $employee,
      'report_type'    => $report_type,
      'report_content' => $report_content,
    ];

    if ($report_type === 'overtime') {
      $dr = $form_state->getValue('date_range') ?: [];
      if (isset($dr['value'], $dr['end_value'])) {
        $params['from'] = substr((string) $dr['value'], 0, 10);
        $params['to'] = substr((string) $dr['end_value'], 0, 10);
      } elseif (isset($dr['from_date'], $dr['to_date'])) {
        $params['from'] = $dr['from_date'];
        $params['to'] = $dr['to_date'];
      }
    }

    $form_state->setRedirect('coach_reporting_system.report_result', [], ['query' => $params]);
  }

  public function submitDownloadReport(array &$form, FormStateInterface $form_state): void {
    $company = $form_state->getValue('company');
    $program = $form_state->getValue('program');
    $coach = $form_state->getValue('coach');
    $employee = $form_state->getValue('employee');
    $report_type = $form_state->getValue('report_type') ?: 'latest';

    $report_options = ['per_person', 'competency_analysis', 'on_job_performance', 'coaching_impact', 'one_to_one_coaching'];
    $report_content = [];
    foreach ($report_options as $option) {
      if ($form_state->getValue($option)) {
        $report_content[] = $option;
      }
    }

    $params = [
      'company'        => $company,
      'program'        => $program,
      'coach'          => $coach ?: 'all',
      'employee'       => $employee,
      'report_type'    => $report_type,
      'report_content' => $report_content,
      'download'       => '1',
    ];

    if ($report_type === 'overtime') {
      $dr = $form_state->getValue('date_range') ?: [];
      if (isset($dr['value'], $dr['end_value'])) {
        $params['from'] = substr((string) $dr['value'], 0, 10);
        $params['to'] = substr((string) $dr['end_value'], 0, 10);
      } elseif (isset($dr['from_date'], $dr['to_date'])) {
        $params['from'] = $dr['from_date'];
        $params['to'] = $dr['to_date'];
      }
    }

    $form_state->setRedirect('coach_reporting_system.report_result', [], ['query' => $params]);
  }

  // ---------------------- Report builder ----------------------

  /**
   * Build the full report markup as a render array (no controller, no Twig).
   */
  protected function buildReportRender(
    int $company_uid,
    int $program_nid,
    int $coach_uid,
    int $employee_uid,
    string $type = 'latest',
    array $range = []
  ): array {
    $db = \Drupal::database();

    $company  = $this->entityTypeManager->getStorage('user')->load($company_uid);
    $program  = $this->entityTypeManager->getStorage('node')->load($program_nid);
    $coach    = $coach_uid ? $this->entityTypeManager->getStorage('user')->load($coach_uid) : NULL;
    $employee = $this->entityTypeManager->getStorage('user')->load($employee_uid);

    if (!$program) {
      return ['#markup' => $this->t('Questionnaire not found.')];
    }

    // Sessions.
    $q = $db->select('coach_reporting_session', 's')
      ->fields('s', ['sid','submitted'])
      ->condition('company_uid', $company_uid)
      ->condition('program_nid', $program_nid)
      ->condition('employee_uid', $employee_uid)
      ->isNotNull('submitted');

    if ($coach_uid) {
      $q->condition('coach_uid', $coach_uid);
    }

    if ($type === 'overtime' && !empty($range['from']) && !empty($range['to'])) {
      try {
        $from_ts = strtotime($range['from'] . ' 00:00:00') ?: 0;
        $to_ts   = strtotime($range['to'] . ' 23:59:59') ?: 0;
        if ($from_ts && $to_ts) {
          $q->condition('submitted', [$from_ts, $to_ts], 'BETWEEN');
        }
      } catch (\Throwable $e) {}
      $q->orderBy('submitted', 'ASC');
    }
    else {
      $q->orderBy('submitted', 'DESC')->range(0, 1);
    }

    $sessions = $q->execute()->fetchAllAssoc('sid');
    if (!$sessions) {
      return ['#markup' => '<div class="messages messages--warning">'.$this->t('No submitted sessions found for the chosen filters.').'</div>'];
    }

    // Questionnaire structure (matrix).
    $steps = $this->buildStepsFromField($program, 'field_create_questionnaire');
    $matrix_def = NULL;
    foreach ($steps as $s) {
      if (($s['widget'] ?? '') === 'matrix') { $matrix_def = $s['def']; break; }
    }
    if (!$matrix_def) {
      return ['#markup' => $this->t('This questionnaire has no matrix to report.')];
    }
    $columns = $matrix_def['options']; // numeric string => label
    $rows    = array_values(array_filter($matrix_def['rows'], fn($r) => in_array($r['type'], ['heading','question'], TRUE)));

    // Answers.
    $sid_list = array_keys($sessions);
    $ans_q = $db->select('coach_reporting_session_answer', 'a')
      ->fields('a', ['sid','row_uuid','value'])
      ->condition('sid', $sid_list, 'IN');
    $answers = $ans_q->execute()->fetchAll();

    $by_sid = [];
    foreach ($answers as $a) {
      if ($a->row_uuid !== NULL && $a->row_uuid !== '') {
        $by_sid[(int) $a->sid][(string) $a->row_uuid] = (string) $a->value;
      }
    }

    // Build series.
    $series = [];
    if ($type === 'overtime' && count($sid_list) > 1) {
      foreach ($sid_list as $sid) {
        $submitted = (int) ($sessions[$sid]->submitted ?? 0);
        $label = $submitted ? \Drupal::service('date.formatter')->format($submitted, 'custom', 'Y-m-d') : ('SID '.$sid);
        $per_row = [];
        foreach ($rows as $r) {
          if ($r['type'] !== 'question') { continue; }
          $k = $by_sid[$sid][$r['id']] ?? NULL;
          $per_row[$r['id']] = is_numeric($k) ? (float) $k : NULL;
        }
        $series[] = ['label' => $label, 'per_row' => $per_row];
      }
    }
    else {
      $sid = reset($sid_list);
      $submitted = (int) ($sessions[$sid]->submitted ?? 0);
      $label = $submitted ? \Drupal::service('date.formatter')->format($submitted, 'custom', 'Y-m-d') : $this->t('Latest');
      $per_row = [];
      foreach ($rows as $r) {
        if ($r['type'] !== 'question') { continue; }
        $k = $by_sid[$sid][$r['id']] ?? NULL;
        $per_row[$r['id']] = is_numeric($k) ? (float) $k : NULL;
      }
      $series[] = ['label' => $label, 'per_row' => $per_row];
    }

    // Inline CSS (lightweight styling to match your mock).
    $build['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => '
      .crs-report{margin-top:1rem}
      .crs-report__header{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:12px 0 18px}
      .crs-report__header>div{background:#0f172a0d;padding:10px;border-radius:6px}
      .crs-report__table{width:100%;border-collapse:separate;border-spacing:0 8px}
      .crs-report__section td{background:#3b0b53;color:#fff;font-weight:700;padding:.5rem .75rem;border-radius:6px}
      .crs-report__q{background:#fff;border:1px solid #e5e7eb;border-radius:6px 0 0 6px;padding:.5rem .75rem}
      .crs-report__cell{background:#fff;border:1px solid #e5e7eb;padding:.25rem .5rem;text-align:center}
      .crs-pill{display:inline-block;min-width:38px;padding:.25rem .5rem;border-radius:999px;background:#f3f4f6}
      .crs-pill.is-on{background:#c7f9cc;font-weight:700}
      ',
      ],
      'crs_report_inline_css',
    ];

    $header_html = sprintf(
      '<div class="crs-report__header">
         <div><strong>%s</strong><br>%s</div>
         <div><strong>%s</strong><br>%s</div>
         <div><strong>%s</strong><br>%s</div>
         <div><strong>%s</strong><br>%s</div>
       </div>',
      $this->t('Company'), $company ? $company->label() : $company_uid,
      $this->t('Program'), $program->label(),
      $this->t('Employee'), $employee ? $employee->label() : $employee_uid,
      $this->t('Date'), $series[0]['label'] ?? ''
    );

    $table = '<table class="crs-report__table"><thead><tr><th>'.$this->t('Competency').'</th>';
    foreach ($columns as $num => $label) {
      $table .= '<th>'.htmlspecialchars((string) $label).'</th>';
    }
    $table .= '</tr></thead><tbody>';

    foreach ($rows as $r) {
      if ($r['type'] === 'heading') {
        $table .= '<tr class="crs-report__section"><td colspan="'.(1+count($columns)).'">'.htmlspecialchars($r['label']).'</td></tr>';
        continue;
      }
      $table .= '<tr><td class="crs-report__q">'.htmlspecialchars($r['label']).'</td>';
      $val = $series[0]['per_row'][$r['id']] ?? NULL;
      foreach ($columns as $num => $label) {
        $on = ($val !== NULL && (float) $val === (float) $num) ? ' is-on' : '';
        $table .= '<td class="crs-report__cell"><span class="crs-pill'.$on.'">'.htmlspecialchars((string) $num).'</span></td>';
      }
      $table .= '</tr>';
    }
    $table .= '</tbody></table>';

    $build['#markup'] = '<div class="crs-report">'.$header_html.$table.'</div>';
    return $build;
  }

  // ---------------------- Helpers & data access ----------------------

  protected function profileFieldExists(string $bundle, string $field_name): bool {
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('profile', $bundle);
    return isset($definitions[$field_name]);
  }


  protected function getCoachByCompany(int $company_uid): array {
    $options = [];
    if (!$this->profileFieldExists('coach', 'field_company')) {
      \Drupal::logger('coach_reporting_system')->error('Missing field_company on coach profile bundle.');
      return $options;
    }
    $profile_storage = $this->entityTypeManager->getStorage('profile');
    $pids = $profile_storage->getQuery()
      ->condition('type', 'coach')
      ->condition('status', 1)
      ->condition('field_company.target_id', $company_uid)
      ->accessCheck(TRUE)
      ->execute();
    if (!$pids) { return $options; }

    $coach_uids = [];
    foreach ($profile_storage->loadMultiple($pids) as $profile) {
      $uid = (int) $profile->getOwnerId();
      if ($uid > 0) { $coach_uids[$uid] = $uid; }
    }
    if (!$coach_uids) { return $options; }

    $coaches = $this->entityTypeManager->getStorage('user')->loadMultiple(array_values($coach_uids));
    foreach ($coaches as $coach) {
      if (!$coach->isActive() || !in_array('coach', $coach->getRoles(), TRUE)) { continue; }
      $full_name = ($coach->hasField('field_full_name') && !$coach->get('field_full_name')->isEmpty())
        ? trim((string) $coach->get('field_full_name')->value) : $coach->label();
      $email = method_exists($coach, 'getEmail') ? $coach->getEmail() : ($coach->get('mail')->value ?? '');
      $label = $email ? sprintf('%s (%s)', $full_name, $email) : $full_name;
      $options[$coach->id()] = $label;
    }
    asort($options, SORT_NATURAL | SORT_FLAG_CASE);
    return $options;
  }

  /**
   * Build employee options filtered by Company, optional Coach & Program, and User status.
   */
  protected function getEmployeeByCompanyCoachProgram(
    int $company_uid,
    ?int $coach_uid = NULL,
    ?int $program_nid = NULL,
    string $status = 'active'
  ): array {
    $options = [];
    $profile_storage = $this->entityTypeManager->getStorage('profile');

    if (!$this->profileFieldExists(self::EMPLOYEE_PROFILE_TYPE, self::EMPLOYEE_FIELD_COMPANY)) {
      \Drupal::logger('coach_reporting_system')->error(
        'Missing @field on @bundle',
        ['@field' => self::EMPLOYEE_FIELD_COMPANY, '@bundle' => self::EMPLOYEE_PROFILE_TYPE]
      );
      return $options;
    }

    $pquery = $profile_storage->getQuery()
      ->condition('type', self::EMPLOYEE_PROFILE_TYPE)
      ->condition(self::EMPLOYEE_FIELD_COMPANY . '.target_id', $company_uid)
      ->condition('status', 1)
      ->accessCheck(TRUE);

    if (!empty($coach_uid) && $coach_uid > 0 && $this->profileFieldExists(self::EMPLOYEE_PROFILE_TYPE, self::EMPLOYEE_FIELD_COACH)) {
      $pquery->condition(self::EMPLOYEE_FIELD_COACH . '.target_id', (int) $coach_uid);
    }
    // If you want to filter by program too, uncomment this block (and ensure field exists).
    // if (!empty($program_nid) && $this->profileFieldExists(self::EMPLOYEE_PROFILE_TYPE, self::EMPLOYEE_FIELD_PROGRAM)) {
    //   $pquery->condition(self::EMPLOYEE_FIELD_PROGRAM . '.target_id', (int) $program_nid);
    // }

    $pids = $pquery->execute();
    if (!$pids) { return $options; }

    $employee_uids = [];
    foreach ($profile_storage->loadMultiple($pids) as $profile) {
      $uid = (int) $profile->getOwnerId();
      if ($uid > 0) { $employee_uids[$uid] = $uid; }
    }
    if (!$employee_uids) { return $options; }

    $user_storage = $this->entityTypeManager->getStorage('user');
    $uquery = $user_storage->getQuery()
      ->condition('uid', array_values($employee_uids), 'IN')
      ->accessCheck(TRUE);

    if ($status === 'active') { $uquery->condition('status', 1); }
    elseif ($status === 'inactive') { $uquery->condition('status', 0); }

    $uids = $uquery->execute();
    if (!$uids) { return $options; }

    $users = $user_storage->loadMultiple($uids);
    foreach ($users as $user) {
      $full_name = ($user->hasField('field_full_name') && !$user->get('field_full_name')->isEmpty())
        ? trim((string) $user->get('field_full_name')->value)
        : $user->label();
      $email = method_exists($user, 'getEmail') ? $user->getEmail() : ($user->get('mail')->value ?? '');
      $options[$user->id()] = $email ? sprintf('%s (%s)', $full_name, $email) : $full_name;
    }
    asort($options, SORT_NATURAL | SORT_FLAG_CASE);
    return $options;
  }

  /** Return unique company UIDs linked on the coach's active profile. */
  protected function getCompaniesForCoach(int $coach_uid): array {
    $coach_account = $this->entityTypeManager->getStorage('user')->load($coach_uid);
    if (!$coach_account) { return []; }

    $profile_storage = $this->entityTypeManager->getStorage('profile');
    $profile = $profile_storage->loadByUser($coach_account, 'coach', TRUE);
    if (is_array($profile)) { $profile = reset($profile) ?: NULL; }

    if (!$profile) {
      $pids = $profile_storage->getQuery()
        ->condition('type', 'coach')
        ->condition('status', 1)
        ->condition('uid', $coach_uid)
        ->sort('created', 'DESC')
        ->range(0, 1)
        ->accessCheck(TRUE)
        ->execute();
      $profile = $pids ? $profile_storage->load(reset($pids)) : NULL;
      if (!$profile) { return []; }
    }

    if (
      !$this->profileFieldExists('coach', 'field_company') ||
      !$profile->hasField('field_company') ||
      $profile->get('field_company')->isEmpty()
    ) { return []; }

    $company_uids = [];
    foreach ($profile->get('field_company')->referencedEntities() as $company_user) {
      $id = (int) $company_user->id();
      if ($id > 0) { $company_uids[$id] = $id; }
    }
    return array_values($company_uids);
  }

  protected function getCompaniesForEmployee(int $employee_uid): array {
    $profile_storage = $this->entityTypeManager->getStorage('profile');
    $pids = $profile_storage->getQuery()
      ->condition('type', self::EMPLOYEE_PROFILE_TYPE)
      ->condition('uid', $employee_uid)
      ->accessCheck(TRUE)
      ->execute();
    if (!$pids) { return []; }
    $company_uids = [];
    foreach ($profile_storage->loadMultiple($pids) as $profile) {
      if ($this->profileFieldExists(self::EMPLOYEE_PROFILE_TYPE, self::EMPLOYEE_FIELD_COMPANY)
        && $profile->hasField(self::EMPLOYEE_FIELD_COMPANY)
        && !$profile->get(self::EMPLOYEE_FIELD_COMPANY)->isEmpty()) {
        $company_user = $profile->get(self::EMPLOYEE_FIELD_COMPANY)->entity;
        if ($company_user) {
          $company_uids[(int) $company_user->id()] = (int) $company_user->id();
        }
      }
    }
    return array_values($company_uids);
  }

  protected function getEmployeeCoaches(int $employee_uid, ?int $company_uid = NULL): array {
    $profile_storage = $this->entityTypeManager->getStorage('profile');
    $query = $profile_storage->getQuery()
      ->condition('type', self::EMPLOYEE_PROFILE_TYPE)
      ->condition('uid', $employee_uid)
      ->accessCheck(TRUE);
    if (!empty($company_uid) && $this->profileFieldExists(self::EMPLOYEE_PROFILE_TYPE, self::EMPLOYEE_FIELD_COMPANY)) {
      $query->condition(self::EMPLOYEE_FIELD_COMPANY . '.target_id', $company_uid);
    }
    $pids = $query->execute();
    if (!$pids) { return []; }

    $coach_uids = [];
    foreach ($profile_storage->loadMultiple($pids) as $profile) {
      if ($this->profileFieldExists(self::EMPLOYEE_PROFILE_TYPE, self::EMPLOYEE_FIELD_COACH)
        && $profile->hasField(self::EMPLOYEE_FIELD_COACH)
        && !$profile->get(self::EMPLOYEE_FIELD_COACH)->isEmpty()) {
        foreach ($profile->get(self::EMPLOYEE_FIELD_COACH)->referencedEntities() as $coach_user) {
          $coach_uids[(int) $coach_user->id()] = (int) $coach_user->id();
        }
      }
    }
    return array_values($coach_uids);
  }

  // ----------------- Minimal questionnaire helpers -----------------

  protected function buildStepsFromField(\Drupal\node\NodeInterface $node, string $field_name): array {
    $steps = [];
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) { return $steps; }
    foreach ($node->get($field_name)->referencedEntities() as $para) {
      if ($para instanceof \Drupal\paragraphs\ParagraphInterface) {
        $step = $this->stepFromParagraph($para);
        if ($step) { $steps[] = $step; }
      }
    }
    return $steps;
  }

  protected function stepFromParagraph(\Drupal\paragraphs\ParagraphInterface $p): array {
    $bundle = strtolower(str_replace([' ', '-'], '_', $p->bundle()));
    $label  = $this->extractLabel($p) ?: ucfirst(str_replace('_', ' ', $bundle));
    if ($bundle === 'questionnaire') {
      return [
        'widget' => 'matrix',
        'def' => [
          'options' => $this->extractScoreOptions($p),
          'rows'    => $this->collectRowsFromCategories($p),
        ],
        'label' => $label,
      ];
    }
    return [];
  }

  protected function extractScoreOptions(\Drupal\paragraphs\ParagraphInterface $questionnaire): array {
    $opts = [];
    if ($questionnaire->hasField('field_options') && !$questionnaire->get('field_options')->isEmpty()) {
      foreach ($questionnaire->get('field_options')->referencedEntities() as $score_para) {
        $label = $this->extractLabel($score_para);
        $value = $score_para->get('field_option_value')->value;
        $opts[$value] = $label;
      }
    }
    if (!$opts) { $opts = ['100'=>'100','75'=>'75','50'=>'50','25'=>'25','0'=>'0']; }
    krsort($opts, SORT_NUMERIC);
    return $opts;
  }

  protected function collectRowsFromCategories(\Drupal\paragraphs\ParagraphInterface $questionnaire): array {
    $rows = [];
    if ($questionnaire->hasField('field_category') && !$questionnaire->get('field_category')->isEmpty()) {
      foreach ($questionnaire->get('field_category')->referencedEntities() as $cat) {
        $this->collectCategoryDeep($cat, $rows);
      }
    }
    return $rows;
  }

  protected function collectCategoryDeep(\Drupal\paragraphs\ParagraphInterface $p, array &$rows): void {
    $label = $this->extractLabel($p);
    $children = [];
    foreach ($p->getFieldDefinitions() as $fname => $def) {
      if ($def->getType() === 'entity_reference_revisions' &&
          ($def->getSettings()['target_type'] ?? NULL) === 'paragraph' &&
          $p->hasField($fname) && !$p->get($fname)->isEmpty()) {
        foreach ($p->get($fname)->referencedEntities() as $ref) {
          if ($ref instanceof \Drupal\paragraphs\ParagraphInterface) { $children[] = $ref; }
        }
      }
    }
    if ($label !== '') {
      $rows[] = ['type' => 'heading', 'id' => $p->uuid(), 'label' => $label];
    }
    if ($children) {
      foreach ($children as $child) { $this->collectCategoryDeep($child, $rows); }
      return;
    }
    if ($label !== '') {
      $rows[] = ['type' => 'question', 'id' => $p->uuid(), 'label' => $label];
    }
  }

  protected function extractLabel(\Drupal\paragraphs\ParagraphInterface $p): string {
    foreach (['field_category','field_question','field_title','field_label','field_heading','field_name','field_text','field_description','field_option_title'] as $f) {
      if ($p->hasField($f) && !$p->get($f)->isEmpty()) {
        $item = $p->get($f)->first();
        if ($item && isset($item->value) && trim((string) $item->value) !== '') {
          return trim((string) $item->value);
        }
        if ($item && isset($item->processed) && trim((string) $item->processed) !== '') {
          return trim((string) $item->processed);
        }
      }
    }
    return '';
  }
}
