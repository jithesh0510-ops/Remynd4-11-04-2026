<?php

namespace Drupal\coach_reporting_system\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Performance Dashboard' Block.
 *
 * @Block(
 *   id = "performance_dashboard_block",
 *   admin_label = @Translation("Performance Dashboard"),
 *   category = @Translation("Coach Reporting System"),
 * )
 */
class PerformanceDashboardBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new PerformanceDashboardBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Get dashboard data
    $metrics = $this->getMetrics();
    $chart_data = $this->getChartData();
    $action_report = $this->getActionReport();
    $users_report = $this->getUsersReport();

    return [
      '#theme' => 'performance_dashboard',
      '#metrics' => $metrics,
      '#chart_data' => $chart_data,
      '#action_report' => $action_report,
      '#users_report' => $users_report,
      '#attached' => [
        'library' => [
          'coach_reporting_system/performance_dashboard',
        ],
      ],
    ];
  }

  /**
   * Get dashboard metrics.
   */
  protected function getMetrics() {
    // TODO: Replace with actual database queries
    return [
      'users_coached' => [
        'value' => 120,
        'change' => '+10%',
      ],
      'coaching_sessions' => [
        'value' => 350,
        'change' => '+5%',
      ],
      'behavioral_progress' => [
        'value' => '85%',
        'change' => '+8%',
      ],
      'on_job_progress' => [
        'value' => '78%',
        'change' => '+7%',
      ],
      'roi' => [
        'value' => '15%',
        'change' => '+3%',
      ],
    ];
  }

  /**
   * Get chart data for Google Charts.
   */
  protected function getChartData() {
    // TODO: Replace with actual database queries
    return [
      'overview' => [
        ['Month', 'Average Score'],
        ['Jan', 70],
        ['Feb', 75],
        ['Mar', 78],
        ['Apr', 80],
        ['May', 82],
        ['Jun', 85],
      ],
      'competency' => [
        ['Month', 'Score'],
        ['Jan', 60],
        ['Feb', 62],
        ['Mar', 65],
        ['Apr', 67],
        ['May', 70],
        ['Jun', 72],
        ['Jul', 73],
        ['Aug', 74],
        ['Sep', 75],
        ['Oct', 76],
        ['Nov', 77],
        ['Dec', 78],
      ],
      'department' => [
        ['Department', 'Score'],
        ['Engineering', 75],
        ['Sales', 30],
        ['Marketing', 40],
        ['HR', 65],
        ['Finance', 25],
      ],
      'sessions' => [
        ['Age Group', 'Sessions'],
        ['20-30', 80],
        ['31-40', 60],
        ['41-50', 50],
        ['51-60', 55],
        ['61+', 45],
      ],
    ];
  }

  /**
   * Get action report data.
   */
  protected function getActionReport() {
    // TODO: Replace with actual database queries
    return [
      [
        'competency' => 'Improve Communication Skills',
        'status' => 'In Progress',
        'due_date' => '2024-08-15',
        'progress' => 75,
      ],
      [
        'competency' => 'Enhance Leadership Abilities',
        'status' => 'Completed',
        'due_date' => '2024-07-20',
        'progress' => 100,
      ],
      [
        'competency' => 'Develop Strategic Thinking',
        'status' => 'In Progress',
        'due_date' => '2024-09-01',
        'progress' => 50,
      ],
      [
        'competency' => 'Master Time Management',
        'status' => 'Not Started',
        'due_date' => '2024-10-10',
        'progress' => 0,
      ],
      [
        'competency' => 'Build Team Collaboration',
        'status' => 'In Progress',
        'due_date' => '2024-11-05',
        'progress' => 60,
      ],
    ];
  }

  /**
   * Get users report data.
   */
  protected function getUsersReport() {
    // TODO: Replace with actual database queries
    return [
      [
        'number' => 1,
        'name' => 'Sophia Carter',
        'comparison' => '+4%',
        'coach' => 'Mcgillis',
        'latest_performance' => 80,
        'previous_performance' => 80,
        'next_session' => '2024-07-25',
        'last_session' => '2024-07-25',
        'first_session' => '2024-07-25',
      ],
      [
        'number' => 3,
        'name' => 'Caleb Bennett',
        'comparison' => '-13',
        'coach' => 'Mcgillis',
        'latest_performance' => 60,
        'previous_performance' => 80,
        'next_session' => '2024-07-25',
        'last_session' => '2024-07-25',
        'first_session' => '2024-07-25',
      ],
      [
        'number' => 4,
        'name' => 'Isabella Reed',
        'comparison' => '+24',
        'coach' => 'Shaun',
        'latest_performance' => 75,
        'previous_performance' => 80,
        'next_session' => '2024-07-25',
        'last_session' => '2024-07-25',
        'first_session' => '2024-07-25',
      ],
      [
        'number' => 6,
        'name' => 'Owen Harper',
        'comparison' => '-16',
        'coach' => 'Shaun',
        'latest_performance' => 90,
        'previous_performance' => 80,
        'next_session' => '2024-07-25',
        'last_session' => '2024-07-25',
        'first_session' => '2024-07-25',
      ],
      [
        'number' => 2,
        'name' => 'Mia Foster',
        'comparison' => '2%',
        'coach' => 'Mr. Isabella',
        'latest_performance' => 50,
        'previous_performance' => 80,
        'next_session' => '2024-07-25',
        'last_session' => '2024-07-25',
        'first_session' => '2024-07-25',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // Cache for 1 hour
    return 3600;
  }

}


