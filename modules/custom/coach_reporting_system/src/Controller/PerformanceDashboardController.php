<?php

namespace Drupal\coach_reporting_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for displaying Performance Dashboard with program-wise accordions.
 */
class PerformanceDashboardController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a PerformanceDashboardController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Display the Performance Dashboard with program-wise accordions.
   */
  public function view(Request $request) {
    $query_params = $request->query->all();

    // Get filter parameters
    $company_uid = !empty($query_params['company']) ? (int) $query_params['company'] : NULL;
    $coach_uid = !empty($query_params['coach']) && $query_params['coach'] !== 'all' ? (int) $query_params['coach'] : NULL;
    $employee_uid = !empty($query_params['employee']) ? (int) $query_params['employee'] : NULL;
    $report_type = $query_params['report_type'] ?? 'latest';
    $from_date = $query_params['from'] ?? NULL;
    $to_date = $query_params['to'] ?? NULL;

    // Get all companies and coaches for dropdowns
    $companies = $this->getCompanies();
    $coaches = $company_uid ? $this->getCoaches($company_uid) : [];

    // Initialize accordion items as empty
    $accordion_items = [];
    $programs_chart_data = [];
    $show_results = FALSE;

      // Only show results if company and coach are selected
    if ($company_uid && $coach_uid) {
      $show_results = TRUE;
      
      // Get all programs for the company/coach
      $programs = $this->getPrograms($company_uid, $coach_uid, $employee_uid);

      if (!empty($programs)) {
        // Build accordion data for each program
        foreach ($programs as $program_nid => $program_data) {
          $dashboard_data = $this->getDashboardDataForProgram(
            $company_uid,
            $program_nid,
            $coach_uid,
            $employee_uid,
            $report_type,
            $from_date,
            $to_date
          );

          $accordion_items[] = [
            'program_id' => $program_nid,
            'program_name' => $program_data['name'],
            'dashboard_data' => $dashboard_data,
          ];
          
          // Add chart data to drupalSettings for this program
          $programs_chart_data[$program_nid] = $dashboard_data['chart_data'];
        }
      }
    }

    // Load company and coach for header
    $company = $company_uid ? $this->entityTypeManager()->getStorage('user')->load($company_uid) : NULL;
    $coach = $coach_uid ? $this->entityTypeManager()->getStorage('user')->load($coach_uid) : NULL;

    return [
      '#theme' => 'performance_dashboard_accordion',
      '#companies' => $companies,
      '#coaches' => $coaches,
      '#selected_company' => $company_uid,
      '#selected_coach' => $coach_uid,
      '#company_name' => $company ? $company->label() : '',
      '#coach_name' => $coach ? $coach->label() : '',
      '#report_type' => $report_type,
      '#from_date' => $from_date,
      '#to_date' => $to_date,
      '#show_results' => $show_results,
      '#accordion_items' => $accordion_items,
      '#attached' => [
        'library' => [
          'coach_reporting_system/performance_dashboard',
        ],
        'drupalSettings' => [
          'performanceDashboard' => [
            'companyUid' => $company_uid,
            'coachUid' => $coach_uid,
            'reportType' => $report_type,
            'programs' => !empty($programs_chart_data) ? $programs_chart_data : [],
          ],
        ],
      ],
    ];
  }

  /**
   * Get coaches via AJAX.
   */
  public function getCoachesAjax(Request $request) {
    $company_uid = $request->query->get('company_uid');
    
    if (!$company_uid) {
      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'coaches' => [],
      ]);
    }
    
    $coaches = $this->getCoaches((int) $company_uid);
    
    return new \Symfony\Component\HttpFoundation\JsonResponse([
      'coaches' => $coaches,
    ]);
  }

  /**
   * Get program dashboard data via AJAX with date filtering.
   */
  public function getProgramDataAjax(Request $request) {
    $program_nid = $request->query->get('program_nid');
    $company_uid = $request->query->get('company_uid');
    $coach_uid = $request->query->get('coach_uid');
    $employee_uid = $request->query->get('employee_uid');
    $from_date = $request->query->get('from_date');
    $to_date = $request->query->get('to_date');
    
    if (!$program_nid || !$company_uid || !$coach_uid) {
      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'success' => FALSE,
        'message' => 'Missing required parameters',
      ]);
    }
    
    // Determine report type based on date selection
    $report_type = ($from_date && $to_date) ? 'overtime' : 'latest';
    
    // Get dashboard data for this program with date filter
    $dashboard_data = $this->getDashboardDataForProgram(
      (int) $company_uid,
      (int) $program_nid,
      (int) $coach_uid,
      $employee_uid ? (int) $employee_uid : NULL,
      $report_type,
      $from_date,
      $to_date
    );
    
    return new \Symfony\Component\HttpFoundation\JsonResponse([
      'success' => TRUE,
      'data' => $dashboard_data,
    ]);
  }

  /**
   * Get company options (same logic as ReportForm).
   */
  protected function getCompanies() {
    $companies = [];
    
    // Get all active users with 'company' role
    $query = $this->entityTypeManager()->getStorage('user')->getQuery();
    $query->condition('status', 1);
    $query->condition('roles', 'company');
    $query->accessCheck(TRUE);
    $uids = $query->execute();
    
    if (!empty($uids)) {
      $users = $this->entityTypeManager()->getStorage('user')->loadMultiple($uids);
      foreach ($users as $uid => $user) {
        // Use full name if available, otherwise use account name
        $full_name = ($user->hasField('field_full_name') && !$user->get('field_full_name')->isEmpty())
          ? trim((string) $user->get('field_full_name')->value)
          : $user->label();
        
        $email = method_exists($user, 'getEmail') ? $user->getEmail() : '';
        $label = $email ? sprintf('%s (%s)', $full_name, $email) : $full_name;
        
        $companies[$uid] = $label;
      }
    }
    
    asort($companies, SORT_NATURAL | SORT_FLAG_CASE);
    return $companies;
  }

  /**
   * Get coaches for a company (same logic as ReportForm).
   */
  protected function getCoaches($company_uid) {
    $coaches = [];
    
    $profile_storage = $this->entityTypeManager()->getStorage('profile');
    
    // Check if field exists
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('profile', 'coach');
    if (!isset($definitions['field_company'])) {
      \Drupal::logger('coach_reporting_system')->error('Missing field_company on coach profile bundle.');
      return $coaches;
    }
    
    // Get coach profiles linked to this company
    $pids = $profile_storage->getQuery()
      ->condition('type', 'coach')
      ->condition('status', 1)
      ->condition('field_company.target_id', $company_uid)
      ->accessCheck(TRUE)
      ->execute();
    
    if (!$pids) {
      return $coaches;
    }

    $coach_uids = [];
    foreach ($profile_storage->loadMultiple($pids) as $profile) {
      $uid = (int) $profile->getOwnerId();
      if ($uid > 0) {
        $coach_uids[$uid] = $uid;
      }
    }
    
    if (!$coach_uids) {
      return $coaches;
    }

    $coach_users = $this->entityTypeManager()->getStorage('user')->loadMultiple(array_values($coach_uids));
    foreach ($coach_users as $coach) {
      if (!$coach->isActive() || !in_array('coach', $coach->getRoles(), TRUE)) {
        continue;
      }
      
      $full_name = ($coach->hasField('field_full_name') && !$coach->get('field_full_name')->isEmpty())
        ? trim((string) $coach->get('field_full_name')->value)
        : $coach->label();
      
      $email = method_exists($coach, 'getEmail') ? $coach->getEmail() : '';
      $label = $email ? sprintf('%s (%s)', $full_name, $email) : $full_name;
      
      $coaches[$coach->id()] = $label;
    }
    
    asort($coaches, SORT_NATURAL | SORT_FLAG_CASE);
    return $coaches;
  }

  /**
   * Get programs from coach_reporting_session table.
   */
  protected function getPrograms($company_uid, $coach_uid = NULL, $employee_uid = NULL) {
    $programs = [];
    
    // Query coach_reporting_session table to get programs
    $query = $this->database->select('coach_reporting_session', 's');
    $query->fields('s', ['program_nid']);
    $query->condition('company_uid', $company_uid);
    
    // Filter by coach if specified
    if ($coach_uid) {
      $query->condition('coach_uid', $coach_uid);
    }
    
    // Filter by employee if specified
    if ($employee_uid) {
      $query->condition('employee_uid', $employee_uid);
    }
    
    // Only get sessions that have been submitted
    $query->isNotNull('submitted');
    
    // Group by program to get unique programs
    $query->groupBy('program_nid');
    
    $program_nids = $query->execute()->fetchCol();
    
    if (empty($program_nids)) {
      return $programs;
    }
    
    // Load program nodes
    $nodes = $this->entityTypeManager()->getStorage('node')->loadMultiple($program_nids);
    
    foreach ($nodes as $nid => $node) {
      $programs[$nid] = [
        'name' => $node->label(),
        'node' => $node,
      ];
    }

    return $programs;
  }

  /**
   * Get dashboard data for a specific program.
   */
  protected function getDashboardDataForProgram($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date) {
    // Store debug information
    $debug_info = [
      'parameters' => [
        'company_uid' => $company_uid,
        'program_nid' => $program_nid,
        'coach_uid' => $coach_uid,
        'employee_uid' => $employee_uid,
        'report_type' => $report_type,
        'from_date' => $from_date,
        'to_date' => $to_date,
      ],
      'date_range_calculated' => [],
      'queries_executed' => [],
    ];
    
    // Calculate default date range
    if (!$from_date || !$to_date) {
      $from_month = date('Y-m', strtotime('-5 months'));
      $to_month = date('Y-m');
      $debug_info['date_range_calculated'] = [
        'from_month' => $from_month,
        'to_month' => $to_month,
        'note' => 'Using default last 6 months',
      ];
    } else {
      $from_month = date('Y-m', strtotime($from_date));
      $to_month = date('Y-m', strtotime($to_date));
      $debug_info['date_range_calculated'] = [
        'from_month' => $from_month,
        'to_month' => $to_month,
        'note' => 'Using custom date range',
      ];
    }
    
    return [
      'metrics' => $this->getMetrics($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date),
      'chart_data' => $this->getChartData($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date),
      'action_report' => $this->getActionReport($company_uid, $program_nid, $coach_uid, $employee_uid),
      'users_report' => $this->getUsersReport($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date, 0, 10),
      'debug' => $debug_info,
    ];
  }

  /**
   * Get dashboard metrics.
   */
  protected function getMetrics($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date) {
    $db = $this->database;

    // Number of Users Coached
    $users_query = $db->select('coach_reporting_session', 's');
    $users_query->addExpression('COUNT(DISTINCT employee_uid)', 'count');
    $users_query->condition('company_uid', $company_uid);
    $users_query->condition('program_nid', $program_nid);
    $users_query->isNotNull('submitted');
    if ($coach_uid) {
      $users_query->condition('coach_uid', $coach_uid);
    }
    
    if ($employee_uid) {
      $users_query->condition('employee_uid', $employee_uid);
    }
    
    $this->applyDateFilter($users_query, $report_type, $from_date, $to_date);
    $users_coached = (int) $users_query->execute()->fetchField();

    // Coaching Sessions Count
    $sessions_query = $db->select('coach_reporting_session', 's');
    $sessions_query->addExpression('COUNT(*)', 'count');
    $sessions_query->condition('company_uid', $company_uid);
    $sessions_query->condition('program_nid', $program_nid);
    $sessions_query->isNotNull('submitted');
    if ($coach_uid) {
      $sessions_query->condition('coach_uid', $coach_uid);
    }
    
    if ($employee_uid) {
      $sessions_query->condition('employee_uid', $employee_uid);
    }
    
    $this->applyDateFilter($sessions_query, $report_type, $from_date, $to_date);
    $sessions_count = (int) $sessions_query->execute()->fetchField();

    // Behavioral Progress (average score from sessions)
    $behavioral_progress = $this->getAverageScore($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date);

    // On-The-Job Progress - NOW DYNAMIC!
    $on_job_progress = $this->getOnJobProgress($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date);
    
    // Calculate previous period for comparison
    $on_job_previous = $this->getOnJobProgressPrevious($company_uid, $program_nid, $coach_uid, $employee_uid);
    $on_job_change = $this->calculatePercentageChange($on_job_previous, $on_job_progress);

    // ROI - Calculate based on performance improvement
    $roi = $this->calculateROI($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date);

    \Drupal::logger('coach_reporting_system')->notice('Metrics calculated: users=@users, sessions=@sessions, behavioral=@behav%, on_job=@onjob%', [
      '@users' => $users_coached,
      '@sessions' => $sessions_count,
      '@behav' => round($behavioral_progress, 1),
      '@onjob' => round($on_job_progress, 1),
    ]);

    return [
      'users_coached' => [
        'value' => $users_coached,
        'change' => $this->calculateChange($users_coached, $company_uid, $program_nid, 'users'),
      ],
      'coaching_sessions' => [
        'value' => $sessions_count,
        'change' => $this->calculateChange($sessions_count, $company_uid, $program_nid, 'sessions'),
      ],
      'behavioral_progress' => [
        'value' => round($behavioral_progress, 1) . '%',
        'change' => '+8%', // TODO: Calculate from historical data
      ],
      'on_job_progress' => [
        'value' => round($on_job_progress, 1) . '%',
        'change' => $on_job_change, // NOW DYNAMIC!
      ],
      'roi' => [
        'value' => round($roi, 1) . '%',
        'change' => '+3%', // TODO: Calculate from historical data
      ],
    ];
  }

  /**
   * Get chart data for Google Charts.
   */
  protected function getChartData($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date) {
    // SQL queries are logged in each individual chart method using \Drupal::logger()
    return [
      'overview' => $this->getOverviewChartData($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date),
      'competency' => $this->getCompetencyChartData($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date),
      'department' => $this->getDepartmentChartData($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date),
      'sessions' => $this->getSessionsChartData($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date),
    ];
  }

  /**
   * Get overview chart data (coaching results over time) - Last 6 months by default.
   * Shows Month and Year format (e.g., "Jun 2025").
   */
  protected function getOverviewChartData($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date) {
    $db = $this->database;
    $chart_data = [['Month', 'Average Score']];
    
    // Determine date range for logging
    if ($from_date && $to_date) {
      $from_ts = strtotime($from_date);
      $to_ts = strtotime($to_date);
    } else {
      $from_ts = strtotime('-5 months');
      $to_ts = time();
    }
    
    \Drupal::logger('coach_reporting_system')->notice('Overview Chart: company=@company, program=@program, from=@from, to=@to, date_range=@from_date to @to_date', [
      '@company' => $company_uid,
      '@program' => $program_nid,
      '@from' => date('Y-m-d', $from_ts),
      '@to' => date('Y-m-d', $to_ts),
      '@from_date' => $from_date ?? 'default',
      '@to_date' => $to_date ?? 'default',
    ]);
    
    // If date range specified, use it; otherwise use last 6 months
    if ($from_date && $to_date) {
      // Generate months between from_date and to_date
      $months = [];
      $current = strtotime($from_date);
      $end = strtotime($to_date);
      
      while ($current <= $end) {
        $month_key = date('Y-m', $current);
        $month_label = date('M Y', $current); // e.g., "Jun 2025"
        if (!isset($months[$month_key])) {
          $months[] = [
            'key' => $month_key,
            'label' => $month_label,
          ];
        }
        $current = strtotime('+1 month', $current);
      }
    } else {
      // Default: Last 6 months with Month and Year
      $months = [];
      for ($i = 5; $i >= 0; $i--) {
        $month_date = strtotime("-$i months");
        $month_key = date('Y-m', $month_date);
        $month_label = date('M Y', $month_date); // e.g., "Jun 2025", "Jul 2025"
        $months[] = [
          'key' => $month_key,
          'label' => $month_label,
        ];
      }
    }
    
    \Drupal::logger('coach_reporting_system')->notice('Overview Chart: Processing @count months', [
      '@count' => count($months),
    ]);
    
    foreach ($months as $month_info) {
      $month_start = strtotime($month_info['key'] . '-01 00:00:00');
      $month_end = strtotime(date('Y-m-t 23:59:59', $month_start));
      
      // Get sessions for this month
      $session_query = $db->select('coach_reporting_session', 's');
      $session_query->fields('s', ['sid']);
      $session_query->condition('company_uid', $company_uid);
      $session_query->condition('program_nid', $program_nid);
      $session_query->isNotNull('submitted');
      $session_query->condition('submitted', [$month_start, $month_end], 'BETWEEN');
      if ($coach_uid) {
        $session_query->condition('coach_uid', $coach_uid);
      }
      
      if ($employee_uid) {
        $session_query->condition('employee_uid', $employee_uid);
      }
      
      $sids = $session_query->execute()->fetchCol();
      
      \Drupal::logger('coach_reporting_system')->notice('Overview Chart: Month @month found @count sessions', [
        '@month' => $month_info['label'],
        '@count' => count($sids),
      ]);
      
      $average_score = 0;
      if (!empty($sids)) {
        // Get answers and calculate normalized average using questionnaire matrix
        $average_score = $this->calculateNormalizedAverageFromQuestionnaire($sids, $program_nid);
        
        \Drupal::logger('coach_reporting_system')->notice('Overview Chart: Month @month calculated score=@score', [
          '@month' => $month_info['label'],
          '@score' => round($average_score, 1),
        ]);
      }
      
      $chart_data[] = [$month_info['label'], round($average_score, 1)];
    }
    
    \Drupal::logger('coach_reporting_system')->notice('Overview Chart: Final data=@data', [
      '@data' => print_r($chart_data, TRUE),
    ]);
    
    return $chart_data;
  }

  /**
   * Get competency trends chart data - Last 6 months by default or date range.
   * Shows Month and Year format (e.g., "Jun 2025").
   */
  protected function getCompetencyChartData($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date) {
    $db = $this->database;
    $chart_data = [['Month', 'Score']];
    
    \Drupal::logger('coach_reporting_system')->notice('Competency Chart: Generating for program=@program', [
      '@program' => $program_nid,
    ]);
    
    // If date range specified, use it; otherwise use last 6 months
    if ($from_date && $to_date) {
      // Generate months between from_date and to_date
      $months = [];
      $current = strtotime($from_date);
      $end = strtotime($to_date);
      
      while ($current <= $end) {
        $month_key = date('Y-m', $current);
        $month_label = date('M Y', $current); // e.g., "Jun 2025"
        if (!isset($months[$month_key])) {
          $months[] = [
            'key' => $month_key,
            'label' => $month_label,
          ];
        }
        $current = strtotime('+1 month', $current);
      }
    } else {
      // Default: Last 6 months with Month and Year
      $months = [];
      for ($i = 5; $i >= 0; $i--) {
        $month_date = strtotime("-$i months");
        $month_key = date('Y-m', $month_date);
        $month_label = date('M Y', $month_date); // e.g., "Jun 2025", "Jul 2025"
        $months[] = [
          'key' => $month_key,
          'label' => $month_label,
        ];
      }
    }
    
    foreach ($months as $month_info) {
      $month_start = strtotime($month_info['key'] . '-01 00:00:00');
      $month_end = strtotime(date('Y-m-t 23:59:59', $month_start));
      
      // Get sessions for this month
      $session_query = $db->select('coach_reporting_session', 's');
      $session_query->fields('s', ['sid']);
      $session_query->condition('company_uid', $company_uid);
      $session_query->condition('program_nid', $program_nid);
      $session_query->isNotNull('submitted');
      $session_query->condition('submitted', [$month_start, $month_end], 'BETWEEN');
      if ($coach_uid) {
        $session_query->condition('coach_uid', $coach_uid);
      }
      
      if ($employee_uid) {
        $session_query->condition('employee_uid', $employee_uid);
      }
      
      $sids = $session_query->execute()->fetchCol();
      
      $average_score = 0;
      if (!empty($sids)) {
        // Use same calculation method as Overview chart
        $average_score = $this->calculateNormalizedAverageFromQuestionnaire($sids, $program_nid);
      }
      
      $chart_data[] = [$month_info['label'], round($average_score, 1)];
    }
    
    return $chart_data;
  }

  /**
   * Get Stars, Core, and Laggards chart data from on-the-job performance.
   * Now supports date filtering - Last 6 months by default.
   */
  protected function getDepartmentChartData($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date) {
    $db = $this->database;
    $company_field = $this->resolveOnJobField('company_uid', 'company_id');
    $employee_field = $this->resolveOnJobField('employee_uid', 'employee_id');
    $coach_field = $this->resolveOnJobField('coach_uid', 'coach_id');
    
    // Determine date range
    if ($from_date && $to_date) {
      $from_month = date('Y-m', strtotime($from_date));
      $to_month = date('Y-m', strtotime($to_date));
    } else {
      // Default: Last 6 months
      $from_month = date('Y-m', strtotime('-5 months'));
      $to_month = date('Y-m');
    }
    
    // Log the query parameters for debugging
    \Drupal::logger('coach_reporting_system')->notice('🔍 Stars/Core/Laggards Query Parameters: company_uid=@company, program_nid=@program, employee_uid=@employee, from_month=@from, to_month=@to', [
      '@company' => $company_uid,
      '@program' => $program_nid,
      '@employee' => $employee_uid ?? 'all',
      '@from' => $from_month,
      '@to' => $to_month,
    ]);
    
    // Get all employees with on-the-job performance data
    $query = $db->select('qs_emp_lagard_starts', 'q');
    if ($employee_field) {
      $query->addField('q', $employee_field, 'employee_uid');
    }
    $query->addExpression('AVG(target_achieved / NULLIF(target_forecasted, 0) * 100)', 'avg_performance');
    if ($company_field) {
      $query->condition($company_field, $company_uid);
    }
    $query->condition('questionnaire_id', $program_nid);
    if ($coach_uid && $coach_field) {
      $query->condition($coach_field, $coach_uid);
    }
    
    // Apply date filter based on month-like field.
    $this->applyOnJobMonthRange($query, $from_month, $to_month);
    
    if ($employee_uid && $employee_field) {
      $query->condition($employee_field, $employee_uid);
    }
    
    if ($employee_field) {
      $query->groupBy($employee_field);
    }
    
    // Log the actual SQL query
    $sql = (string) $query;
    \Drupal::logger('coach_reporting_system')->notice('🔍 Stars/Core/Laggards SQL Query: @sql', [
      '@sql' => $sql,
    ]);
    
    $results = $query->execute()->fetchAll();
    
    // Log the raw results count
    \Drupal::logger('coach_reporting_system')->notice('🔍 Stars/Core/Laggards Query returned @count employees', [
      '@count' => count($results),
    ]);
    
    // Categorize employees as Stars, Core, Laggards
    $stars_count = 0;
    $core_count = 0;
    $laggards_count = 0;
    
    $stars_total = 0;
    $core_total = 0;
    $laggards_total = 0;
    
    foreach ($results as $row) {
      $performance = (float) $row->avg_performance;
      
      if ($performance >= 100) {
        $stars_count++;
        $stars_total += $performance;
      } elseif ($performance >= 60) {
        $core_count++;
        $core_total += $performance;
      } else {
        $laggards_count++;
        $laggards_total += $performance;
      }
    }
    
    // Calculate average performance for each category
    $stars_avg = $stars_count > 0 ? $stars_total / $stars_count : 0;
    $core_avg = $core_count > 0 ? $core_total / $core_count : 0;
    $laggards_avg = $laggards_count > 0 ? $laggards_total / $laggards_count : 0;
    
    // Build chart data - return empty if no data found
    if ($stars_count === 0 && $core_count === 0 && $laggards_count === 0) {
      // No data found - return empty array
      $chart_data = [];
      \Drupal::logger('coach_reporting_system')->info('Stars/Core/Laggards: No data found for company=@company, program=@program, date range=@from to @to', [
        '@company' => $company_uid,
        '@program' => $program_nid,
        '@from' => $from_month,
        '@to' => $to_month,
      ]);
    } else {
      // Return actual data - showing average performance percentage for each category
      $chart_data = [
        ['Category', 'Performance %']
      ];
      
      // Only add categories that have employees
      if ($stars_count > 0) {
        $chart_data[] = ['Stars', round($stars_avg, 1)];
      }
      if ($core_count > 0) {
        $chart_data[] = ['Core', round($core_avg, 1)];
      }
      if ($laggards_count > 0) {
        $chart_data[] = ['Laggards', round($laggards_avg, 1)];
      }
      
      \Drupal::logger('coach_reporting_system')->info('Stars/Core/Laggards chart: Stars=@stars employees (@savg% avg), Core=@core employees (@cavg% avg), Laggards=@laggards employees (@lavg% avg)', [
        '@stars' => $stars_count,
        '@savg' => round($stars_avg, 1),
        '@core' => $core_count,
        '@cavg' => round($core_avg, 1),
        '@laggards' => $laggards_count,
        '@lavg' => round($laggards_avg, 1),
      ]);
    }
    
    return $chart_data;
  }

  /**
   * Get sessions chart data - Coaching sessions by month.
   * Last 6 months by default, or custom date range.
   */
  protected function getSessionsChartData($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date) {
    $db = $this->database;
    $chart_data = [['Period', 'Sessions']];
    
    // If date range specified, use it; otherwise use last 6 months
    if ($from_date && $to_date) {
      // Generate months between from_date and to_date
      $months = [];
      $current = strtotime($from_date);
      $end = strtotime($to_date);
      
      while ($current <= $end) {
        $month_key = date('Y-m', $current);
        $month_label = date('M Y', $current);
        if (!isset($months[$month_key])) {
          $months[] = [
            'key' => $month_key,
            'label' => $month_label,
          ];
        }
        $current = strtotime('+1 month', $current);
      }
    } else {
      // Default: Last 6 months
      $months = [];
      for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $month_label = date('M Y', strtotime($month . '-01'));
        $months[] = [
          'key' => $month,
          'label' => $month_label,
        ];
      }
    }
    
    // Get session count for each month
    foreach ($months as $month_info) {
      $month_start = strtotime($month_info['key'] . '-01 00:00:00');
      $month_end = strtotime(date('Y-m-t 23:59:59', $month_start));
      
      $query = $db->select('coach_reporting_session', 's');
      $query->addExpression('COUNT(*)', 'count');
      $query->condition('company_uid', $company_uid);
      $query->condition('program_nid', $program_nid);
      $query->isNotNull('submitted');
      $query->condition('submitted', [$month_start, $month_end], 'BETWEEN');
      if ($coach_uid) {
        $query->condition('coach_uid', $coach_uid);
      }
      
      if ($employee_uid) {
        $query->condition('employee_uid', $employee_uid);
      }
      
      $count = (int) $query->execute()->fetchField();
      $chart_data[] = [$month_info['label'], $count];
    }
    
    return $chart_data;
  }

  /**
   * Calculate normalized average score from session IDs using questionnaire matrix options.
   * This matches the logic used in ReportResultController for consistency.
   */
  protected function calculateNormalizedAverageFromQuestionnaire($sids, $program_nid) {
    if (empty($sids)) {
      return 0;
    }
    
    $db = $this->database;
    
    // Load the questionnaire/program to get matrix options
    $program = $this->entityTypeManager()->getStorage('node')->load($program_nid);
    if (!$program || !$program->hasField('field_create_questionnaire')) {
      // Fallback to simple calculation
      return $this->calculateNormalizedAverage($sids);
    }
    
    // Build normalize map from questionnaire matrix
    $normalize_map = $this->buildNormalizeMapFromProgram($program);
    
    // Get all answers for these sessions
    $answers_query = $db->select('coach_reporting_session_answer', 'a');
    $answers_query->fields('a', ['value']);
    $answers_query->condition('sid', $sids, 'IN');
    $answers = $answers_query->execute()->fetchAll();
    
    if (empty($answers)) {
      \Drupal::logger('coach_reporting_system')->warning('No answers found for sessions: @sids', [
        '@sids' => implode(',', $sids),
      ]);
      return 0;
    }
    
    // Calculate normalized scores
    $total = 0;
    $count = 0;
    
    foreach ($answers as $answer) {
      $raw_value = (string) $answer->value;
      $normalized = NULL;
      
      // Try to find in normalize map
      if ($raw_value !== '' && array_key_exists($raw_value, $normalize_map)) {
        $normalized = $normalize_map[$raw_value];
      } else {
        // Try integer match
        $int_key = (string) ((int) $raw_value);
        if ($raw_value !== '' && array_key_exists($int_key, $normalize_map)) {
          $normalized = $normalize_map[$int_key];
        } elseif (is_numeric($raw_value)) {
          // If it's already a percentage, use it directly (clamped 0-100)
          $normalized = max(0.0, min(100.0, (float) $raw_value));
        }
      }
      
      if ($normalized !== NULL) {
        $total += $normalized;
        $count++;
      }
    }
    
    $average = $count > 0 ? $total / $count : 0;
    
    \Drupal::logger('coach_reporting_system')->notice('Calculated average: @avg from @count answers', [
      '@avg' => round($average, 2),
      '@count' => $count,
    ]);
    
    return $average;
  }
  
  /**
   * Build normalize map from program's questionnaire matrix.
   */
  protected function buildNormalizeMapFromProgram($program) {
    $normalize_map = [];
    
    if (!$program->hasField('field_create_questionnaire')) {
      return $normalize_map;
    }
    
    $questionnaire_data = $program->get('field_create_questionnaire')->getValue();
    if (empty($questionnaire_data)) {
      return $normalize_map;
    }
    
    // Parse questionnaire JSON to find matrix options
    foreach ($questionnaire_data as $item) {
      if (empty($item['value'])) {
        continue;
      }
      
      $decoded = json_decode($item['value'], TRUE);
      if (!$decoded || empty($decoded['widget']) || $decoded['widget'] !== 'matrix') {
        continue;
      }
      
      // Found matrix widget - get options
      if (!empty($decoded['def']['options']) && is_array($decoded['def']['options'])) {
        $options = $decoded['def']['options'];
        $option_keys = array_keys($options);
        $option_count = count($option_keys);
        
        if ($option_count > 0) {
          $denominator = max(1, $option_count - 1);
          
          // Build normalize map: option_key => percentage
          foreach (array_values($option_keys) as $index => $key) {
            // First option = 100%, last option = 0%
            $normalize_map[(string) $key] = 100 - ($index * 100.0 / $denominator);
          }
          
          \Drupal::logger('coach_reporting_system')->notice('Built normalize map: @map', [
            '@map' => print_r($normalize_map, TRUE),
          ]);
          
          break; // Use first matrix found
        }
      }
    }
    
    // If no matrix found, use default 0-4 scale
    if (empty($normalize_map)) {
      \Drupal::logger('coach_reporting_system')->warning('No matrix options found, using default 0-4 scale');
      $normalize_map = [
        '0' => 100,
        '1' => 75,
        '2' => 50,
        '3' => 25,
        '4' => 0,
      ];
    }
    
    return $normalize_map;
  }
  
  /**
   * Calculate normalized average score from session IDs (0-100 scale).
   * Simple fallback method if questionnaire matrix not available.
   */
  protected function calculateNormalizedAverage($sids) {
    if (empty($sids)) {
      return 0;
    }
    
    $db = $this->database;
    
    // Get all answers for these sessions
    $answers_query = $db->select('coach_reporting_session_answer', 'a');
    $answers_query->fields('a', ['value']);
    $answers_query->condition('sid', $sids, 'IN');
    $answers = $answers_query->execute()->fetchCol();
    
    if (empty($answers)) {
      return 0;
    }
    
    // Calculate normalized score
    // Assuming matrix options are 0-4 where:
    // 0 = Best (100%), 1 = Good (75%), 2 = Average (50%), 3 = Poor (25%), 4 = Worst (0%)
    $total = 0;
    $count = 0;
    
    foreach ($answers as $value) {
      if (is_numeric($value)) {
        // Normalize to 0-100 scale (reverse: lower number = higher score)
        $normalized = 100 - ($value * 25);
        $total += $normalized;
        $count++;
      }
    }
    
    return $count > 0 ? $total / $count : 0;
  }

  /**
   * Get action report data.
   */
  protected function getActionReport($company_uid, $program_nid, $coach_uid, $employee_uid) {
    // This would query competencies and their progress
    // For now, return sample structure
    return [
      [
        'competency' => 'Improve Communication Skills',
        'status' => 'In Progress',
        'due_date' => date('Y-m-d', strtotime('+30 days')),
        'progress' => 75,
      ],
      [
        'competency' => 'Enhance Leadership Abilities',
        'status' => 'Completed',
        'due_date' => date('Y-m-d', strtotime('-10 days')),
        'progress' => 100,
      ],
    ];
  }

  /**
   * Get users report data with pagination.
   */
  protected function getUsersReport($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date, $page = 0, $items_per_page = 10) {
    $db = $this->database;
    
    // If specific employee, get their data
    if ($employee_uid) {
      $employee_uids = [$employee_uid];
      $total_count = 1;
    } else {
      // Get total count for pagination
      $count_query = $db->select('coach_reporting_session', 's');
      $count_query->addExpression('COUNT(DISTINCT employee_uid)', 'count');
      $count_query->condition('company_uid', $company_uid);
      $count_query->condition('program_nid', $program_nid);
      $count_query->isNotNull('submitted');
      if ($coach_uid) {
        $count_query->condition('coach_uid', $coach_uid);
      }
      $this->applyDateFilter($count_query, $report_type, $from_date, $to_date);
      $total_count = (int) $count_query->execute()->fetchField();
      
      // Get all employees for this program/company with pagination
      $query = $db->select('coach_reporting_session', 's');
      $query->fields('s', ['employee_uid']);
      $query->condition('company_uid', $company_uid);
      $query->condition('program_nid', $program_nid);
      $query->isNotNull('submitted');
      if ($coach_uid) {
        $query->condition('coach_uid', $coach_uid);
      }
      $this->applyDateFilter($query, $report_type, $from_date, $to_date);
      $query->groupBy('employee_uid');
      $query->range($page * $items_per_page, $items_per_page);
      
      $employee_uids = $query->execute()->fetchCol();
    }
    
    $users_data = [];
    $counter = ($page * $items_per_page) + 1;
    
    foreach ($employee_uids as $uid) {
      $employee = $this->entityTypeManager()->getStorage('user')->load($uid);
      if (!$employee) {
        continue;
      }
      
      // Get latest and previous performance
      $latest = $this->getAverageScore($company_uid, $program_nid, $coach_uid, $uid, 'latest', NULL, NULL);
      $previous = $this->getAverageScore($company_uid, $program_nid, $coach_uid, $uid, 'previous', NULL, NULL);
      
      // Get session dates
      $sessions = $db->select('coach_reporting_session', 's')
        ->fields('s', ['submitted'])
        ->condition('company_uid', $company_uid)
        ->condition('program_nid', $program_nid)
        ->condition('employee_uid', $uid)
        ->isNotNull('submitted')
        ->orderBy('submitted', 'DESC')
        ->range(0, 2)
        ;
      if ($coach_uid) {
        $sessions->condition('coach_uid', $coach_uid);
      }
      $sessions = $sessions->execute()->fetchCol();
      
      $last_session = !empty($sessions[0]) ? date('Y-m-d', $sessions[0]) : 'N/A';
      $first_session = !empty($sessions) ? date('Y-m-d', end($sessions)) : 'N/A';
      
      // Get coach name
      $coach_name = 'Unassigned';
      $profiles = $this->entityTypeManager()
        ->getStorage('profile')
        ->loadByProperties([
          'uid' => $uid,
          'type' => 'employee',
        ]);
      
      if (!empty($profiles)) {
        $profile = reset($profiles);
        if ($profile->hasField('field_coach') && !$profile->get('field_coach')->isEmpty()) {
          $coach_ids = $profile->get('field_coach')->getValue();
          if (!empty($coach_ids[0]['target_id'])) {
            $coach = $this->entityTypeManager()->getStorage('user')->load($coach_ids[0]['target_id']);
            if ($coach) {
              $coach_name = $coach->label();
            }
          }
        }
      }
      
      $comparison = $latest - $previous;
      $comparison_str = $comparison > 0 ? '+' . round($comparison, 1) : round($comparison, 1);
      
      $users_data[] = [
        'number' => $counter++,
        'name' => $employee->label(),
        'comparison' => $comparison_str,
        'coach' => $coach_name,
        'latest_performance' => round($latest, 1),
        'previous_performance' => round($previous, 1),
        'next_session' => $last_session, // TODO: Get actual next session
        'last_session' => $last_session,
        'first_session' => $first_session,
      ];
    }
    
    // Calculate pagination info
    $total_pages = $total_count > 0 ? ceil($total_count / $items_per_page) : 1;
    
    return [
      'data' => $users_data,
      'pagination' => [
        'current_page' => $page,
        'total_pages' => $total_pages,
        'total_items' => $total_count,
        'items_per_page' => $items_per_page,
      ],
    ];
  }

  /**
   * Helper: Apply date filter to query.
   */
  protected function applyDateFilter(&$query, $report_type, $from_date, $to_date) {
    if ($report_type === 'latest') {
      // Last 3 months
      $to_ts = time();
      $from_ts = strtotime('-3 months', $to_ts);
      $query->condition('submitted', [$from_ts, $to_ts], 'BETWEEN');
    } elseif ($report_type === 'overtime' && $from_date && $to_date) {
      $from_ts = strtotime($from_date . ' 00:00:00');
      $to_ts = strtotime($to_date . ' 23:59:59');
      if ($from_ts && $to_ts) {
        $query->condition('submitted', [$from_ts, $to_ts], 'BETWEEN');
      }
    }
  }

  /**
   * Helper: Get average score for sessions.
   */
  protected function getAverageScore($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date) {
    $db = $this->database;
    
    // Get sessions
    $query = $db->select('coach_reporting_session', 's');
    $query->fields('s', ['sid']);
    $query->condition('company_uid', $company_uid);
    $query->condition('program_nid', $program_nid);
    if ($coach_uid) {
      $query->condition('coach_uid', $coach_uid);
    }
    
    if ($employee_uid) {
      $query->condition('employee_uid', $employee_uid);
    }
    
    $query->isNotNull('submitted');
    
    if ($report_type === 'previous') {
      // Get sessions from 6-3 months ago
      $to_ts = strtotime('-3 months');
      $from_ts = strtotime('-6 months');
      $query->condition('submitted', [$from_ts, $to_ts], 'BETWEEN');
    } else {
      $this->applyDateFilter($query, $report_type, $from_date, $to_date);
    }
    
    $sids = $query->execute()->fetchCol();
    
    if (empty($sids)) {
      return 0;
    }
    
    // Get answers and calculate average
    $answers_query = $db->select('coach_reporting_session_answer', 'a');
    $answers_query->fields('a', ['value']);
    $answers_query->condition('sid', $sids, 'IN');
    $answers = $answers_query->execute()->fetchCol();
    
    if (empty($answers)) {
      return 0;
    }
    
    // Calculate normalized score (assuming 0-4 scale mapped to 0-100)
    $total = 0;
    $count = 0;
    
    foreach ($answers as $value) {
      if (is_numeric($value)) {
        // Normalize to 0-100 scale
        $normalized = 100 - ($value * 25); // 0->100, 1->75, 2->50, 3->25, 4->0
        $total += $normalized;
        $count++;
      }
    }
    
    return $count > 0 ? $total / $count : 0;
  }


  /**
   * Helper: Get on-the-job progress percentage.
   * Now supports last 6 months default and custom date ranges.
   */
  protected function getOnJobProgress($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date) {
    $db = $this->database;
    $company_field = $this->resolveOnJobField('company_uid', 'company_id');
    $employee_field = $this->resolveOnJobField('employee_uid', 'employee_id');
    $coach_field = $this->resolveOnJobField('coach_uid', 'coach_id');
    
    \Drupal::logger('coach_reporting_system')->notice('On-Job Progress: Calculating for company=@company, program=@program', [
      '@company' => $company_uid,
      '@program' => $program_nid,
    ]);
    
    $query = $db->select('qs_emp_lagard_starts', 'q');
    $query->addExpression('AVG(target_achieved / NULLIF(target_forecasted, 0) * 100)', 'avg_progress');
    $query->addExpression('COUNT(*)', 'record_count');
    if ($company_field) {
      $query->condition($company_field, $company_uid);
    }
    $query->condition('questionnaire_id', $program_nid);
    if ($coach_uid && $coach_field) {
      $query->condition($coach_field, $coach_uid);
    }
    
    if ($employee_uid && $employee_field) {
      $query->condition($employee_field, $employee_uid);
    }
    
    // Determine date range for month filter
    if ($from_date && $to_date) {
      // Custom date range
      $from_month = date('Y-m', strtotime($from_date));
      $to_month = date('Y-m', strtotime($to_date));
      
      \Drupal::logger('coach_reporting_system')->notice('On-Job Progress: Using custom date range @from to @to', [
        '@from' => $from_month,
        '@to' => $to_month,
      ]);
    } else {
      // Default: Last 6 months
      $to_month = date('Y-m');
      $from_month = date('Y-m', strtotime('-5 months')); // 6 months total (current + 5 previous)
      
      \Drupal::logger('coach_reporting_system')->notice('On-Job Progress: Using default last 6 months @from to @to', [
        '@from' => $from_month,
        '@to' => $to_month,
      ]);
    }
    
    // Apply month filter
    $this->applyOnJobMonthRange($query, $from_month, $to_month);
    
    $result = $query->execute()->fetch();
    
    $avg_progress = $result && $result->avg_progress ? (float) $result->avg_progress : 0;
    $record_count = $result && $result->record_count ? (int) $result->record_count : 0;
    
    \Drupal::logger('coach_reporting_system')->notice('On-Job Progress: Found @count records, average=@avg%', [
      '@count' => $record_count,
      '@avg' => round($avg_progress, 2),
    ]);
    
    return $avg_progress;
  }

  /**
   * Helper: Get on-the-job progress for previous period (for comparison).
   */
  protected function getOnJobProgressPrevious($company_uid, $program_nid, $coach_uid, $employee_uid) {
    $db = $this->database;
    $company_field = $this->resolveOnJobField('company_uid', 'company_id');
    $employee_field = $this->resolveOnJobField('employee_uid', 'employee_id');
    $coach_field = $this->resolveOnJobField('coach_uid', 'coach_id');
    
    // Get data from 12-6 months ago (previous 6-month period)
    $to_month = date('Y-m', strtotime('-6 months'));
    $from_month = date('Y-m', strtotime('-11 months'));
    
    $query = $db->select('qs_emp_lagard_starts', 'q');
    $query->addExpression('AVG(target_achieved / NULLIF(target_forecasted, 0) * 100)', 'avg_progress');
    if ($company_field) {
      $query->condition($company_field, $company_uid);
    }
    $query->condition('questionnaire_id', $program_nid);
    $this->applyOnJobMonthRange($query, $from_month, $to_month);
    if ($coach_uid && $coach_field) {
      $query->condition($coach_field, $coach_uid);
    }
    
    if ($employee_uid && $employee_field) {
      $query->condition($employee_field, $employee_uid);
    }
    
    $result = $query->execute()->fetchField();
    
    return $result ? (float) $result : 0;
  }
  
  /**
   * Helper: Calculate percentage change between two values.
   */
  protected function calculatePercentageChange($old_value, $new_value) {
    if ($old_value == 0) {
      return $new_value > 0 ? '+100%' : '0%';
    }
    
    $change = (($new_value - $old_value) / $old_value) * 100;
    $sign = $change >= 0 ? '+' : '';
    
    return $sign . round($change, 1) . '%';
  }

  /**
   * Helper: Calculate ROI.
   */
  protected function calculateROI($company_uid, $program_nid, $coach_uid, $employee_uid, $report_type, $from_date, $to_date) {
    // Simple ROI calculation based on performance improvement
    $latest = $this->getAverageScore($company_uid, $program_nid, $coach_uid, $employee_uid, 'latest', NULL, NULL);
    $previous = $this->getAverageScore($company_uid, $program_nid, $coach_uid, $employee_uid, 'previous', NULL, NULL);
    
    if ($previous > 0) {
      return (($latest - $previous) / $previous) * 100;
    }
    
    return 15; // Default ROI
  }

  /**
   * Helper: Calculate percentage change.
   */
  protected function calculateChange($current_value, $company_uid, $program_nid, $metric_type) {
    // Get previous period value and calculate change
    // For now, return placeholder
    return '+10%';
  }

  /**
   * Resolve a field name in qs_emp_lagard_starts using primary/fallback names.
   */
  protected function resolveOnJobField(string $primary, ?string $fallback = NULL): ?string {
    $schema = $this->database->schema();
    if ($schema->fieldExists('qs_emp_lagard_starts', $primary)) {
      return $primary;
    }
    if ($fallback && $schema->fieldExists('qs_emp_lagard_starts', $fallback)) {
      return $fallback;
    }
    return NULL;
  }

  /**
   * Apply month range filter for qs_emp_lagard_starts.
   */
  protected function applyOnJobMonthRange($query, string $from_month, string $to_month): void {
    $month_field = $this->resolveOnJobField('month');
    if ($month_field) {
      $query->condition($month_field, [$from_month, $to_month], 'BETWEEN');
      return;
    }
    // Fallback: derive YYYY-MM from created column.
    if ($this->resolveOnJobField('created')) {
      $query->where('LEFT(q.created, 7) BETWEEN :from_month AND :to_month', [
        ':from_month' => $from_month,
        ':to_month' => $to_month,
      ]);
    }
  }

}

