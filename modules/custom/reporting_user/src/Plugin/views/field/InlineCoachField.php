<?php

namespace Drupal\reporting_user\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\profile\Entity\Profile;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an inline editable coach multi-select field for employee views.
 *
 * @ViewsField("inline_coach_field")
 */
class InlineCoachField extends FieldPluginBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a InlineCoachField object.
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

    // Get employee's company.
    $company_id = NULL;
    if ($profile->hasField('field_company') && !$profile->get('field_company')->isEmpty()) {
      $company_entity = $profile->get('field_company')->entity;
      if ($company_entity) {
        $company_id = $company_entity->id();
      }
    }

    // Get current coach assignments.
    $current_coaches = [];
    $current_coach_names = [];
    if ($profile->hasField('field_coach') && !$profile->get('field_coach')->isEmpty()) {
      foreach ($profile->get('field_coach') as $coach_item) {
        if ($coach_item->entity) {
          $coach = $coach_item->entity;
          // Use string keys to ensure proper matching with option keys.
          $current_coaches[] = (string) $coach->id();
          
          // Get coach name.
          $first_name = $coach->hasField('field_first_name') && !$coach->get('field_first_name')->isEmpty()
            ? $coach->get('field_first_name')->value
            : '';
          $last_name = $coach->hasField('field_last_name') && !$coach->get('field_last_name')->isEmpty()
            ? $coach->get('field_last_name')->value
            : '';
          
          $name = trim($first_name . ' ' . $last_name);
          if (empty($name)) {
            $name = $coach->getAccountName();
          }
          $current_coach_names[] = $name;
        }
      }
    }

    // Get coaches filtered by employee's company.
    $coaches = $this->getCoaches($company_id);
    $coach_options = $this->formatCoachOptions($coaches);

    // Build the multi-select dropdown wrapper.
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['inline-coach-field-wrapper'],
      ],
      '#attached' => [
        'library' => ['reporting_user/inline_coach'],
      ],
    ];

    // If no company is assigned, show a message.
    if (!$company_id) {
      $build['message'] = [
        '#type' => 'markup',
        '#markup' => '<div class="coach-no-company-message" style="color: #dc3545; font-size: 0.875rem; padding: 0.5rem;">No company assigned to employee. Please assign a company first.</div>',
      ];
      return $build;
    }

    // If no coaches available for this company, show a message.
    if (empty($coaches)) {
      $build['message'] = [
        '#type' => 'markup',
        '#markup' => '<div class="coach-no-coaches-message" style="color: #856404; font-size: 0.875rem; padding: 0.5rem;">No coaches available for this company.</div>',
      ];
      return $build;
    }

    $build['select'] = [
      '#type' => 'select',
      '#attributes' => [
        'class' => ['form-control', 'updatestatus', 'coach-multiselect'],
        'data-id' => $employee_id,
        'data-val' => 'update_coach',
        'data-default-value' => !empty($current_coaches) ? implode(',', $current_coaches) : '',
        'multiple' => 'multiple',
        'name' => 'coach_' . $employee_id . '[]',
        'style' => 'min-width: 200px;',
      ],
      '#options' => $coach_options,
      '#default_value' => $current_coaches,
      '#value' => $current_coaches,
    ];

    // Add current selection display.
    if (!empty($current_coach_names)) {
      $build['current'] = [
        '#type' => 'markup',
        '#markup' => '<div class="current-coaches">' . implode(', ', $current_coach_names) . '</div>',
        '#prefix' => '<div class="coach-display">',
        '#suffix' => '</div>',
      ];
    }

    return $build;
  }

  /**
   * Get coaches filtered by company.
   *
   * @param int|null $company_id
   *   The company user ID to filter by. If NULL, returns all coaches.
   *
   * @return \Drupal\user\UserInterface[]
   *   Array of coach users that belong to the specified company.
   */
  protected function getCoaches($company_id = NULL) {
    $profile_storage = $this->entityTypeManager->getStorage('profile');
    
    // Build query for coach profiles.
    $query = $profile_storage->getQuery()
      ->condition('type', 'coach')
      ->condition('status', 1)
      ->accessCheck(FALSE);
    
    // Filter by company if provided.
    if ($company_id) {
      $query->condition('field_company.target_id', $company_id);
    }
    
    $profile_ids = $query->execute();
    
    if (empty($profile_ids)) {
      return [];
    }
    
    // Load coach profiles and get their user IDs.
    $coach_profiles = $profile_storage->loadMultiple($profile_ids);
    $coach_uids = [];
    
    foreach ($coach_profiles as $coach_profile) {
      $uid = $coach_profile->getOwnerId();
      if ($uid) {
        $coach_uids[] = $uid;
      }
    }
    
    if (empty($coach_uids)) {
      return [];
    }
    
    // Load coach users and verify they have the coach role and are active.
    $user_storage = $this->entityTypeManager->getStorage('user');
    $coach_users = $user_storage->loadMultiple($coach_uids);
    
    $valid_coaches = [];
    foreach ($coach_users as $coach_user) {
      // Verify user is active and has coach role.
      if ($coach_user->isActive() && $coach_user->hasRole('coach')) {
        $valid_coaches[] = $coach_user;
      }
    }
    
    return $valid_coaches;
  }

  /**
   * Format coach options for select dropdown.
   *
   * @param \Drupal\user\UserInterface[] $coaches
   *   Array of coach users.
   *
   * @return array
   *   Options array for select element.
   */
  protected function formatCoachOptions(array $coaches) {
    $options = [];
    
    foreach ($coaches as $coach) {
      $first_name = $coach->hasField('field_first_name') && !$coach->get('field_first_name')->isEmpty()
        ? $coach->get('field_first_name')->value
        : '';
      $last_name = $coach->hasField('field_last_name') && !$coach->get('field_last_name')->isEmpty()
        ? $coach->get('field_last_name')->value
        : '';
      
      $name = trim($first_name . ' ' . $last_name);
      if (empty($name)) {
        $name = $coach->getAccountName();
      }
      
      // Use string keys to ensure proper matching with default_value.
      $options[(string) $coach->id()] = $name;
    }

    return $options;
  }

}

