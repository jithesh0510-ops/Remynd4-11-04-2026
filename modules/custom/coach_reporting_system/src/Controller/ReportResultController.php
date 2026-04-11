<?php

namespace Drupal\coach_reporting_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\coach_reporting_system\Utility\QuestionnaireMatrixTrait;

/**
 * Controller for displaying report results with tabs.
 */
class ReportResultController extends ControllerBase {
  use QuestionnaireMatrixTrait;

  /**
   * Display the report results with tabs.
   */
  public function viewResult(Request $request) {
    try {
      $query_params = $request->query->all();

      // Validate required parameters
      $required_params = ['company', 'program', 'employee'];
      foreach ($required_params as $param) {
        if (empty($query_params[$param])) {
          $this->messenger()->addError($this->t('Missing required parameter: @param', ['@param' => $param]));
          return $this->redirect('coach_reporting_system.report');
        }
      }

      $company_uid   = (int) $query_params['company'];
      $program_nid   = (int) $query_params['program'];
      $employee_uid  = (int) $query_params['employee'];
      $coach_uid     = !empty($query_params['coach']) && $query_params['coach'] !== 'all' ? (int) $query_params['coach'] : NULL;
      $report_type   = $query_params['report_type'] ?? 'latest';
      if (!in_array($report_type, ['latest', 'overtime'], TRUE)) {
        $report_type = 'latest';
      }
      $report_content = $query_params['report_content'] ?? [];
      $from_date     = $query_params['from'] ?? NULL;
      $to_date       = $query_params['to'] ?? NULL;
      $download      = !empty($query_params['download']);

      if (!is_array($report_content)) {
        $report_content = [];
      }
      $allowed_content = ['per_person', 'competency_analysis', 'on_job_performance', 'coaching_impact', 'one_to_one_coaching'];
      $report_content = array_values(array_intersect($report_content, $allowed_content));

      // Load entities and validate
      $company  = $this->entityTypeManager()->getStorage('user')->load($company_uid);
      $program  = $this->entityTypeManager()->getStorage('node')->load($program_nid);
      $employee = $this->entityTypeManager()->getStorage('user')->load($employee_uid);
      $coach    = $coach_uid ? $this->entityTypeManager()->getStorage('user')->load($coach_uid) : NULL;

      if (!$company || !$program || !$employee) {
        $this->messenger()->addError($this->t('Invalid company, program, or employee selected.'));
        return $this->redirect('coach_reporting_system.report');
      }

      // Enforce access: user may only view/download reports they are allowed to see.
      if (!$this->currentUserCanAccessReport($company_uid, $coach_uid, $employee_uid)) {
        $this->messenger()->addError($this->t('You do not have permission to view this report.'));
        return $this->redirect('coach_reporting_system.report');
      }

      // If download is requested, export to Excel
      if ($download) {
        return $this->downloadReportAsExcel($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $report_content, $from_date, $to_date);
      }

      // Build the report with tabs
      $build = [
        '#type' => 'container',
        '#attributes' => ['class' => ['report-result-tabs']],
        '#attached' => [
          'library' => ['coach_reporting_system/report_result'],
        ],
      ];

      // Employee details
      $build['employee_details'] = $this->buildEmployeeDetails($company, $program, $employee, $report_type, $from_date, $to_date);

      // Tabs
      $build['tabs'] = $this->buildDynamicTabs($report_content, $query_params, $company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date);

      return $build;
    }
    catch (\Exception $e) {
      \Drupal::logger('coach_reporting_system')->error('Error in viewResult: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t('An error occurred while loading the report. Please try again.'));
      return $this->redirect('coach_reporting_system.report');
    }
  }

  /**
   * Build dynamic tabs based on selected report content.
   */
  protected function buildDynamicTabs($report_content, $query_params, $company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date): array {
    $report_types = [
      'per_person' => [
        'title' => $this->t('Per Person'),
        'description' => $this->t('Individual performance metrics and competency scores'),
        'icon' => 'person',
      ],
      'competency_analysis' => [
        'title' => $this->t('Competency Analysis'),
        'description' => $this->t('Performance analysis over time and across periods'),
        'icon' => 'analytics',
      ],
      'on_job_performance' => [
        'title' => $this->t('On-The-Job Performance Results'),
        'description' => $this->t('Actual workplace performance metrics and achievements'),
        'icon' => 'work',
      ],
      'coaching_impact' => [
        'title' => $this->t('Coaching Impact on Performance'),
        'description' => $this->t('Impact measurement of coaching interventions'),
        'icon' => 'trending_up',
      ],
      'one_to_one_coaching' => [
        'title' => $this->t('Coaching One to One Report'),
        'description' => $this->t('Individual coaching sessions and development plans'),
        'icon' => 'psychology',
      ],
    ];

    if (empty($report_content)) {
      $report_content = array_keys($report_types);
    }

    $nav_tabs = [];
    $tab_panes = [];
    $active_tab = TRUE;

    foreach ($report_types as $key => $config) {
      if (in_array($key, $report_content, TRUE)) {
        $nav_tabs[$key . '_tab'] = [
          '#markup' => '<a href="#tab-' . $key . '" class="nav-link' . ($active_tab ? ' active' : '') . '" data-toggle="tab" role="tab" aria-controls="tab-' . $key . '" aria-selected="' . ($active_tab ? 'true' : 'false') . '" title="' . $config['description'] . '">' . $config['title'] . '</a>',
        ];

        $tab_panes[$key . '_pane'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['tab-pane', 'fade', $active_tab ? 'show active' : ''],
            'id' => 'tab-' . $key,
            'role' => 'tabpanel',
            'aria-labelledby' => 'tab-' . $key,
          ],
          'content' => $this->buildReportContent($key, $company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date),
        ];

        $active_tab = FALSE;
      }
    }

    if (empty($nav_tabs)) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['no-tabs-message']],
        'content' => [
          '#markup' => '<div class="alert alert-warning"><strong>' . $this->t('No report types selected.') . '</strong> ' . $this->t('Please select at least one report type from the form.') . '</div>',
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['report-tabs']],
      'nav' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['nav', 'nav-tabs'],
          'role' => 'tablist',
        ],
        'tabs' => $nav_tabs,
      ],
      'content' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['tab-content']],
        'panes' => $tab_panes,
      ],
    ];
  }

  /**
   * Build employee details section.
   */
  protected function buildEmployeeDetails($company, $program, $employee, $report_type, $from_date, $to_date): array {
    $db = \Drupal::database();
    $company_uid = $company->id();
    $program_nid = $program->id();
    $employee_uid = $employee->id();
    
    $date_display = '';
    if ($report_type === 'latest') {
      // Get the latest session date for this employee
      $latest_session = $db->select('coach_reporting_session', 's')
        ->fields('s', ['submitted'])
        ->condition('company_uid', $company_uid)
        ->condition('program_nid', $program_nid)
        ->condition('employee_uid', $employee_uid)
        ->isNotNull('submitted')
        ->orderBy('submitted', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchField();
      
      if ($latest_session) {
        $date_display = date('d/m/Y', $latest_session);
      } else {
        $date_display = date('d/m/Y'); // Fallback to current date if no sessions found
      }
    } elseif ($from_date && $to_date) {
      $date_display = $from_date . ' to ' . $to_date;
    }

    // Build HTML table format
    $esc = fn($s) => \Drupal\Component\Utility\Html::escape((string) $s);
    
    $html = '<div class="employee-details">';
    $html .= '<table class="employee-details-table">';
    $html .= '<tbody>';
    $html .= '<tr>';
    $html .= '<td class="detail-label">' . $this->t('Company') . ':</td>';
    $html .= '<td class="detail-value">' . $esc($company->label()) . '</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td class="detail-label">' . $this->t('Program') . ':</td>';
    $html .= '<td class="detail-value">' . $esc($program->label()) . '</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td class="detail-label">' . $this->t('Employee') . ':</td>';
    $html .= '<td class="detail-value">' . $esc($employee->label()) . '</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td class="detail-label">' . $this->t('Date') . ' <small>(DD/MM/YY)</small>:</td>';
    $html .= '<td class="detail-value">' . $esc($date_display) . '</td>';
    $html .= '</tr>';
    $html .= '</tbody>';
    $html .= '</table>';
    
    // Action buttons (use route-based URLs for correct base path)
    $download_query = \Drupal::request()->query->all();
    $download_query['download'] = '1';
    $download_url = Url::fromRoute('coach_reporting_system.report_result', [], ['query' => $download_query])->toString();
    $close_url = Url::fromRoute('coach_reporting_system.report')->toString();
    $html .= '<div class="details-buttons">';
    $html .= '<a href="' . $esc($download_url) . '" class="coaching-button">' . $this->t('DOWNLOAD REPORT') . '</a>';
    $html .= '<a href="' . $esc($close_url) . '" class="coaching-button">' . $this->t('CLOSE') . '</a>';
    $html .= '</div>';
    $html .= '</div>';
    
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['employee-details']],
      'content' => [
        '#markup' => $html,
      ],
    ];
  }

  /**
   * Build report content for a specific report type.
   */
  protected function buildReportContent($report_type, $company_uid, $program_nid, $coach_uid, $employee_uid, $report_type_param, $from_date, $to_date): array {
    switch ($report_type) {
      case 'per_person':
        return $this->buildPerPersonContent($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type_param, $from_date, $to_date);

      case 'competency_analysis':
        return $this->buildCompetencyAnalysisContent($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type_param, $from_date, $to_date);

      case 'on_job_performance':
        return $this->buildOnJobPerformanceContent($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type_param, $from_date, $to_date);

      case 'coaching_impact':
        return $this->buildCoachingImpactContent($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type_param, $from_date, $to_date);

      case 'one_to_one_coaching':
        return $this->buildOneToOneCoachingContent($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type_param, $from_date, $to_date);

      default:
        return ['#markup' => '<p>' . $this->t('Report type not implemented yet.') . '</p>'];
    }
  }

  /**
   * Build Per Person report content.
   */
  protected function buildPerPersonContent($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date): array {
    $employee = $this->entityTypeManager()->getStorage('user')->load($employee_uid);
    $employee_name = $employee ? $employee->label() : 'Employee ' . $employee_uid;

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['per-person-report']],
      'content' => [
        '#markup' => $this->buildPerPersonTable($employee_name, $company_uid, $program_nid, $employee_uid, $report_type, $from_date, $to_date),
      ],
    ];
  }

 /**
 * Dynamic Per-Person table (formatted as requested) with 0–100 normalization
 * based on the number of matrix options (even spacing).
 */
