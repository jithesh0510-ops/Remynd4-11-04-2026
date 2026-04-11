<?php

namespace Drupal\reporting_user\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\profile\Entity\Profile;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an inline editable job position dropdown field for employee views.
 *
 * @ViewsField("inline_job_position_field")
 */
class InlineJobPositionField extends FieldPluginBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a InlineJobPositionField object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing -- we don't need to add anything to the query.
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $user = $values->_entity;
    if (!$user || $user->getEntityTypeId() !== 'user') {
      return '';
    }

    // Load employee profile.
    $profile_storage = $this->entityTypeManager->getStorage('profile');
    $profiles = $profile_storage->loadByProperties([
      'uid' => $user->id(),
      'type' => 'employee',
      'status' => 1,
    ]);

    if (empty($profiles)) {
      return '';
    }

    $profile = reset($profiles);
    $employee_id = $profile->id() ?: $user->uuid();

    // Get current job position.
    $current_job_position_id = NULL;
    $current_job_position_name = '';
    if ($profile->hasField('field_job_position') && !$profile->get('field_job_position')->isEmpty()) {
      $job_position_term = $profile->get('field_job_position')->entity;
      if ($job_position_term) {
        $current_job_position_id = (string) $job_position_term->id();
        $current_job_position_name = $job_position_term->getName();
      }
    }

    // Get all job position options from taxonomy.
    $job_position_options = $this->getJobPositionOptions();

    // Build the select dropdown wrapper.
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['inline-job-position-field-wrapper'],
      ],
      '#attached' => [
        'library' => ['reporting_user/inline_job_position'],
      ],
    ];

    // If no job positions available, show a message.
    if (empty($job_position_options)) {
      $build['message'] = [
        '#type' => 'markup',
        '#markup' => '<div class="job-position-no-options-message" style="color: #856404; font-size: 0.875rem; padding: 0.5rem;">No job positions available.</div>',
      ];
      return $build;
    }

    $build['select'] = [
      '#type' => 'select',
      '#attributes' => [
        'class' => ['form-control', 'updatestatus', 'job-position-select'],
        'data-id' => $employee_id,
        'data-val' => 'update_job_position',
        'data-default-value' => $current_job_position_id ?: '',
        'name' => 'job_position_' . $employee_id,
        'style' => 'min-width: 200px;',
      ],
      '#options' => $job_position_options,
      '#default_value' => $current_job_position_id,
      '#value' => $current_job_position_id,
    ];

    // Add current selection display.
    if (!empty($current_job_position_name)) {
      $build['current'] = [
        '#type' => 'markup',
        '#markup' => '<div class="current-job-position">' . $current_job_position_name . '</div>',
        '#prefix' => '<div class="job-position-display">',
        '#suffix' => '</div>',
      ];
    }

    return $build;
  }

  /**
   * Get job position options from taxonomy.
   *
   * @return array
   *   Options array for select element (tid => name).
   */
  protected function getJobPositionOptions() {
    $options = ['' => '- Select Job Position -'];
    
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    
    // Load all terms from job_position vocabulary.
    $terms = $term_storage->loadTree('job_position', 0, NULL, TRUE);
    
    foreach ($terms as $term) {
      if ($term->isPublished()) {
        // Use string keys to ensure proper matching with default_value.
        $options[(string) $term->id()] = $term->getName();
      }
    }
    
    // Sort alphabetically.
    asort($options, SORT_NATURAL | SORT_FLAG_CASE);
    
    return $options;
  }

}



