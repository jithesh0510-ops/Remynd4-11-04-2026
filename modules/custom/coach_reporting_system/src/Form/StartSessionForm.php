<?php

namespace Drupal\coach_reporting_system\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Coach-only form to start a new coaching session.
 * All select fields use the Select2 form element.
 */
class StartSessionForm extends FormBase {

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
  protected EntityTypeManagerInterface $entityTypeManager;

  /** @var \Drupal\Core\TempStore\PrivateTempStore */
  protected $tempstore;

  // Employee profile wiring (adjust if your machine names differ).
  private const EMPLOYEE_PROFILE_TYPE   = 'employee';
  private const EMPLOYEE_FIELD_COMPANY  = 'field_company';
  private const EMPLOYEE_FIELD_COACH    = 'field_coach';
  private const EMPLOYEE_FIELD_PROGRAM  = 'field_program';

  public function __construct(EntityTypeManagerInterface $entity_type_manager, PrivateTempStoreFactory $tempstore_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->tempstore = $tempstore_factory->get('coach_reporting_system');
  }

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('tempstore.private')
    );
  }

  public function getFormId(): string {
    return 'coach_reporting_system_start_session_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $account = $this->currentUser();
    if (!in_array('coach', $account->getRoles(), TRUE)) {
      return ['#markup' => $this->t('Access denied. Coaches only.')];
    }

    $selected_company  = $form_state->getValue('company') ?: '';
    $selected_program  = $form_state->getValue('program') ?: '';
    $selected_employee = $form_state->getValue('employee') ?: '';

    $form['#prefix'] = '<div id="start-session-wrapper">';
    $form['#suffix'] = '</div>';
    $form['#cache']['max-age'] = 0;

    // 1) Company (Select2) — companies assigned to this coach.
    $company_options = $this->getCompaniesForCoachOptions((int) $account->id());
    $form['company'] = [
      '#type' => 'select2',
      '#title' => $this->t('Select Company'),
      '#options' => $company_options,
      '#empty_option' => $this->t('- Select company -'),
      '#default_value' => $selected_company,
      '#required' => TRUE,
      // Select2 options:
      '#select2' => [
        'allowClear' => TRUE,
        'width' => 'resolve',
        // 'placeholder' is taken from #empty_option automatically.
      ],
      '#ajax' => [
        'callback' => '::updateDependents',
        'wrapper' => 'start-session-deps',
        'event' => 'change',
      ],
    ];

    // Dependents container.
    $form['dependents'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'start-session-deps'],
      '#states' => [
        'visible' => [
          ':input[name="company"]' => ['!value' => ''],
        ],
      ],
    ];

    // 2) Program (Select2) — questionnaires assigned to selected company.
    $program_options = [];
    if (!empty($selected_company)) {
      $program_options = $this->getQuestionnairesByCompany((int) $selected_company);
    }
    $form['dependents']['program'] = [
      '#type' => 'select2',
      '#title' => $this->t('Select a Program'),
      '#description' => $this->t('Pulled from the selected company’s assigned questionnaires.'),
      '#options' => $program_options,
      '#empty_option' => empty($selected_company) ? $this->t('Select a company first') : $this->t('- Select program -'),
      '#default_value' => $selected_program,
      '#required' => TRUE,
      '#select2' => [
        'allowClear' => TRUE,
        'width' => 'resolve',
      ],
      '#states' => [
        'disabled' => [
          ':input[name="company"]' => ['value' => ''],
        ],
      ],
      '#ajax' => [
        'callback' => '::updateDependents',
        'wrapper' => 'start-session-deps',
        'event' => 'change',
      ],
    ];

    // 3) Employee (Select2) — employees in that company, filtered by program + current coach.
    $employee_options = [];
    if (!empty($selected_company) && !empty($selected_program)) {
      $employee_options = $this->getEmployeeByCompanyCoachProgram(
        (int) $selected_company,
        (int) $account->id(),       // current coach
        (int) $selected_program,
        'active'
      );
    }
    $form['dependents']['employee'] = [
      '#type' => 'select2',
      '#title' => $this->t('Select an Employee'),
      '#options' => $employee_options,
      '#empty_option' => empty($selected_program) ? $this->t('Select a program first') : $this->t('- Select employee -'),
      '#default_value' => $selected_employee,
      '#required' => TRUE,
      '#select2' => [
        'allowClear' => TRUE,
        'width' => 'resolve',
      ],
      '#states' => [
        'disabled' => [
          ':input[name="program"]' => ['value' => ''],
        ],
      ],
    ];

    // 4) Fill date (native date).
    $form['dependents']['fill_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Questionnaire Filling Date'),
      '#default_value' => $form_state->getValue('fill_date') ?: date('Y-m-d'),
      '#required' => TRUE,
      '#states' => [
        'disabled' => [
          ':input[name="employee"]' => ['value' => ''],
        ],
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['start'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Session'),
      '#button_type' => 'primary',
    ];

    // Make sure core/once + Ajax behaviors are present (usually auto).
    $form['#attached']['library'][] = 'core/drupal';
    $form['#attached']['library'][] = 'core/drupal.ajax';
    $form['#attached']['library'][] = 'core/once';

    return $form;
  }

  /**
   * AJAX refresh for dependents.
   */
  public function updateDependents(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    return $form['dependents'];
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $coach_uid    = (int) $this->currentUser()->id();
    $company_uid  = (int) $form_state->getValue('company');
    $program_nid  = (int) $form_state->getValue('program');
    $employee_uid = (int) $form_state->getValue('employee');
    $fill_date    = $form_state->getValue('fill_date');

    // Persist a row for reporting.
    $connection = \Drupal::database();
    $sid = $connection->insert('coach_reporting_session')
      ->fields([
        'coach_uid'   => $coach_uid,
        'company_uid' => $company_uid,
        'program_nid' => $program_nid,
        'employee_uid'=> $employee_uid,
        'fill_date'   => $fill_date,
        'created'     => \Drupal::time()->getRequestTime(),
      ])
      ->execute();

    // Put in tempstore for the run form.
    $this->tempstore->set('current_session', [
      'sid'          => (int) $sid,
      'coach_uid'    => $coach_uid,
      'company_uid'  => $company_uid,
      'program_nid'  => $program_nid,
      'employee_uid' => $employee_uid,
      'fill_date'    => $fill_date,
    ]);

    $form_state->setRedirect('coach_reporting_system.session_run');
  }

  // ---------------- Helpers ----------------

  protected function profileFieldExists(string $bundle, string $field_name): bool {
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('profile', $bundle);
    return isset($definitions[$field_name]);
  }

    /**
     * Build "Select Company" options for a given coach user.
     *
     * Returns companies referenced on the coach's active profile (field_company).
     */
    protected function getCompaniesForCoachOptions(int $coach_uid): array {
      $options = [];
    
      // 1) Load the coach account.
      /** @var \Drupal\user\UserInterface|null $coach_account */
      $coach_account = $this->entityTypeManager->getStorage('user')->load($coach_uid);
      if (!$coach_account || !$coach_account->isActive()) {
        return $options;
      }
    
      // 2) Load the active 'coach' profile via helper (fast path).
      /** @var \Drupal\profile\ProfileStorageInterface $profile_storage */
      $profile_storage = $this->entityTypeManager->getStorage('profile');
    
      $profile = $profile_storage->loadByUser($coach_account, 'coach', TRUE);
    
      // Some Profile versions always return an entity; others may return NULL or array.
      if (is_array($profile)) {
        $profile = reset($profile) ?: NULL;
      }
    
      // 3) Fallback: entity query for latest active coach profile (if needed).
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
      }
    
      if (!$profile) {
        return $options;
      }
    
      // 4) Collect company user IDs from coach profile's field_company.
      if (!$this->profileFieldExists('coach', 'field_company')
        || !$profile->hasField('field_company')
        || $profile->get('field_company')->isEmpty()) {
        return $options;
      }
    
      $company_uids = [];
      foreach ($profile->get('field_company')->referencedEntities() as $company_user) {
        $id = (int) $company_user->id();
        if ($id > 0) {
          $company_uids[$id] = $id; // de-dup
        }
      }
    
      if (!$company_uids) {
        return $options;
      }
    
      // 5) Load company users, keep only active users with 'company' role.
      /** @var \Drupal\user\UserInterface[] $accounts */
      $accounts = $this->entityTypeManager->getStorage('user')->loadMultiple(array_values($company_uids));
    
      foreach ($accounts as $account) {
        if (!$account->isActive() || !in_array('company', $account->getRoles(), TRUE)) {
          continue;
        }
    
        // Label as "Full Name (email)" with fallback to username.
        $full_name = ($account->hasField('field_full_name') && !$account->get('field_full_name')->isEmpty())
          ? trim((string) $account->get('field_full_name')->value)
          : $account->label();
    
        $email = method_exists($account, 'getEmail')
          ? $account->getEmail()
          : ($account->get('mail')->value ?? '');
    
        $options[$account->id()] = $email ? sprintf('%s (%s)', $full_name, $email) : $full_name;
      }
    
      // Natural, case-insensitive sort by label.
      asort($options, SORT_NATURAL | SORT_FLAG_CASE);
      return $options;
    }

    /**
     * Given a company user ID, return questionnaires assigned on its Company profile.
     *
     * Profile type: company
     * Field on profile: field_select_questionnaire (Paragraphs, E.R.R.)
     * Field on paragraph: field_questionnaire (Node, bundle = questionnaire)
     */
    protected function getQuestionnairesByCompany(int $company_uid): array {
      $options = [];
    
      // 1) Load the company user.
      /** @var \Drupal\user\UserInterface|null $company_account */
      $company_account = $this->entityTypeManager->getStorage('user')->load($company_uid);
      if (!$company_account) {
        return $options;
      }
    
      // 2) Load the active 'company' profile via loadByUser (fast path).
      /** @var \Drupal\profile\ProfileStorageInterface $profile_storage */
      $profile_storage = $this->entityTypeManager->getStorage('profile');
      $profile = $profile_storage->loadByUser($company_account, 'company', TRUE);
    
      // Some Profile versions may return an array; normalize to a single profile.
      if (is_array($profile)) {
        $profile = reset($profile) ?: NULL;
      }
    
      // 3) Fallback: latest active company profile via entity query.
      if (!$profile) {
        $pids = $profile_storage->getQuery()
          ->condition('uid', $company_uid)
          ->condition('type', 'company')
          ->condition('status', 1)
          ->sort('created', 'DESC')
          ->range(0, 1)
          ->accessCheck(TRUE)
          ->execute();
    
        $profile = $pids ? $profile_storage->load(reset($pids)) : NULL;
        if (!$profile) {
          return $options;
        }
      }
    
      // 4) Guard: ensure the field exists and has values.
      if (!$this->profileFieldExists('company', 'field_select_questionnaire')
        || !$profile->hasField('field_select_questionnaire')
        || $profile->get('field_select_questionnaire')->isEmpty()) {
        return $options;
      }
    
      // 5) Iterate referenced Paragraphs (Entity Reference Revisions).
      $questionnaire_ids = [];
      $paragraphs = $profile->get('field_select_questionnaire')->referencedEntities();
    
      foreach ($paragraphs as $para) {
        if ($para->hasField('field_questionnaire') && !$para->get('field_questionnaire')->isEmpty()) {
          $node = $para->get('field_questionnaire')->entity;
          if ($node && $node->bundle() === 'questionnaire') {
            $questionnaire_ids[$node->id()] = $node->id(); // de-dup
          }
        }
      }
    
      if (!$questionnaire_ids) {
        return $options;
      }
    
      // 6) Load nodes to build labels (optional: filter to published only).
      /** @var \Drupal\node\NodeInterface[] $nodes */
      $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple(array_values($questionnaire_ids));
      foreach ($nodes as $node) {
        // If you want only published questionnaires, uncomment:
        // if (!$node->isPublished()) { continue; }
        $options[$node->id()] = $node->label();
      }
    
      asort($options, SORT_NATURAL | SORT_FLAG_CASE);
      return $options;
    }

 
     /**
     * Build Employee options filtered by Company (required), Coach (optional),
     * Program (optional), and user status ('active' | 'inactive' | 'any').
     *
     * Source of truth = Employee profile type with fields:
     *  - field_company  (User reference: Company)   [REQUIRED to exist]
     *  - field_coach    (User reference: Coach)     [OPTIONAL filter]
     *  - field_program  (Node reference: Questionnaire) [OPTIONAL filter]
     */
    protected function getEmployeeByCompanyCoachProgram(
      int $company_uid,
      ?int $coach_uid = NULL,
      ?int $program_nid = NULL,
      string $status = 'active'
    ): array {
      $options = [];
    
      /** @var \Drupal\profile\ProfileStorageInterface $profile_storage */
      $profile_storage = $this->entityTypeManager->getStorage('profile');
    
      // Guard: required field must exist on the employee profile bundle.
      if (!$this->profileFieldExists(self::EMPLOYEE_PROFILE_TYPE, self::EMPLOYEE_FIELD_COMPANY)) {
        \Drupal::logger('coach_reporting_system')->error(
          'Missing @field on @bundle profile bundle.',
          ['@field' => self::EMPLOYEE_FIELD_COMPANY, '@bundle' => self::EMPLOYEE_PROFILE_TYPE]
        );
        return $options; 
      }
    
      // 1) Profile query: find employee profiles matching Company (+ optional Coach/Program).
      $pquery = $profile_storage->getQuery()
        ->condition('type', self::EMPLOYEE_PROFILE_TYPE)
        ->condition('status', 1) // only active profile rows
        ->condition(self::EMPLOYEE_FIELD_COMPANY . '.target_id', $company_uid)
        ->accessCheck(TRUE);
    
      if (!empty($coach_uid) && $this->profileFieldExists(self::EMPLOYEE_PROFILE_TYPE, self::EMPLOYEE_FIELD_COACH)) {
        $pquery->condition(self::EMPLOYEE_FIELD_COACH . '.target_id', (int) $coach_uid);
      }
    
      $pids = $pquery->execute();
      if (!$pids) {
        return $options;
      }
    
      // 2) Collect employee UIDs from those profiles (de-dup).
      $employee_uids = [];
      foreach ($profile_storage->loadMultiple($pids) as $profile) {
        $uid = (int) $profile->getOwnerId();
        if ($uid > 0) {
          $employee_uids[$uid] = $uid;
        }
      }
      if (!$employee_uids) {
        return $options;
      }
    
      // 3) User query filtered by ACTIVE/INACTIVE status (status is on user, not profile).
      $user_storage = $this->entityTypeManager->getStorage('user');
      $uquery = $user_storage->getQuery()
        ->condition('uid', array_values($employee_uids), 'IN')
        ->accessCheck(TRUE);
    
      if ($status === 'active') {
        $uquery->condition('status', 1);
      } elseif ($status === 'inactive') {
        $uquery->condition('status', 0);
      } // else 'any' → no filter
    
      $uids = $uquery->execute();
      if (!$uids) {
        return $options;
      }
    
      // 4) Build "Full Name (email)" labels.
      /** @var \Drupal\user\UserInterface[] $users */
      $users = $user_storage->loadMultiple($uids);
      foreach ($users as $user) {
        $full_name = ($user->hasField('field_full_name') && !$user->get('field_full_name')->isEmpty())
          ? trim((string) $user->get('field_full_name')->value)
          : $user->label();
    
        $email = method_exists($user, 'getEmail')
          ? $user->getEmail()
          : ($user->get('mail')->value ?? '');
    
        $options[$user->id()] = $email ? sprintf('%s (%s)', $full_name, $email) : $full_name;
      }
    
      // 5) Natural, case-insensitive sort by label.
      asort($options, SORT_NATURAL | SORT_FLAG_CASE);
      return $options;
    }


}