protected function buildPerPersonTable($employee_name, $company_uid, $program_nid, $employee_uid, $report_type, $from_date, $to_date): string {
  $db = \Drupal::database();
  $program = $this->entityTypeManager()->getStorage('node')->load((int) $program_nid);
  if (!$program) {
    return '<div class="messages messages--error">'.$this->t('Questionnaire not found.').'</div>';
  }

  // 1) Extract dynamic matrix from Questionnaire paragraphs.
  $steps = $this->buildStepsFromField($program, 'field_create_questionnaire');
  $matrix_def = NULL;
  foreach ($steps as $s) {
    if (($s['widget'] ?? '') === 'matrix') { $matrix_def = $s['def']; break; }
  }
  if (!$matrix_def || empty($matrix_def['rows']) || empty($matrix_def['options'])) {
    return '<div class="messages messages--warning">'.$this->t('This questionnaire has no matrix to report.').'</div>';
  }

  // Keep only headings and questions in order. (type: heading/question; id = row_uuid; label)
  $rows = array_values(array_filter($matrix_def['rows'], fn($r) => in_array($r['type'], ['heading', 'question'], TRUE)));

  // --- Build normalization map (EVENLY spaced 0..100 over the number of options) ---
  // We keep the order as provided by $matrix_def['options'].
  $columns      = $matrix_def['options'];     // key => label (keys can be strings/numbers)
  $option_keys  = array_keys($columns);       // preserve order
  $option_count = max(1, count($option_keys));
  $den          = max(1, $option_count - 1);  // avoid div-by-zero (single option -> always 0)

  // raw stored value (as string) -> normalized percent (float)
  $normalize_map = [];
  foreach (array_values($option_keys) as $i => $rawKey) {
   /* $normalize_map[(string) $rawKey] = ($i * 100.0) / $den;  // e.g. 0,25,50,75,100 (5 options)*/
    $normalize_map[(string)$rawKey] = 100 - ($i * 100.0 / $den);
  }

  // 2) Load submitted sessions for this employee/program/company.
  $q = $db->select('coach_reporting_session', 's')
    ->fields('s', ['sid','submitted'])
    ->condition('company_uid', (int) $company_uid)
    ->condition('program_nid', (int) $program_nid)
    ->condition('employee_uid', (int) $employee_uid)
    ->isNotNull('submitted');

  $is_overtime = ($report_type === 'overtime');
  if ($is_overtime && $from_date && $to_date) {
    try {
      $from_ts = strtotime($from_date . ' 00:00:00') ?: 0;
      $to_ts   = strtotime($to_date   . ' 23:59:59') ?: 0;
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
    return '<div class="messages messages--warning">'.$this->t('No submitted sessions found for the chosen filters.').'</div>';
  }
  $sid_list = array_keys($sessions);

  // 3) Load answers for these sessions + NORMALIZE
  $ans_q = $db->select('coach_reporting_session_answer', 'a')
    ->fields('a', ['sid','row_uuid','value'])
    ->condition('sid', $sid_list, 'IN');
  $answers = $ans_q->execute()->fetchAll();

  // by row_uuid => [normalized values...]
  $by_row_values = [];
  foreach ($answers as $a) {
    $rowUuid = (string) $a->row_uuid;
    if ($rowUuid === '') { continue; }

    $norm = NULL;
    $raw  = (string) $a->value;

    if ($raw !== '' && array_key_exists($raw, $normalize_map)) {
      // Direct match in options → normalize by position.
      $norm = $normalize_map[$raw];
    }
    else {
      // Try integer-cast match (covers "100" vs 100 stored etc.)
      $intKey = (string) ((int) $raw);
      if ($raw !== '' && array_key_exists($intKey, $normalize_map)) {
        $norm = $normalize_map[$intKey];
      }
      elseif (is_numeric($raw)) {
        // If it's a numeric percent already, clamp 0..100 and use it directly.
        $pct  = (float) $raw;
        $norm = max(0.0, min(100.0, $pct));
      } else {
        // Unknown value; skip.
        continue;
      }
    }

    if ($is_overtime) {
      $by_row_values[$rowUuid][] = $norm;
    } else {
      $by_row_values[$rowUuid] = [$norm];
    }
  }

  // Helper to average arrays safely.
  $avg = static function(array $nums): ?float {
    $nums = array_values(array_filter($nums, fn($v) => is_numeric($v)));
    if (!$nums) { return NULL; }
    return array_sum($nums) / count($nums);
  };

  // Compute final per-question normalized score (0..100).
  $per_question_score = [];
  foreach ($rows as $r) {
    if ($r['type'] !== 'question') { continue; }
    $rid = (string) $r['id'];
    if (!empty($by_row_values[$rid])) {
      $per_question_score[$rid] = $is_overtime ? $avg($by_row_values[$rid]) : reset($by_row_values[$rid]);
    } else {
      $per_question_score[$rid] = NULL;
    }
  }

  // 4) Build the requested data structure with full hierarchy (categories, subcategories, sub-subcategories, questions).
  $competencies = []; // [ [category => 'X', subcategories => [ [name => 'Y', sub_subcategories => [ [name => 'Z', questions => [ [name => 'Q', score => 0..100], ... ] ], ... ] ], ... ] ], ... ]
  $current_category = NULL;
  $current_subcategory = NULL;
  $current_sub_subcategory = NULL;
  
  foreach ($rows as $r) {
    if ($r['type'] === 'heading') {
      // Determine hierarchy level based on context and heading patterns
      if ($current_category === NULL || count($competencies) === 0) {
        // This is a main category
        $current_category = $r['label'];
        $competencies[] = [
          'category' => $current_category, 
          'subcategories' => []
        ];
        $current_subcategory = NULL;
        $current_sub_subcategory = NULL;
      } else {
        // Check if this is a subcategory or sub-subcategory
        // If we don't have a subcategory yet, this is a subcategory
        if ($current_subcategory === NULL) {
          $current_subcategory = $r['label'];
          // Add subcategory to current category
          $competencies[count($competencies)-1]['subcategories'][] = [
            'name' => $current_subcategory,
            'sub_subcategories' => []
          ];
          $current_sub_subcategory = NULL;
        } else {
          // This is a sub-subcategory - but only if it's not a question text
          // Check if this looks like a question (contains "How effectively" or similar patterns)
          $is_question_text = preg_match('/^(How|What|Which|When|Where|Why|Does|Can|Will|Should)/i', $r['label']) || 
                              preg_match('/\?$/', $r['label']) ||
                              strlen($r['label']) > 100; // Long text is likely a question
          
          if (!$is_question_text) {
            $current_sub_subcategory = $r['label'];
            // Add sub-subcategory to current subcategory
            $subcategory_index = count($competencies[count($competencies)-1]['subcategories']) - 1;
            $competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories'][] = [
              'name' => $current_sub_subcategory,
              'questions' => []
            ];
          }
          // If it's a question text, we'll handle it in the question section
        }
      }
      continue;
    }
    if ($r['type'] === 'question') {
      if ($current_category === NULL) {
        $current_category = (string) $this->t('General');
        $competencies[] = [
          'category' => $current_category, 
          'subcategories' => []
        ];
      }
      
      $rid   = (string) $r['id'];
      $score = $per_question_score[$rid] ?? NULL; // already 0..100 normalized
      
      // Determine where to place this question in the hierarchy
      if ($current_subcategory === NULL) {
        // No subcategory, add directly to category
        if (empty($competencies[count($competencies)-1]['subcategories'])) {
          $competencies[count($competencies)-1]['subcategories'][] = [
            'name' => (string) $this->t('General Questions'),
            'sub_subcategories' => []
          ];
        }
        $subcategory_index = count($competencies[count($competencies)-1]['subcategories']) - 1;
        if (empty($competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories'])) {
          $competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories'][] = [
            'name' => (string) $this->t('Questions'),
            'questions' => []
          ];
        }
        $sub_subcategory_index = count($competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories']) - 1;
        $competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories'][$sub_subcategory_index]['questions'][] = [
          'name' => $r['label'],
          'score' => $score === NULL ? NULL : (float) $score,
        ];
      } else {
        // We have a subcategory
        $subcategory_index = count($competencies[count($competencies)-1]['subcategories']) - 1;
        
        if ($current_sub_subcategory === NULL) {
          // No sub-subcategory, add directly to subcategory
          if (empty($competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories'])) {
            $competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories'][] = [
              'name' => (string) $this->t('Questions'),
              'questions' => []
            ];
          }
          $sub_subcategory_index = count($competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories']) - 1;
          $competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories'][$sub_subcategory_index]['questions'][] = [
            'name' => $r['label'],
            'score' => $score === NULL ? NULL : (float) $score,
          ];
        } else {
          // We have a sub-subcategory, add to it
          $sub_subcategory_index = count($competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories']) - 1;
          $competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories'][$sub_subcategory_index]['questions'][] = [
            'name' => $r['label'],
            'score' => $score === NULL ? NULL : (float) $score,
          ];
        }
      }
    }
  }

  // Compute averages at all hierarchy levels (still 0..100 scale).
  // Calculate from bottom-up: questions -> sub-subcategories -> subcategories -> categories
  foreach ($competencies as $cat_idx => &$comp) {
    foreach ($comp['subcategories'] as $sc_idx => &$sc) {
      foreach ($sc['sub_subcategories'] as $ssc_idx => &$ssc) {
        // Calculate sub-subcategory average from its questions
        $ssc_vals = [];
        foreach ($ssc['questions'] as $q) {
          if ($q['score'] !== NULL) { $ssc_vals[] = (float) $q['score']; }
        }
        $ssc['average'] = $ssc_vals ? $avg($ssc_vals) : NULL;
      }
      
      // Calculate subcategory average from its sub-subcategories
      $sc_vals = [];
      foreach ($sc['sub_subcategories'] as $ssc) {
        if ($ssc['average'] !== NULL) { $sc_vals[] = (float) $ssc['average']; }
      }
      $sc['average'] = $sc_vals ? $avg($sc_vals) : NULL;
    }
    
    // Calculate category average from its subcategories
    $cat_vals = [];
    foreach ($comp['subcategories'] as $sc) {
      if ($sc['average'] !== NULL) { $cat_vals[] = (float) $sc['average']; }
    }
    $comp['average'] = $cat_vals ? $avg($cat_vals) : NULL;
  }
  unset($comp, $sc, $ssc); // Break references

  // 5) Render HTML per requested format
  $esc = fn($s) => \Drupal\Component\Utility\Html::escape((string) $s);
  $session_count = count($sid_list);

  $style = '';

  $html = $style;
  $html .= '<div class="per-person-table-container">
    <table class="competency-table">
      <thead>
        <tr>
          <th class="competency-header"><div class="heading">'.$this->t('Category / Subcategory / Sub-Subcategory / Question').'</div></th>
          <th class="score-header"><div class="heading">'.$esc($employee_name).' ('.$session_count.'*)</div></th>
          <th class="average-header"><div class="heading">'.$this->t('Overall average per Competency').'</div></th>
        </tr>
      </thead>
      <tbody>';

  foreach ($competencies as $i => $comp) {
    // Category row with its average
    $cat_avg = $comp['average'];
    $cat_avg_text = ($cat_avg === NULL) ? '—' : (is_float($cat_avg) && fmod($cat_avg,1)!==0.0 ? number_format($cat_avg, 1) : (string) (int) round($cat_avg));
    $cat_avg_class = ($cat_avg >= 80) ? 'score-green' : (($cat_avg >= 60) ? 'score-yellow' : 'score-red');
    
    $html .= '<tr class="category-row">
      <td class="category-cell"><div class="category">'.$esc($comp['category']).'</div></td>
      <td class="score-cell '.$cat_avg_class.'"><div class="coaching_impact_field">'.$esc($cat_avg_text).'</div></td>
      <td class="average-cell '.$cat_avg_class.'"><div class="coaching_impact_field">'.$esc($cat_avg_text).'</div></td>
    </tr>';
    
    foreach ($comp['subcategories'] as $sc) {
      // Subcategory row with its average
      $sc_avg = $sc['average'];
      $sc_avg_text = ($sc_avg === NULL) ? '—' : (is_float($sc_avg) && fmod($sc_avg,1)!==0.0 ? number_format($sc_avg, 1) : (string) (int) round($sc_avg));
      $sc_avg_class = ($sc_avg >= 80) ? 'score-green' : (($sc_avg >= 60) ? 'score-yellow' : 'score-red');
      
      $html .= '<tr class="subcategory-row">
        <td class="subcategory-cell"><div class="subcategory">'.$esc($sc['name']).'</div></td>
        <td class="score-cell '.$sc_avg_class.'"><div class="coaching_impact_field">'.$esc($sc_avg_text).'</div></td>
        <td class="average-cell '.$sc_avg_class.'"><div class="coaching_impact_field">'.$esc($sc_avg_text).'</div></td>
      </tr>';
      
      foreach ($sc['sub_subcategories'] as $ssc) {
        // Sub-subcategory row with its average
        $ssc_avg = $ssc['average'];
        $ssc_avg_text = ($ssc_avg === NULL) ? '—' : (is_float($ssc_avg) && fmod($ssc_avg,1)!==0.0 ? number_format($ssc_avg, 1) : (string) (int) round($ssc_avg));
        $ssc_avg_class = ($ssc_avg >= 80) ? 'score-green' : (($ssc_avg >= 60) ? 'score-yellow' : 'score-red');
        
        $html .= '<tr class="sub-subcategory-row">
          <td class="sub-subcategory-cell"><div class="sub-subcategory">'.$esc($ssc['name']).'</div></td>
          <td class="score-cell '.$ssc_avg_class.'"><div class="coaching_impact_field">'.$esc($ssc_avg_text).'</div></td>
          <td class="average-cell '.$ssc_avg_class.'"><div class="coaching_impact_field">'.$esc($ssc_avg_text).'</div></td>
        </tr>';
        
        foreach ($ssc['questions'] as $q) {
          // Individual question row
          $val = $q['score'];                           // 0..100 or NULL
          $val_for_color = $val ?? 0;        // color fallback
          $score_class = ($val_for_color >= 80) ? 'score-green' : (($val_for_color >= 60) ? 'score-yellow' : 'score-red');

          $score_text = ($val === NULL) ? '—' : (is_float($val) && fmod($val,1)!==0.0 ? number_format($val, 1) : (string) (int) round($val));

          $html .= '<tr class="question-row">
            <td class="question-cell"><div class="question">'.$esc($q['name']).'</div></td>
            <td class="score-cell '.$score_class.'"><div class="coaching_impact_field">'.$esc($score_text).'</div></td>
            <td class="average-cell '.$score_class.'"><div class="coaching_impact_field">'.$esc($score_text).'</div></td>
          </tr>';
        }
      }
    }
  }

  $html .= '</tbody>
    </table>
  </div>';

  return $html;
}

  /**
   * (Optional) Old sample implementation kept for reference.
   */
  protected function buildPerPersonTable_old($employee_name, $company_uid, $program_nid, $employee_uid, $report_type, $from_date, $to_date): string {
    // (unchanged from your sample)
    $html = '<div class="alert alert-info">'.$this->t('Old static sample removed for brevity.').'</div>';
    return $html;
  }

  // 1) Wrapper used by buildReportContent()
    protected function buildCompetencyAnalysisContent($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date): array {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['competency-analysis']],
        'content' => [
          '#markup' => $this->buildCompetencyAnalysisTable((int)$company_uid, (int)$program_nid, (int)$employee_uid),
        ],
      ];
    }
    
    // 2) Actual table builder
protected function buildCompetencyAnalysisTable(int $company_uid, int $program_nid, int $employee_uid): string {
  $db = \Drupal::database();
  $program = $this->entityTypeManager()->getStorage('node')->load($program_nid);
  if (!$program) {
    return '<div class="messages messages--error">'.$this->t('Questionnaire not found.').'</div>';
  }

  // --- Questionnaire structure + normalization map (reversed, even-spaced 0..100) ---
  $steps = $this->buildStepsFromField($program, 'field_create_questionnaire');
  $matrix_def = NULL;
  foreach ($steps as $s) { if (($s['widget'] ?? '') === 'matrix') { $matrix_def = $s['def']; break; } }
  if (!$matrix_def || empty($matrix_def['rows']) || empty($matrix_def['options'])) {
    return '<div class="messages messages--warning">'.$this->t('This questionnaire has no matrix to analyze.').'</div>';
  }

  $rows = array_values(array_filter($matrix_def['rows'], fn($r) => in_array($r['type'], ['heading','question'], TRUE)));

  $columns      = $matrix_def['options'];  // key => label (order matters)
  $option_keys  = array_keys($columns);
  $option_count = max(1, count($option_keys));
  $den          = max(1, $option_count - 1);

  // First option -> 100, last -> 0
  $normalize_map = [];
  foreach (array_values($option_keys) as $i => $rawKey) {
    $normalize_map[(string) $rawKey] = 100 - ($i * 100.0 / $den);
  }

  // --- Sessions: all (for full history), last 3 months, and latest ---
  $s_q = $db->select('coach_reporting_session', 's')
    ->fields('s', ['sid','fill_date'])
    ->condition('company_uid', $company_uid)
    ->condition('program_nid', $program_nid)
    ->condition('employee_uid', $employee_uid)
    ->isNotNull('submitted')
    ->orderBy('submitted', 'ASC');
  $sessions = $s_q->execute()->fetchAllAssoc('sid');
  if (!$sessions) {
    return '<div class="messages messages--warning">'.$this->t('No submitted sessions found for this employee.').'</div>';
  }

  $sid_all = array_keys($sessions);
  $now_ts = time();
  $three_months_ago = strtotime('-3 months', $now_ts) ?: ($now_ts - 90*86400);

  $sid_last_3m = [];
  foreach ($sessions as $sid => $obj) {
    $ts = (int) (strtotime($obj->fill_date) ?? 0);
    if ($ts >= $three_months_ago && $ts <= $now_ts) { $sid_last_3m[] = $sid; }
  }
  $sid_latest = [ end($sid_all) ?: reset($sid_all) ];

  // --- Answers (normalize to 0..100) ---
  $ans_q = $db->select('coach_reporting_session_answer', 'a')
    ->fields('a', ['sid','row_uuid','value'])
    ->condition('sid', $sid_all, 'IN');
  $answers = $ans_q->execute()->fetchAll();

  $answers_by_sid_row = []; // [sid][row_uuid] = normalized 0..100
  foreach ($answers as $a) {
    $sid = (int) $a->sid;
    $rid = (string) $a->row_uuid;
    if ($rid === '') { continue; }

    $norm = NULL;
    $raw  = (string) $a->value;

    if ($raw !== '' && array_key_exists($raw, $normalize_map)) {
      $norm = $normalize_map[$raw];
    } else {
      $intKey = (string)((int)$raw);
      if ($raw !== '' && array_key_exists($intKey, $normalize_map)) {
        $norm = $normalize_map[$intKey];
      } elseif (is_numeric($raw)) {
        $pct  = (float)$raw;
        $norm = max(0.0, min(100.0, $pct));
      } else {
        continue;
      }
    }

    $answers_by_sid_row[$sid][$rid] = $norm;
  }

  $avg = static function(array $nums): ?float {
    $nums = array_values(array_filter($nums, fn($v) => is_numeric($v)));
    return $nums ? array_sum($nums) / count($nums) : NULL;
  };

  $per_bucket = function(array $bucket) use ($rows, $answers_by_sid_row, $avg): array {
    $out = [];
    foreach ($rows as $r) {
      if ($r['type'] !== 'question') { continue; }
      $rid = (string)$r['id'];
      $vals = [];
      foreach ($bucket as $sid) {
        if (isset($answers_by_sid_row[$sid][$rid])) {
          $vals[] = (float)$answers_by_sid_row[$sid][$rid];
        }
      }
      $out[$rid] = $vals ? $avg($vals) : NULL;
    }
    return $out;
  };

  $pq_full   = $per_bucket($sid_all);
  $pq_last3  = $sid_last_3m ? $per_bucket($sid_last_3m) : [];
  $pq_latest = $per_bucket($sid_latest);

  // --- Build full hierarchy (categories, subcategories, sub-subcategories, questions) ---
  $competencies = [];
  $current_category = NULL;
  $current_subcategory = NULL;
  $current_sub_subcategory = NULL;
  
  foreach ($rows as $r) {
    if ($r['type'] === 'heading') {
      // Determine hierarchy level
      if ($current_category === NULL || count($competencies) === 0) {
        // This is a main category
        $current_category = $r['label'];
        $competencies[] = [
          'category' => $current_category, 
          'subcategories' => []
        ];
        $current_subcategory = NULL;
        $current_sub_subcategory = NULL;
      } else {
        // Check if this is a subcategory or sub-subcategory
        if ($current_subcategory === NULL) {
          $current_subcategory = $r['label'];
          $competencies[count($competencies)-1]['subcategories'][] = [
            'name' => $current_subcategory,
            'sub_subcategories' => []
          ];
          $current_sub_subcategory = NULL;
        } else {
          // Check if this looks like a question text
          $is_question_text = preg_match('/^(How|What|Which|When|Where|Why|Does|Can|Will|Should)/i', $r['label']) || 
                              preg_match('/\?$/', $r['label']) ||
                              strlen($r['label']) > 100;
          
          if (!$is_question_text) {
            $current_sub_subcategory = $r['label'];
            $subcategory_index = count($competencies[count($competencies)-1]['subcategories']) - 1;
            $competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories'][] = [
              'name' => $current_sub_subcategory,
              'questions' => []
            ];
          }
        }
      }
      continue;
    }
    if ($r['type'] === 'question') {
      if ($current_category === NULL) {
        $current_category = (string)$this->t('General');
        $competencies[] = [
          'category' => $current_category, 
          'subcategories' => []
        ];
      }
      
      $rid = (string)$r['id'];
      
      // Place question in hierarchy
      if ($current_subcategory === NULL) {
        if (empty($competencies[count($competencies)-1]['subcategories'])) {
          $competencies[count($competencies)-1]['subcategories'][] = [
            'name' => (string)$this->t('General Questions'),
            'sub_subcategories' => []
          ];
        }
        $subcategory_index = count($competencies[count($competencies)-1]['subcategories']) - 1;
        if (empty($competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories'])) {
          $competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories'][] = [
            'name' => (string)$this->t('Questions'),
            'questions' => []
          ];
        }
        $sub_subcategory_index = count($competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories']) - 1;
        $competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories'][$sub_subcategory_index]['questions'][] = [
          'name' => $r['label'],
          'rid' => $rid,
        ];
      } else {
        $subcategory_index = count($competencies[count($competencies)-1]['subcategories']) - 1;
        
        if ($current_sub_subcategory === NULL) {
          if (empty($competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories'])) {
            $competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories'][] = [
              'name' => (string)$this->t('Questions'),
              'questions' => []
            ];
          }
          $sub_subcategory_index = count($competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories']) - 1;
          $competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories'][$sub_subcategory_index]['questions'][] = [
            'name' => $r['label'],
            'rid' => $rid,
          ];
        } else {
          $sub_subcategory_index = count($competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories']) - 1;
          $competencies[count($competencies)-1]['subcategories'][$subcategory_index]['sub_subcategories'][$sub_subcategory_index]['questions'][] = [
            'name' => $r['label'],
            'rid' => $rid,
          ];
        }
      }
    }
  }

  // Calculate averages for all hierarchy levels (bottom-up: questions -> sub-subcategories -> subcategories -> categories)
  foreach ($competencies as $cat_idx => &$comp) {
    foreach ($comp['subcategories'] as $sc_idx => &$sc) {
      foreach ($sc['sub_subcategories'] as $ssc_idx => &$ssc) {
        // Calculate sub-subcategory averages from questions
        $vf_ssc = $vl3_ssc = $vlt_ssc = [];
        foreach ($ssc['questions'] as $q) {
          $rid = $q['rid'];
          if (array_key_exists($rid, $pq_full) && $pq_full[$rid] !== NULL) { $vf_ssc[] = (float)$pq_full[$rid]; }
          if ($sid_last_3m && array_key_exists($rid, $pq_last3) && $pq_last3[$rid] !== NULL) { $vl3_ssc[] = (float)$pq_last3[$rid]; }
          if (array_key_exists($rid, $pq_latest) && $pq_latest[$rid] !== NULL) { $vlt_ssc[] = (float)$pq_latest[$rid]; }
        }
        $ssc['avg_full'] = $vf_ssc ? $avg($vf_ssc) : NULL;
        $ssc['avg_last3'] = $vl3_ssc ? $avg($vl3_ssc) : NULL;
        $ssc['avg_latest'] = $vlt_ssc ? $avg($vlt_ssc) : NULL;
      }
      
      // Calculate subcategory averages from sub-subcategories
      $vf_sc = $vl3_sc = $vlt_sc = [];
      foreach ($sc['sub_subcategories'] as $ssc) {
        if ($ssc['avg_full'] !== NULL) { $vf_sc[] = (float)$ssc['avg_full']; }
        if ($ssc['avg_last3'] !== NULL) { $vl3_sc[] = (float)$ssc['avg_last3']; }
        if ($ssc['avg_latest'] !== NULL) { $vlt_sc[] = (float)$ssc['avg_latest']; }
      }
      $sc['avg_full'] = $vf_sc ? $avg($vf_sc) : NULL;
      $sc['avg_last3'] = $vl3_sc ? $avg($vl3_sc) : NULL;
      $sc['avg_latest'] = $vlt_sc ? $avg($vlt_sc) : NULL;
    }
    
    // Calculate category averages from subcategories
    $vf_cat = $vl3_cat = $vlt_cat = [];
    foreach ($comp['subcategories'] as $sc) {
      if ($sc['avg_full'] !== NULL) { $vf_cat[] = (float)$sc['avg_full']; }
      if ($sc['avg_last3'] !== NULL) { $vl3_cat[] = (float)$sc['avg_last3']; }
      if ($sc['avg_latest'] !== NULL) { $vlt_cat[] = (float)$sc['avg_latest']; }
    }
    $comp['avg_full'] = $vf_cat ? $avg($vf_cat) : NULL;
    $comp['avg_last3'] = $vl3_cat ? $avg($vl3_cat) : NULL;
    $comp['avg_latest'] = $vlt_cat ? $avg($vlt_cat) : NULL;
  }
  unset($comp, $sc, $ssc); // Break references

  $esc = fn($s) => \Drupal\Component\Utility\Html::escape((string)$s);
  $fmt = static function($v) { return $v === NULL ? '—' : ((is_float($v) && fmod($v,1)!==0.0) ? number_format($v, 1) : (string)(int)round($v)); };

  $html = '<div class="competency-analysis-table-container">
    <table class="competency-table">
      <thead>
        <tr>
          <th class="competency-header"><div class="heading">'.$this->t('Category / Subcategory / Sub-Subcategory / Question').'</div></th>
          <th class="avg-full"><div class="heading">'.$this->t('Full History').'</div></th>
          <th class="avg-last3"><div class="heading">'.$this->t('Previous 3 months').'</div></th>
          <th class="avg-latest"><div class="heading">'.$this->t('Latest').'</div></th>
        </tr>
      </thead>
      <tbody>';

  // Helper to get color class based on score
  $getColorClass = function($value) {
    if ($value === NULL) return '';
    if ($value >= 100) return 'score-green';
    if ($value >= 60) return 'score-yellow';
    return 'score-red';
  };

  foreach ($competencies as $i => $comp) {
    // Category row with averages
    $cat_f = $comp['avg_full'];
    $cat_l3 = $comp['avg_last3'];
    $cat_lt = $comp['avg_latest'];
    
    $cat_f_class = $getColorClass($cat_f);
    $cat_l3_class = $getColorClass($cat_l3);
    $cat_lt_class = $getColorClass($cat_lt);
    
    $html .= '<tr class="category-row">
      <td class="category-cell"><div class="category">'.$esc($comp['category']).'</div></td>
      <td class="avg-full-cell '.$cat_f_class.'"><div class="coaching_impact_field">'.$esc($fmt($cat_f)).'</div></td>
      <td class="avg-last3-cell '.$cat_l3_class.'"><div class="coaching_impact_field">'.$esc($fmt($cat_l3)).'</div></td>
      <td class="avg-latest-cell '.$cat_lt_class.'"><div class="coaching_impact_field">'.$esc($fmt($cat_lt)).'</div></td>
    </tr>';

    foreach ($comp['subcategories'] as $sc) {
      // Subcategory row with averages
      $sc_f = $sc['avg_full'];
      $sc_l3 = $sc['avg_last3'];
      $sc_lt = $sc['avg_latest'];
      
      $sc_f_class = $getColorClass($sc_f);
      $sc_l3_class = $getColorClass($sc_l3);
      $sc_lt_class = $getColorClass($sc_lt);
      
      $html .= '<tr class="subcategory-row">
        <td class="subcategory-cell"><div class="subcategory">'.$esc($sc['name']).'</div></td>
        <td class="avg-full-cell '.$sc_f_class.'"><div class="coaching_impact_field">'.$esc($fmt($sc_f)).'</div></td>
        <td class="avg-last3-cell '.$sc_l3_class.'"><div class="coaching_impact_field">'.$esc($fmt($sc_l3)).'</div></td>
        <td class="avg-latest-cell '.$sc_lt_class.'"><div class="coaching_impact_field">'.$esc($fmt($sc_lt)).'</div></td>
      </tr>';
      
      foreach ($sc['sub_subcategories'] as $ssc) {
        // Sub-subcategory row with averages
        $ssc_f = $ssc['avg_full'];
        $ssc_l3 = $ssc['avg_last3'];
        $ssc_lt = $ssc['avg_latest'];
        
        $ssc_f_class = $getColorClass($ssc_f);
        $ssc_l3_class = $getColorClass($ssc_l3);
        $ssc_lt_class = $getColorClass($ssc_lt);
        
        $html .= '<tr class="sub-subcategory-row">
          <td class="sub-subcategory-cell"><div class="sub-subcategory">'.$esc($ssc['name']).'</div></td>
          <td class="avg-full-cell '.$ssc_f_class.'"><div class="coaching_impact_field">'.$esc($fmt($ssc_f)).'</div></td>
          <td class="avg-last3-cell '.$ssc_l3_class.'"><div class="coaching_impact_field">'.$esc($fmt($ssc_l3)).'</div></td>
          <td class="avg-latest-cell '.$ssc_lt_class.'"><div class="coaching_impact_field">'.$esc($fmt($ssc_lt)).'</div></td>
        </tr>';
        
        foreach ($ssc['questions'] as $q) {
          // Individual question row
          $rid = $q['rid'];
          $q_f = $pq_full[$rid] ?? NULL;
          $q_l3 = $pq_last3[$rid] ?? NULL;
          $q_lt = $pq_latest[$rid] ?? NULL;
          
          $q_f_class = $getColorClass($q_f);
          $q_l3_class = $getColorClass($q_l3);
          $q_lt_class = $getColorClass($q_lt);
          
          $html .= '<tr class="question-row">
            <td class="question-cell"><div class="question">'.$esc($q['name']).'</div></td>
            <td class="avg-full-cell '.$q_f_class.'"><div class="coaching_impact_field">'.$esc($fmt($q_f)).'</div></td>
            <td class="avg-last3-cell '.$q_l3_class.'"><div class="coaching_impact_field">'.$esc($fmt($q_l3)).'</div></td>
            <td class="avg-latest-cell '.$q_lt_class.'"><div class="coaching_impact_field">'.$esc($fmt($q_lt)).'</div></td>
          </tr>';
        }
      }
    }
  }

  $html .= '</tbody></table></div>';

  return $html;
}



  protected function buildOnJobPerformanceContent($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date): array {
    $table_html = $this->buildOnJobPerformanceTable($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date);
    
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['on-job-performance']],
      'content' => [
        '#markup' => $table_html,
      ],
    ];
  }

  /**
   * Build On-The-Job Performance Results table in the same format as questionnaireresult.php.
   */
  protected function buildOnJobPerformanceTable($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date): string {
    $db = \Drupal::database();
    $esc = fn($s) => \Drupal\Component\Utility\Html::escape((string) $s);
    
    // Check if table exists
    if (!$db->schema()->tableExists('qs_emp_lagard_starts')) {
      return '<div class="messages messages--error">' . $this->t('On-The-Job performance data table does not exist. Please contact administrator.') . '</div>';
    }
    
    // Get employee information
    $employee = $this->entityTypeManager()->getStorage('user')->load($employee_uid);
    if (!$employee) {
      return '<div class="messages messages--error">' . $this->t('Employee not found.') . '</div>';
    }
    
    $employee_name = $employee->label();
    
    // Get coach information from employee profile
    $coach_name = 'N/A';
    $profile_storage = $this->entityTypeManager()->getStorage('profile');
    $profiles = $profile_storage->loadByProperties([
      'uid' => $employee_uid,
      'type' => 'employee',
      'status' => 1
    ]);
    
    if (!empty($profiles)) {
      $profile = reset($profiles);
      if ($profile->hasField('field_coach') && !$profile->get('field_coach')->isEmpty()) {
        $coach_ids = $profile->get('field_coach')->getValue();
        if (!empty($coach_ids)) {
          $first_coach_id = $coach_ids[0]['target_id'];
          $coach = $this->entityTypeManager()->getStorage('user')->load($first_coach_id);
          if ($coach) {
            $coach_name = $coach->label();
          }
        }
      }
    }
    
    // Build query to fetch On-The-Job performance data
    $query = $db->select('qs_emp_lagard_starts', 'q')
      ->fields('q', ['month', 'target_forecasted', 'target_achieved', 'created'])
      ->condition('employee_uid', $employee_uid)
      ->condition('company_uid', $company_uid);
    
    if ($program_nid) {
      $query->condition('questionnaire_id', $program_nid);
    }
    
    // Apply date filtering - filter by month field to match exact months
    if ($report_type === 'latest') {
      // Get last 3 months of data (matching Coaching Impact logic)
      $to_ts = time();
      $from_ts = strtotime('-3 months', $to_ts);
      $from_month = date('Y-m', $from_ts);
      $to_month = date('Y-m', $to_ts);
      $query->condition('month', [$from_month, $to_month], 'BETWEEN');
    } else {
      // Filter by date range
      if ($from_date && $to_date) {
        $from_ts = strtotime($from_date . ' 00:00:00') ?: 0;
        $to_ts = strtotime($to_date . ' 23:59:59') ?: 0;
        if ($from_ts && $to_ts) {
          $from_month = date('Y-m', $from_ts);
          $to_month = date('Y-m', $to_ts);
          $query->condition('month', [$from_month, $to_month], 'BETWEEN');
        }
      }
    }
    
    $query->orderBy('month', 'ASC');
    $results = $query->execute()->fetchAll();
    
    if (empty($results)) {
      return '<div class="messages messages--warning">' . $this->t('No On-The-Job performance data found.') . '</div>';
    }
    
    // Categorize results into Stars, Core, and Laggards
    $categorized_data = [
      'Stars' => [],
      'Core' => [],
      'Laggards' => []
    ];
    
    foreach ($results as $result) {
      if ($result->target_forecasted > 0) {
        $percentage = ($result->target_achieved / $result->target_forecasted) * 100;
        $ratio = $result->target_achieved / $result->target_forecasted;
        $category = '';
        $rating = 0;
        
        // Match original logic exactly: Stars if achieved >= forecasted OR ratio > 0.99 (99%+)
        if (($result->target_achieved >= $result->target_forecasted) || ($ratio > 0.99)) {
          $category = 'Stars';
          $rating = 3;
        } elseif ($ratio >= 0.6 && $ratio <= 0.99) {
          $category = 'Core';
          $rating = 2;
        } else {
          $category = 'Laggards';
          $rating = 1;
        }
        
        // Extract month and year from month field (format: Y-m)
        $month_parts = explode('-', $result->month);
        $on_month = isset($month_parts[1]) ? (int)$month_parts[1] : 0;
        $on_year = isset($month_parts[0]) ? (int)$month_parts[0] : 0;
        
        // Format percentage to 2 decimal places if needed
        $formatted_percentage = (fmod($percentage, 1) == 0) ? (string)round($percentage) : number_format($percentage, 2, '.', '');
        
        $categorized_data[$category][] = [
          'employee_uid' => $employee_uid,
          'employee_name' => $employee_name,
          'coach_name' => $coach_name, // Coach/Manager name
          'month' => $on_month,
          'year' => $on_year,
          'target_forecasted' => $result->target_forecasted,
          'target_achieved' => $result->target_achieved,
          'percentage' => $formatted_percentage, // Round to 2 decimal places if needed
          'rating' => $rating
        ];
      }
    }
    
    // Define category display settings (same as questionnaireresult.php)
    // Using numeric keys like original: 3 => Stars, 2 => Core, 1 => Laggards
    $category_config = [
      3 => [
        'title' => 'Stars',
        'category_key' => 'Stars',
        'rot_title' => 'Stars (100% +)',
        'background' => '#b3e2c7',
        'color' => '#000'
      ],
      2 => [
        'title' => 'Core',
        'category_key' => 'Core',
        'rot_title' => 'Core Performers (between 60 and 99%)',
        'background' => '#FFDD7D',
        'color' => '#000'
      ],
      1 => [
        'title' => 'Laggards',
        'category_key' => 'Laggards',
        'rot_title' => 'Laggards (less than 60%)',
        'background' => '#F95959',
        'color' => '#fff'
      ]
    ];
    
    // Build HTML output
    $html = '';
    
    foreach ($category_config as $rating_key => $category_info) {
      $category_name = $category_info['category_key'];
      $category_css_class = 'category-' . strtolower($category_name);
      
      // Category header table with color-coded CSS class
      $html .= '<table class="table mt-20 ' . $category_css_class . '" style="margin-top: 20px;">
        <tr align="center" style="background-color: ' . $category_info['background'] . '; color: ' . $category_info['color'] . ';">
          <th colspan="7">' . $esc($this->t($category_info['rot_title'])) . '</th>
        </tr>
      </table>';
      
      // Data table
      $html .= '<div class="no-more-tables">
        <table class="table mt-20 start_legard_table">
          <thead>
            <tr>
              <th width="100">' . $this->t('Reference') . '</th>
              <th width="200">' . $this->t('Manager name') . '</th>
              <th width="200">' . $this->t('Month') . '</th>
              <th width="200">' . $this->t('Target forecasted') . '</th>
              <th width="200">' . $this->t('Target Achieved') . '</th>
              <th width="200">' . $this->t('Achieved') . ' %</th>
              <th>' . $this->t('Rating') . '</th>
            </tr>
          </thead>
          <tbody>';
      
      // Add data rows if available
      if (!empty($categorized_data[$category_name])) {
        // Determine row CSS class based on category name
        $row_class = 'performance-' . strtolower($category_name);
        
        foreach ($categorized_data[$category_name] as $data) {
          $month_name = date('F', mktime(0, 0, 0, $data['month'], 10));
          
          $html .= '<tr class="' . $row_class . '">
            <td data-title="' . $this->t('Reference') . '">' . $esc($data['employee_uid']) . '</td>
            <td data-title="' . $this->t('Manager name') . '">' . $esc($data['coach_name']) . '</td>
            <td data-title="' . $this->t('Month') . '">' . $esc($month_name) . '-' . $esc($data['year']) . '</td>
            <td data-title="' . $this->t('Target forecasted') . '">' . $esc($data['target_forecasted']) . '</td>
            <td data-title="' . $this->t('Target Achieved') . '">' . $esc($data['target_achieved']) . '</td>
            <td data-title="' . $this->t('Achieved') . ' %" class="detect_color">' . $esc($data['percentage']) . '%</td>
            <td data-title="' . $this->t('Rating') . '">' . $esc($rating_key) . '</td>
          </tr>';
        }
      }
      
      $html .= '</tbody>
        </table>
      </div>';
    }
    
    return $html;
  }

  protected function buildCoachingImpactContent($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date): array {
    $db = \Drupal::database();
    
    // Generate monthly periods for chart data
    $monthly_periods = [];
    $monthly_behavioral_data = [];
    $monthly_onjob_data = [];
    
    if ($report_type === 'latest') {
      $to_ts = time();
      $from_ts = strtotime('-2 months', $to_ts);
      $current_ts = $from_ts;
      
      while ($current_ts <= $to_ts) {
        $month_display = date('M Y', $current_ts);
        $month_start = strtotime(date('Y-m-01 00:00:00', $current_ts));
        $month_end = strtotime(date('Y-m-t 23:59:59', $current_ts));
        
        $monthly_periods[] = $month_display;
        
        $date_cond = ['submitted' => [$month_start, $month_end], 'operator' => 'BETWEEN'];
        $behavioral_data = $this->getBehavioralPerformanceData($company_uid, $program_nid, $employee_uid, $date_cond);
        $onjob_data = $this->getOnJobProgressData($company_uid, $program_nid, $employee_uid, $date_cond);
        
        $monthly_behavioral_data[] = $behavioral_data['percentage'] ?? 0;
        $monthly_onjob_data[] = $onjob_data['percentage'] ?? 0;
        
        $current_ts = strtotime('+1 month', $current_ts);
      }
    }
    
    $table_html = $this->buildCoachingImpactTable($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date);
    
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['coaching-impact']],
      'content' => [
        '#markup' => $table_html,
      ],
      '#attached' => [
        'library' => ['coach_reporting_system/report_result'],
        'drupalSettings' => [
          'coachingImpact' => [
            'behavioralChartId' => 'behavioral_progress_chart_' . $employee_uid,
            'onjobChartId' => 'On_The_Job_Progress_chart_' . $employee_uid,
            'monthlyPeriods' => $monthly_periods,
            'behavioralData' => $monthly_behavioral_data,
            'onjobData' => $monthly_onjob_data,
            'periodDisplay' => implode(', ', array_slice($monthly_periods, 0, 3)) . '...',
          ],
        ],
      ],
    ];
  }

  /**
   * Build Coaching Impact on Performance table with dynamic data and charts.
   */
  protected function buildCoachingImpactTable($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date): string {
    $db = \Drupal::database();
    
    // Get employee information
    $employee = $this->entityTypeManager()->getStorage('user')->load($employee_uid);
    if (!$employee) {
      return '<div class="messages messages--error">' . $this->t('Employee not found.') . '</div>';
    }
    
    $employee_name = $employee->label();
    $employee_parts = explode(' ', $employee_name);
    $first_name = $employee_parts[0] ?? '';
    $last_name = implode(' ', array_slice($employee_parts, 1)) ?: '';
    
    // Determine date range and monthly periods
    $monthly_periods = [];
    $from_ts = 0;
    $to_ts = 0;
    
    if ($report_type === 'latest') {
      // For latest report, get last 3 months of data
      $to_ts = time();
      $from_ts = strtotime('-3 months', $to_ts);
      
      // Generate monthly periods
      $current_ts = $from_ts;
      while ($current_ts <= $to_ts) {
        $month_key = date('Y-m', $current_ts);
        $month_display = date('M Y', $current_ts);
        $month_start = strtotime(date('Y-m-01 00:00:00', $current_ts));
        $month_end = strtotime(date('Y-m-t 23:59:59', $current_ts));
        
        $monthly_periods[] = [
          'key' => $month_key,
          'display' => $month_display,
          'start' => $month_start,
          'end' => $month_end
        ];
        
        $current_ts = strtotime('+1 month', $current_ts);
      }
    } elseif ($report_type === 'overtime' && $from_date && $to_date) {
      $from_ts = strtotime($from_date . ' 00:00:00') ?: 0;
      $to_ts = strtotime($to_date . ' 23:59:59') ?: 0;
      
      if ($from_ts && $to_ts) {
        // Generate monthly periods for the date range
        $current_ts = $from_ts;
        while ($current_ts <= $to_ts) {
          $month_key = date('Y-m', $current_ts);
          $month_display = date('M Y', $current_ts);
          $month_start = strtotime(date('Y-m-01 00:00:00', $current_ts));
          $month_end = strtotime(date('Y-m-t 23:59:59', $current_ts));
          
          $monthly_periods[] = [
            'key' => $month_key,
            'display' => $month_display,
            'start' => $month_start,
            'end' => $month_end
          ];
          
          $current_ts = strtotime('+1 month', $current_ts);
        }
      }
    }
    
    // If no monthly periods, use a single period (all time)
    if (empty($monthly_periods)) {
      $monthly_periods[] = [
        'key' => 'all',
        'display' => $this->t('All Time'),
        'start' => 0,
        'end' => time()
      ];
    }
    
    // Get pre/post training data
    $prepost_data = $this->getPrePostTrainingData($company_uid, $program_nid, $employee_uid);
    
    // Get monthly behavioral performance data
    $monthly_behavioral = [];
    $monthly_onjob = [];
    
    foreach ($monthly_periods as $period) {
      $date_cond = ['submitted' => [$period['start'], $period['end']], 'operator' => 'BETWEEN'];
      $monthly_behavioral[$period['key']] = $this->getBehavioralPerformanceData($company_uid, $program_nid, $employee_uid, $date_cond);
      $monthly_onjob[$period['key']] = $this->getOnJobProgressData($company_uid, $program_nid, $employee_uid, $date_cond);
    }
    
    // Calculate cumulative results
    $all_behavioral = $this->getBehavioralPerformanceData($company_uid, $program_nid, $employee_uid, []);
    $all_onjob = $this->getOnJobProgressData($company_uid, $program_nid, $employee_uid, []);
    $cumulative_results = $this->calculateCumulativeResults($prepost_data, $all_behavioral, $all_onjob);
    
    // Build the table HTML
    $esc = fn($s) => \Drupal\Component\Utility\Html::escape((string) $s);
    
    $month_count = count($monthly_periods);
    $total_cols = 2 + 3 + $month_count + $month_count + 3; // Name(2) + Skills(3) + Behavioral(months) + OnJob(months) + Cumulative(3)
    
    $html = '<div class="coaching-impact-table-container">';
    $html .= '<table class="table table-bordereds coaching_table rounded rounded-3 name_table" border="0">';
    
    // Table header - Main categories
    $html .= '<thead class="coaching_thead">';
    $html .= '<tr align="center" class="cat_bg">';
    $html .= '<th colspan="2" class="remove_all"></th>';
    $html .= '<th colspan="3">';
    $html .= '<div class="category">Skills Assessments Results in %</div>';
    $html .= '</th>';
    $html .= '<th colspan="'.$month_count.'">';
    $html .= '<div class="category">Behavioral Performance Results in %</div>';
    $html .= '</th>';
    $html .= '<th colspan="'.$month_count.'">';
    $html .= '<div class="category">On the Job Performance Results in %</div>';
    $html .= '</th>';
    $html .= '<th colspan="3">';
    $html .= '<div class="category">Cumulative Results in %</div>';
    $html .= '</th>';
    $html .= '</tr>';
    $html .= '</thead>';
    
    // Sub-header with monthly columns
    $html .= '<tbody>';
    $html .= '<tr align="center" class="subcat_bg">';
    $html .= '<th><div class="subheading">Surname</div></th>';
    $html .= '<th><div class="subheading">First name</div></th>';
    $html .= '<th><div class="subheading">Pre Skills Result</div></th>';
    $html .= '<th><div class="subheading">Post Skills Result</div></th>';
    $html .= '<th><div class="subheading">Progress Results</div></th>';
    
    // Monthly columns for Behavioral Performance
    foreach ($monthly_periods as $period) {
      $html .= '<th><div class="subheading">' . $esc($period['display']) . '</div></th>';
    }
    
    // Monthly columns for On-The-Job Performance
    foreach ($monthly_periods as $period) {
      $html .= '<th><div class="subheading">' . $esc($period['display']) . '</div></th>';
    }
    
    // Cumulative columns
    $html .= '<th><div class="subheading">Behavioral Results till date</div></th>';
    $html .= '<th><div class="subheading">On-The-Job Results till date</div></th>';
    $html .= '<th><div class="subheading">Merging Behavioral and On-The-Job Results</div></th>';
    $html .= '</tr>';
    
    // Data row
    $html .= '<tr align="center">';
    $html .= '<td><div class="coaching_impact_field"><strong>' . $esc($last_name) . '</strong></div></td>';
    $html .= '<td><div class="coaching_impact_field"><strong>' . $esc($first_name) . '</strong></div></td>';
    
    // Helper to get color class based on score
    $getColorClass = function($value) {
      if ($value === NULL || $value === 'N/A') return '';
      $numValue = is_numeric($value) ? (float)$value : 0;
      if ($numValue >= 100) return 'score-green';
      if ($numValue >= 60) return 'score-yellow';
      return 'score-red';
    };
    
    // Pre/Post Skills Results
    $pre_skills = $prepost_data['pre_grade'] ?? 0;
    $post_skills = $prepost_data['post_grade'] ?? 0;
    $skills_progress = 0;
    if ($pre_skills !== 0 && $post_skills !== 0) {
      $skills_progress = $post_skills - $pre_skills;
    }
    
    $pre_class = $getColorClass($pre_skills);
    $post_class = $getColorClass($post_skills);
    $progress_class = $getColorClass($post_skills); // Use post score for color
    
    $html .= '<td class="'.$pre_class.'"><div class="coaching_impact_field">' . $esc($pre_skills) . '</div></td>';
    $html .= '<td class="'.$post_class.'"><div class="coaching_impact_field">' . $esc($post_skills) . '</div></td>';
    $html .= '<td class="'.$progress_class.'"><div class="coaching_impact_field">' . $esc($skills_progress) . '%</div></td>';
    
    // Helper to format percentage to 2 decimal places if needed
    $formatPercentage = function($value) {
      if ($value === NULL || $value === '') return '0';
      $numValue = (float)$value;
      // If it's a whole number, show without decimals, otherwise show up to 2 decimal places
      return (fmod($numValue, 1) == 0) ? (string)round($numValue) : number_format($numValue, 2, '.', '');
    };
    
    // Monthly Behavioral Performance Results
    foreach ($monthly_periods as $period) {
      $behavioral_percentage = $monthly_behavioral[$period['key']]['percentage'] ?? 0;
      $behavioral_class = $getColorClass($behavioral_percentage);
      $html .= '<td class="'.$behavioral_class.'"><div class="coaching_impact_field">' . $esc($formatPercentage($behavioral_percentage)) . '%</div></td>';
    }
    
    // Monthly On-The-Job Performance Results - Use exact same values as On-The-Job Performance Results table
    foreach ($monthly_periods as $period) {
      $onjob_percentage = $monthly_onjob[$period['key']]['percentage'] ?? 0;
      $onjob_class = $getColorClass($onjob_percentage);
      $html .= '<td class="'.$onjob_class.'"><div class="coaching_impact_field">' . $esc($formatPercentage($onjob_percentage)) . '%</div></td>';
    }
    
    // Cumulative Results
    $behavioral_till_date = $cumulative_results['behavioral_till_date'] ?? 0;
    $onjob_till_date = $cumulative_results['onjob_till_date'] ?? 0;
    $merged_results = $cumulative_results['merged_results'] ?? 0;
    
    $behavioral_till_class = $getColorClass($behavioral_till_date);
    $onjob_till_class = $getColorClass($onjob_till_date);
    $merged_class = $getColorClass($merged_results);
    
    $html .= '<td class="'.$behavioral_till_class.'"><div class="coaching_impact_field">' . $esc($formatPercentage($behavioral_till_date)) . '%</div></td>';
    $html .= '<td class="'.$onjob_till_class.'"><div class="coaching_impact_field">' . $esc($formatPercentage($onjob_till_date)) . '%</div></td>';
    $html .= '<td class="'.$merged_class.'"><div class="coaching_impact_field">' . $esc($formatPercentage($merged_results)) . '%</div></td>';
    
    $html .= '</tr>';
    
    // Add charts row below data row
    $behavioral_chart_id = 'behavioral_progress_chart_' . $employee_uid;
    $onjob_chart_id = 'On_The_Job_Progress_chart_' . $employee_uid;
    
    $html .= '<tr class="charts-row">';
    $html .= '<td colspan="2"><div class="chart-label">' . $this->t('Progress Charts') . '</div></td>';
    $html .= '<td colspan="3"></td>'; // Skills columns
    
    // Behavioral Chart spanning behavioral monthly columns
    $html .= '<td colspan="' . $month_count . '" class="chart-cell">';
    $html .= '<div class="inline-chart-container">';
    $html .= '<div class="chart-header">' . $this->t('Behavioral Progress Chart') . '</div>';
    $html .= '<div id="' . $behavioral_chart_id . '" class="inline-chart" style="min-height: 200px; width: 100%;"></div>';
    $html .= '</div>';
    $html .= '</td>';
    
    // On-The-Job Chart spanning on-the-job monthly columns
    $html .= '<td colspan="' . $month_count . '" class="chart-cell">';
    $html .= '<div class="inline-chart-container">';
    $html .= '<div class="chart-header">' . $this->t('On-The-Job Progress Chart') . '</div>';
    $html .= '<div id="' . $onjob_chart_id . '" class="inline-chart" style="min-height: 200px; width: 100%;"></div>';
    $html .= '</div>';
    $html .= '</td>';
    
    $html .= '<td colspan="3"></td>'; // Cumulative columns
    $html .= '</tr>';
    
    $html .= '</tbody>';
    $html .= '</table>';
    $html .= '</div>';
    
    return $html;
  }

  /**
   * Get period display for charts.
   */
  protected function getPeriodDisplay($company_uid, $program_nid, $employee_uid, $report_type, $from_date, $to_date): string {
    $db = \Drupal::database();
    
    if ($report_type === 'latest') {
      $latest_session = $db->select('coach_reporting_session', 's')
        ->fields('s', ['submitted'])
        ->condition('company_uid', $company_uid)
        ->condition('program_nid', $program_nid)
        ->condition('employee_uid', $employee_uid)
        ->isNotNull('submitted')
        ->orderBy('submitted', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchField();
      
      if ($latest_session) {
        return date('M Y', $latest_session);
      }
    } elseif ($from_date && $to_date) {
      $from_ts = strtotime($from_date . ' 00:00:00') ?: 0;
      $to_ts = strtotime($to_date . ' 23:59:59') ?: 0;
      if ($from_ts && $to_ts) {
        return date('M Y', $from_ts) . ' - ' . date('M Y', $to_ts);
      }
    }
    
    return date('M Y');
  }

  /**
   * Get pre/post training data for the employee.
   */
  protected function getPrePostTrainingData($company_uid, $program_nid, $employee_uid): array {
    $db = \Drupal::database();
    
    // Check if table exists
    if (!$db->schema()->tableExists('qs_employee_prepost_relation')) {
      \Drupal::logger('coach_reporting_system')->warning('Table qs_employee_prepost_relation does not exist. Please run the database creation script.');
      return ['pre_grade' => 0, 'post_grade' => 0];
    }
    
    try {
      $result = $db->select('qs_employee_prepost_relation', 'r')
        ->fields('r', ['pre', 'post'])
        ->condition('employee_id', $employee_uid)
        ->condition('company_id', $company_uid)
        ->condition('questionnaire_id', $program_nid)
        ->execute()
        ->fetchAssoc();
      
      if ($result) {
        return [
          'pre_grade' => $result['pre'] ?? 0,
          'post_grade' => $result['post'] ?? 0
        ];
      }
      return ['pre_grade' => 0, 'post_grade' => 0];
    } catch (\Exception $e) {
      \Drupal::logger('coach_reporting_system')->error('Error querying qs_employee_prepost_relation: @message', ['@message' => $e->getMessage()]);
      return ['pre_grade' => 0, 'post_grade' => 0];
    }
  }

  /**
   * Get behavioral performance data from sessions.
   */
  protected function getBehavioralPerformanceData($company_uid, $program_nid, $employee_uid, $date_condition): array {
    $db = \Drupal::database();
    
    // Get sessions based on date condition
    $query = $db->select('coach_reporting_session', 's')
      ->fields('s', ['sid', 'submitted'])
      ->condition('company_uid', $company_uid)
      ->condition('program_nid', $program_nid)
      ->condition('employee_uid', $employee_uid)
      ->isNotNull('submitted');
    
    if (!empty($date_condition)) {
      if (isset($date_condition['operator']) && $date_condition['operator'] === 'BETWEEN') {
        $query->condition('submitted', $date_condition['submitted'], 'BETWEEN');
      } else {
        $query->condition('submitted', $date_condition['submitted']);
      }
    }
    
    $sessions = $query->execute()->fetchAllAssoc('sid');
    
    if (empty($sessions)) {
      return ['percentage' => 0, 'data' => []];
    }
    
    // Get questionnaire matrix for normalization
    $program = $this->entityTypeManager()->getStorage('node')->load($program_nid);
    $steps = $this->buildStepsFromField($program, 'field_create_questionnaire');
    $matrix_def = NULL;
    foreach ($steps as $s) {
      if (($s['widget'] ?? '') === 'matrix') {
        $matrix_def = $s['def'];
        break;
      }
    }
    
    if (!$matrix_def || empty($matrix_def['options'])) {
      return ['percentage' => 0, 'data' => []];
    }
    
    // Calculate normalized scores
    $columns = $matrix_def['options'];
    $option_keys = array_keys($columns);
    $option_count = max(1, count($option_keys));
    $den = max(1, $option_count - 1);
    
    $normalize_map = [];
    foreach (array_values($option_keys) as $i => $rawKey) {
      $normalize_map[(string) $rawKey] = 100 - ($i * 100.0 / $den);
    }
    
    // Get answers and calculate average score
    $sid_list = array_keys($sessions);
    $answers = $db->select('coach_reporting_session_answer', 'a')
      ->fields('a', ['sid', 'row_uuid', 'value'])
      ->condition('sid', $sid_list, 'IN')
      ->execute()
      ->fetchAll();
    
    $total_score = 0;
    $score_count = 0;
    
    foreach ($answers as $answer) {
      $raw = (string) $answer->value;
      if ($raw !== '' && array_key_exists($raw, $normalize_map)) {
        $total_score += $normalize_map[$raw];
        $score_count++;
      }
    }
    
    $average_score = $score_count > 0 ? $total_score / $score_count : 0;
    
    return [
      'percentage' => round($average_score, 1),
      'data' => [$average_score]
    ];
  }

  /**
   * Get on-the-job progress data.
   */
  protected function getOnJobProgressData($company_uid, $program_nid, $employee_uid, $date_condition): array {
    $db = \Drupal::database();
    
    // Check if table exists
    if (!$db->schema()->tableExists('qs_emp_lagard_starts')) {
      \Drupal::logger('coach_reporting_system')->warning('Table qs_emp_lagard_starts does not exist. Please run the database creation script.');
      return ['percentage' => 0, 'data' => []];
    }
    
    try {
      // Build query similar to original questionnaireresult.php
      $query = $db->select('qs_emp_lagard_starts', 'q')
        ->fields('q', ['month', 'target_forecasted', 'target_achieved', 'created'])
        ->condition('employee_uid', $employee_uid)
        ->condition('company_uid', $company_uid);
      
      if ($program_nid) {
        $query->condition('questionnaire_id', $program_nid);
      }
      
      // Apply date condition if provided - filter by month field for accuracy
      if (!empty($date_condition)) {
        if (isset($date_condition['operator']) && $date_condition['operator'] === 'BETWEEN') {
          if (isset($date_condition['submitted']) && is_array($date_condition['submitted'])) {
            // Convert timestamps to Y-m format to match month field
            $from_month = date('Y-m', $date_condition['submitted'][0]);
            $to_month = date('Y-m', $date_condition['submitted'][1]);
            $query->condition('month', [$from_month, $to_month], 'BETWEEN');
          }
        }
      }
      
      $results = $query->execute()->fetchAll();
      
      if (empty($results)) {
        return ['percentage' => 0, 'data' => []];
      }
      
      // Calculate performance like original - sum all targets and achievements
      $total_forecasted = 0;
      $total_achieved = 0;
      
      foreach ($results as $result) {
        $total_forecasted += $result->target_forecasted;
        $total_achieved += $result->target_achieved;
      }
      
      // Calculate overall percentage like original
      $overall_percentage = 0;
      if ($total_forecasted > 0) {
        $overall_percentage = ($total_achieved / $total_forecasted) * 100;
      }
      
      // Format percentage to 2 decimal places if needed
      $formatted_percentage = (fmod($overall_percentage, 1) == 0) ? (string)round($overall_percentage) : number_format($overall_percentage, 2, '.', '');
      
      // Debug logging
      \Drupal::logger('coach_reporting_system')->info('OnJob calculation: Employee @emp, Forecasted: @forecast, Achieved: @achieve, Percentage: @pct', [
        '@emp' => $employee_uid,
        '@forecast' => $total_forecasted,
        '@achieve' => $total_achieved,
        '@pct' => $formatted_percentage
      ]);
      
      return [
        'percentage' => (float)$formatted_percentage, // Round to 2 decimal places if needed
        'data' => [(float)$formatted_percentage],
        'total_forecasted' => $total_forecasted,
        'total_achieved' => $total_achieved
      ];
    } catch (\Exception $e) {
      \Drupal::logger('coach_reporting_system')->error('Error querying qs_emp_lagard_starts: @message', ['@message' => $e->getMessage()]);
      return ['percentage' => 0, 'data' => []];
    }
  }

  /**
   * Calculate cumulative results.
   */
  protected function calculateCumulativeResults($prepost_data, $behavioral_data, $onjob_data): array {
    $behavioral_till_date = $behavioral_data['percentage'] ?? 0;
    $onjob_till_date = $onjob_data['percentage'] ?? 0;
    
    // Merge behavioral and on-the-job results (weighted average)
    $merged_results = ($behavioral_till_date + $onjob_till_date) / 2;
    
    return [
      'behavioral_till_date' => $behavioral_till_date,
      'onjob_till_date' => $onjob_till_date,
      'merged_results' => $merged_results // Don't round - use exact value
    ];
  }


  protected function buildOneToOneCoachingContent($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['one-to-one-coaching']],
      'content' => [
        '#markup' => '<div class="alert alert-info"><strong>' . $this->t('Coaching One to One Report') . '</strong><br>'
          . $this->t('This report will show detailed insights from individual coaching sessions and personalized development plans.') . ' '
          . $this->t('Coming soon.') . '</div>',
      ],
    ];
  }

  protected function checkForReportData($company_uid, $program_nid, $employee_uid, $report_type): bool {
    return TRUE;
  }

  /**
   * Download report as Excel file with colors, charts, and separate sheets.
   */
  protected function downloadReportAsExcel($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $report_content, $from_date, $to_date) {
    // Load entities
    $company  = $this->entityTypeManager()->getStorage('user')->load($company_uid);
    $program  = $this->entityTypeManager()->getStorage('node')->load($program_nid);
    $employee = $this->entityTypeManager()->getStorage('user')->load($employee_uid);
    
    if (!$company || !$program || !$employee) {
      $this->messenger()->addError($this->t('Invalid data for export.'));
      return $this->redirect('coach_reporting_system.report');
    }
    
    $employee_name = $employee->label();
    $program_name = $program->label();
    $company_name = $company->label();
    
    // Determine date range display
    $date_range = '';
    if ($report_type === 'latest') {
      $date_range = 'Latest Report - ' . date('d-m-Y');
    } else {
      $from_display = $from_date ? date('d-m-Y', strtotime($from_date)) : '';
      $to_display = $to_date ? date('d-m-Y', strtotime($to_date)) : '';
      $date_range = $from_display . ' - ' . $to_display;
    }
    
    // Build HTML with proper Excel worksheet structure
    $html = '<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <style>
    .green-bg { background-color: #b3e2c7; color: #000000; }
    .yellow-bg { background-color: #FFDD7D; color: #000000; }
    .red-bg { background-color: #F95959; color: #FFFFFF; }
    .green-dark { background-color: #10b981; color: #FFFFFF; font-weight: bold; }
    .yellow-dark { background-color: #f59e0b; color: #FFFFFF; font-weight: bold; }
    .red-dark { background-color: #ef4444; color: #FFFFFF; font-weight: bold; }
    .purple-header { background-color: #40124e; color: #FFFFFF; font-weight: bold; text-align: center; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #000000; padding: 8px; text-align: center; }
    .chart-table { width: 60%; margin: 20px auto; }
    .chart-title { background-color: #40124e; color: #FFFFFF; padding: 10px; text-align: center; font-weight: bold; margin-top: 20px; }
  </style>
</head>
<body>';
    
    // Add Summary section
    $html .= '<h1 style="background-color: #40124e; color: #FFFFFF; padding: 20px; text-align: center;">Coaching Report Summary</h1>';
    $html .= '<table border="1" style="width: 50%; margin: 20px auto;">';
    $html .= '<tr><th style="background-color: #5a1d6e; color: #FFFFFF;">Company</th><td>' . htmlspecialchars($company_name) . '</td></tr>';
    $html .= '<tr><th style="background-color: #5a1d6e; color: #FFFFFF;">Program</th><td>' . htmlspecialchars($program_name) . '</td></tr>';
    $html .= '<tr><th style="background-color: #5a1d6e; color: #FFFFFF;">Employee</th><td>' . htmlspecialchars($employee_name) . '</td></tr>';
    $html .= '<tr><th style="background-color: #5a1d6e; color: #FFFFFF;">Date Range</th><td>' . htmlspecialchars($date_range) . '</td></tr>';
    $html .= '</table>';
    $html .= '<hr style="margin: 40px 0; border: 2px solid #40124e;">';
    
    // Define report types
    $report_types = [
      'per_person' => 'Per Person',
      'competency_analysis' => 'Competency Analysis',
      'on_job_performance' => 'On-The-Job Performance',
      'coaching_impact' => 'Coaching Impact'
    ];
    
    // Generate each report in a separate div (Excel will treat as separate sheets)
    foreach ($report_types as $key => $title) {
      if (!empty($report_content) && !in_array($key, $report_content)) {
        continue;
      }
      
      $html .= '<div style="page-break-after: always;">';
      $html .= '<h2 style="background-color: #40124e; color: #FFFFFF; padding: 15px; text-align: center;">' . htmlspecialchars($title) . '</h2>';
      
      // Get the table HTML for this tab
      switch ($key) {
        case 'per_person':
          $html .= $this->buildPerPersonTableForExport($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date);
          break;
        case 'competency_analysis':
          $html .= $this->buildCompetencyAnalysisTableForExport($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date);
          break;
        case 'on_job_performance':
          $html .= $this->buildOnJobPerformanceTableForExport($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date);
          break;
        case 'coaching_impact':
          $html .= $this->buildCoachingImpactTableForExport($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date);
          break;
      }
      
      $html .= '</div>';
    }
    
    $html .= '</body></html>';
    
    // Create response
    $response = new Response($html);
    $safe_program = preg_replace('/[^a-zA-Z0-9 ]/', '', $program_name);
    $safe_employee = preg_replace('/[^a-zA-Z0-9 ]/', '', $employee_name);
    $filename = $safe_program . '- ' . $safe_employee . ' - ' . $date_range . '.xls';
    $response->headers->set('Content-Type', 'application/vnd.ms-excel');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    $response->headers->set('Pragma', 'public');
    $response->headers->set('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
    
    return $response;
  }

  /**
   * Build Per Person table for Excel export with colors.
   */
  protected function buildPerPersonTableForExport($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date): string {
    // Get employee name
    $employee = $this->entityTypeManager()->getStorage('user')->load($employee_uid);
    $employee_name = $employee ? $employee->label() : 'Employee ' . $employee_uid;
    
    // buildPerPersonTable signature: ($employee_name, $company_uid, $program_nid, $employee_uid, $report_type, $from_date, $to_date)
    $table_html = $this->buildPerPersonTable($employee_name, $company_uid, $program_nid, $employee_uid, $report_type, $from_date, $to_date);
    
    // Convert CSS classes to inline styles for Excel
    $table_html = str_replace('class="score-green"', 'class="green-dark"', $table_html);
    $table_html = str_replace('class="score-yellow"', 'class="yellow-dark"', $table_html);
    $table_html = str_replace('class="score-red"', 'class="red-dark"', $table_html);
    
    return $table_html;
  }

  /**
   * Build Competency Analysis table for Excel export with colors.
   */
  protected function buildCompetencyAnalysisTableForExport($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date): string {
    // buildCompetencyAnalysisTable signature: (int $company_uid, int $program_nid, int $employee_uid)
    $table_html = $this->buildCompetencyAnalysisTable($company_uid, $program_nid, $employee_uid);
    
    // Convert CSS classes to inline styles for Excel
    $table_html = str_replace('class="score-green"', 'class="green-dark"', $table_html);
    $table_html = str_replace('class="score-yellow"', 'class="yellow-dark"', $table_html);
    $table_html = str_replace('class="score-red"', 'class="red-dark"', $table_html);
    
    return $table_html;
  }

  /**
   * Build On-The-Job Performance table for Excel export with colors.
   */
  protected function buildOnJobPerformanceTableForExport($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date): string {
    $table_html = $this->buildOnJobPerformanceTable($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date);
    
    // Convert CSS classes to Excel-compatible inline styles
    $table_html = str_replace('class="performance-stars"', 'class="green-bg"', $table_html);
    $table_html = str_replace('class="performance-core"', 'class="yellow-bg"', $table_html);
    $table_html = str_replace('class="performance-laggards"', 'class="red-bg"', $table_html);
    
    return $table_html;
  }

  /**
   * Build Coaching Impact table for Excel export with colors and chart data.
   */
  protected function buildCoachingImpactTableForExport($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date): string {
    // Get the main table
    $table_html = $this->buildCoachingImpactTable($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date);
    
    // Convert CSS classes to inline styles for Excel
    $table_html = str_replace('class="score-green"', 'class="green-dark"', $table_html);
    $table_html = str_replace('class="score-yellow"', 'class="yellow-dark"', $table_html);
    $table_html = str_replace('class="score-red"', 'class="red-dark"', $table_html);
    
    // Remove chart divs but add chart data tables instead
    $table_html = str_replace('<div class="inline-chart', '<div style="display:none" class="inline-chart', $table_html);
    
    // Add chart data tables for Excel
    $chart_data_html = $this->buildChartDataTablesForExcel($company_uid, $program_nid, $employee_uid, $report_type, $from_date, $to_date);
    
    return $table_html . $chart_data_html;
  }
  
  /**
   * Build chart data as tables for Excel export.
   */
  protected function buildChartDataTablesForExcel($company_uid, $program_nid, $employee_uid, $report_type, $from_date, $to_date): string {
    $html = '<br/><br/>';
    $html .= '<div class="chart-title">Progress Charts Data</div>';
    
    // Determine monthly periods
    $monthly_periods = [];
    $monthly_behavioral_data = [];
    $monthly_onjob_data = [];
    
    if ($report_type === 'latest') {
      $to_ts = time();
      $from_ts = strtotime('-3 months', $to_ts);
      $current_ts = $from_ts;
      
      while ($current_ts <= $to_ts) {
        $month_display = date('M Y', $current_ts);
        $month_start = strtotime(date('Y-m-01 00:00:00', $current_ts));
        $month_end = strtotime(date('Y-m-t 23:59:59', $current_ts));
        
        $monthly_periods[] = $month_display;
        
        $date_cond = ['submitted' => [$month_start, $month_end], 'operator' => 'BETWEEN'];
        $behavioral_data = $this->getBehavioralPerformanceData($company_uid, $program_nid, $employee_uid, $date_cond);
        $onjob_data = $this->getOnJobProgressData($company_uid, $program_nid, $employee_uid, $date_cond);
        
        $monthly_behavioral_data[] = $behavioral_data['percentage'] ?? 0;
        $monthly_onjob_data[] = $onjob_data['percentage'] ?? 0;
        
        $current_ts = strtotime('+1 month', $current_ts);
      }
    }
    
    // Behavioral Progress Chart Data
    $html .= '<h3 style="background-color: #40124e; color: #FFFFFF; padding: 10px; margin-top: 30px; text-align: center;">Behavioral Progress Chart</h3>';
    $html .= '<table border="1" class="chart-table">';
    $html .= '<thead><tr style="background-color: #5a1d6e; color: #FFFFFF; font-weight: bold;"><th>Month</th><th>Behavioral Progress (%)</th><th>Category</th></tr></thead>';
    $html .= '<tbody>';
    
    for ($i = 0; $i < count($monthly_periods); $i++) {
      $value = $monthly_behavioral_data[$i];
      $category = $value >= 100 ? 'Stars' : ($value >= 60 ? 'Core' : 'Laggards');
      $row_class = $value >= 100 ? 'green-bg' : ($value >= 60 ? 'yellow-bg' : 'red-bg');
      
      $html .= '<tr class="' . $row_class . '">';
      $html .= '<td><strong>' . htmlspecialchars($monthly_periods[$i]) . '</strong></td>';
      $html .= '<td><strong>' . number_format($value, 2) . '%</strong></td>';
      $html .= '<td><strong>' . $category . '</strong></td>';
      $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';
    
    // On-The-Job Progress Chart Data
    $html .= '<h3 style="background-color: #40124e; color: #FFFFFF; padding: 10px; margin-top: 30px; text-align: center;">On-The-Job Progress Chart</h3>';
    $html .= '<table border="1" class="chart-table">';
    $html .= '<thead><tr style="background-color: #5a1d6e; color: #FFFFFF; font-weight: bold;"><th>Month</th><th>On-The-Job Progress (%)</th><th>Category</th></tr></thead>';
    $html .= '<tbody>';
    
    for ($i = 0; $i < count($monthly_periods); $i++) {
      $value = $monthly_onjob_data[$i];
      $category = $value >= 100 ? 'Stars' : ($value >= 60 ? 'Core' : 'Laggards');
      $row_class = $value >= 100 ? 'green-bg' : ($value >= 60 ? 'yellow-bg' : 'red-bg');
      
      $html .= '<tr class="' . $row_class . '">';
      $html .= '<td><strong>' . htmlspecialchars($monthly_periods[$i]) . '</strong></td>';
      $html .= '<td><strong>' . number_format($value, 2) . '%</strong></td>';
      $html .= '<td><strong>' . $category . '</strong></td>';
      $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';
    
    return $html;
  }

  /**
   * Checks whether the current user is allowed to view the report for the given company/coach/employee.
   */
  protected function currentUserCanAccessReport(int $company_uid, ?int $coach_uid, int $employee_uid): bool {
    $current_user = \Drupal::currentUser();
    $current_uid = (int) $current_user->id();
    $roles = $current_user->getRoles(TRUE);

    $is_admin = in_array('administrator', $roles, TRUE) || $current_user->hasPermission('administer users');
    if ($is_admin) {
      return TRUE;
    }

    if (in_array('company', $roles, TRUE)) {
      return $company_uid === $current_uid;
    }

    if (in_array('coach', $roles, TRUE)) {
      $allowed = $this->getCompaniesForCoach($current_uid);
      return in_array($company_uid, $allowed, TRUE);
    }

    if (in_array('employee', $roles, TRUE)) {
      if ($employee_uid !== $current_uid) {
        return FALSE;
      }
      $allowed = $this->getCompaniesForEmployee($current_uid);
      return in_array($company_uid, $allowed, TRUE);
    }

    return FALSE;
  }

  protected function profileFieldExists(string $bundle, string $field_name): bool {
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('profile', $bundle);
    return isset($definitions[$field_name]);
  }

  /**
   * Returns company UIDs linked on the coach's active profile.
   */
  protected function getCompaniesForCoach(int $coach_uid): array {
    $coach_account = $this->entityTypeManager()->getStorage('user')->load($coach_uid);
    if (!$coach_account) {
      return [];
    }
    $profile_storage = $this->entityTypeManager()->getStorage('profile');
    $profile = $profile_storage->loadByUser($coach_account, 'coach', TRUE);
    if (is_array($profile)) {
      $profile = reset($profile) ?: NULL;
    }
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
      if (!$profile) {
        return [];
      }
    }
    if (!$this->profileFieldExists('coach', 'field_company')
      || !$profile->hasField('field_company')
      || $profile->get('field_company')->isEmpty()) {
      return [];
    }
    $company_uids = [];
    foreach ($profile->get('field_company')->referencedEntities() as $company_user) {
      $id = (int) $company_user->id();
      if ($id > 0) {
        $company_uids[$id] = $id;
      }
    }
    return array_values($company_uids);
  }

  /**
   * Returns company UIDs linked on the employee's profile(s).
   */
  protected function getCompaniesForEmployee(int $employee_uid): array {
    $profile_storage = $this->entityTypeManager()->getStorage('profile');
    $pids = $profile_storage->getQuery()
      ->condition('type', 'employee')
      ->condition('uid', $employee_uid)
      ->accessCheck(TRUE)
      ->execute();
    if (!$pids) {
      return [];
    }
    $company_uids = [];
    foreach ($profile_storage->loadMultiple($pids) as $profile) {
      if ($this->profileFieldExists('employee', 'field_company')
        && $profile->hasField('field_company')
        && !$profile->get('field_company')->isEmpty()) {
        $company_user = $profile->get('field_company')->entity;
        if ($company_user) {
          $company_uids[(int) $company_user->id()] = (int) $company_user->id();
        }
      }
    }
    return array_values($company_uids);
  }
}
