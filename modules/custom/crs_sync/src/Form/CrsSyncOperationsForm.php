<?php

namespace Drupal\crs_sync\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\crs_sync\Sync\MigrationFieldAudit;
use Drupal\crs_sync\Sync\MigrationResetService;
use Drupal\crs_sync\Sync\SyncManager;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Single admin page: purge, user import, questionnaires, field check.
 */
class CrsSyncOperationsForm extends FormBase {

  public function __construct(
    protected SyncManager $syncManager,
    protected MigrationResetService $migrationReset,
    protected MigrationFieldAudit $fieldAudit,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('crs_sync.sync_manager'),
      $container->get('crs_sync.migration_reset'),
      $container->get('crs_sync.migration_field_audit'),
    );
  }

  public function getFormId(): string {
    return 'crs_sync_operations';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attributes']['class'][] = 'crs-sync-operations';
    $form['#attached']['library'][] = 'crs_sync/operations_admin';

    $form['intro'] = [
      '#type' => 'inline_template',
      '#template' => '<p>{{ text }}</p><p><strong>{{ legacy }}</strong></p>',
      '#context' => [
        'text' => $this->t('Run legacy database → Drupal steps in order after a purge, or use individual buttons. Requires <code>$databases[\'legacy\']</code> in settings.php (after DDEV include). Use <em>Test legacy database</em> below if imports fail.'),
        'legacy' => $this->t('Recommended order: Purge → Companies → Coaches → Employees → Questionnaires → Assign questionnaires → Coach questionnaire submissions (requires legacy tables qs_coach_submitted_session / qs_coach_submitted_answer and enabled crs_migrate).'),
      ],
    ];

    $form['reset'] = [
      '#type' => 'details',
      '#title' => $this->t('1. Reset (destructive)'),
      '#open' => FALSE,
    ];
    $form['reset']['purge_confirm'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I understand this will delete questionnaire nodes, related paragraphs, company assignment paragraphs, crs_sync map tables, and all users with the company, coach, or employee role (user ID 1 is never removed).'),
      '#required' => FALSE,
    ];
    $form['reset']['purge'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run purge'),
      '#button_type' => 'danger',
      '#submit' => ['::submitPurge'],
      '#validate' => ['::validatePurge'],
    ];

    $form['users'] = [
      '#type' => 'details',
      '#title' => $this->t('2. Import users'),
      '#open' => TRUE,
    ];
    $form['users']['companies'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import companies'),
      '#submit' => ['::submitCompanies'],
    ];
    $form['users']['coaches'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import coaches'),
      '#submit' => ['::submitCoaches'],
    ];
    $form['users']['employees'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import employees'),
      '#submit' => ['::submitEmployees'],
    ];
    $form['users']['all_users'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import all users (companies, then coaches, then employees)'),
      '#submit' => ['::submitAllUsers'],
    ];

    $form['content'] = [
      '#type' => 'details',
      '#title' => $this->t('3. Questionnaires & assignments'),
      '#open' => TRUE,
    ];
    $form['content']['questionnaires'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import questionnaires'),
      '#submit' => ['::submitQuestionnaires'],
    ];
    $form['content']['assignments'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync company ↔ questionnaire assignments'),
      '#submit' => ['::submitAssignments'],
    ];

    $form['submissions'] = [
      '#type' => 'details',
      '#title' => $this->t('4. Coach questionnaire submissions (legacy → Drupal)'),
      '#open' => TRUE,
    ];
    $form['submissions']['help'] = [
      '#type' => 'inline_template',
      '#template' => '<p>{{ text }}</p>',
      '#context' => [
        'text' => $this->t('Imports sessions into <code>coach_reporting_session</code> (and answers into <code>coach_reporting_session_answer</code>) from the legacy connection. Sources: <code>qs_coach_submitted_*</code> (coach on behalf of employee) or <code>qs_emp_questionnaire_filling_master</code> (employee questionnaire fills — migration crs_emp_filling_session). Requires Migrate + crs_migrate, <code>crs_sync_legacy_map</code>, and <code>crs_sync_content_map</code> from questionnaire import.'),
      ],
    ];
    $coach_prereq = $this->syncManager->coachSubmissionPrerequisites();
    $form['submissions']['prereq'] = [
      '#type' => 'markup',
      '#markup' => '<div class="messages ' . ($coach_prereq['ok'] ? 'messages--status' : 'messages--warning') . ' crs-coach-prereq">'
        . '<p><strong>' . $this->t('Coach submission prerequisites (live)') . '</strong></p>'
        . '<ul><li>' . implode('</li><li>', array_map([Html::class, 'escape'], $coach_prereq['lines'])) . '</li></ul>'
        . (!$coach_prereq['ok'] ? '<p>' . $this->t('Fix the items above before expecting imports. A “0 rows imported” result usually means empty legacy submission tables (load a dump or CRS_LEGACY_DATABASE), missing crs_sync_content_map (section 3), or skipped rows — use migrate messages only when legacy counts are non-zero.') . '</p>' : '')
        . '</div>',
      '#weight' => 1,
    ];
    $migrate_ready = \Drupal::hasService('plugin.manager.migration');
    if (!$migrate_ready) {
      $form['submissions']['migrate_notice'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        'text' => [
          '#markup' => '<p>' . $this->t('Coach submission import needs the core <em>Migrate</em> module enabled. Enable it on the Extend page (or <code>drush en migrate</code>), then rebuild caches.') . '</p>',
        ],
        'extend' => Link::fromTextAndUrl(
          $this->t('Open the Extend page'),
          Url::fromRoute('system.modules_list')
        )->toRenderable(),
      ];
    }
    $form['submissions']['sessions'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import coach submission sessions'),
      '#submit' => ['::submitCoachSubmissionSessions'],
      '#disabled' => !$migrate_ready,
    ];
    $form['submissions']['answers'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import coach submission answers'),
      '#submit' => ['::submitCoachSubmissionAnswers'],
      '#disabled' => !$migrate_ready,
    ];
    $form['submissions']['all'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import all coach submissions (sessions, then answers)'),
      '#submit' => ['::submitCoachSubmissionsAll'],
      '#disabled' => !$migrate_ready,
    ];
    $form['submissions']['emp_sessions'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import employee filling sessions (qs_emp_questionnaire_filling_master)'),
      '#submit' => ['::submitEmpFillingSessions'],
      '#disabled' => !$migrate_ready,
    ];

    $form['diagnostics'] = [
      '#type' => 'details',
      '#title' => $this->t('5. Diagnostics'),
      '#open' => FALSE,
    ];
    $form['diagnostics']['verify'] = [
      '#type' => 'submit',
      '#value' => $this->t('Verify required fields'),
      '#submit' => ['::submitVerifyFields'],
    ];
    $form['diagnostics']['legacy_test'] = [
      '#type' => 'submit',
      '#value' => $this->t('Test legacy database'),
      '#submit' => ['::submitLegacyTest'],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  public function validatePurge(array &$form, FormStateInterface $form_state): void {
    if (empty($form_state->getValue('purge_confirm'))) {
      $form_state->setErrorByName('purge_confirm', $this->t('Confirm the checkbox before running purge.'));
    }
  }

  public function submitPurge(array &$form, FormStateInterface $form_state): void {
    try {
      $summary = $this->migrationReset->purgeAll();
      $this->messenger()->addStatus($this->t(
        'Purge finished. Questionnaires: @q, profiles cleared: @p, paragraph roots swept: @s, map rows removed: @m, users deleted: @u.',
        [
          '@q' => $summary['questionnaires_deleted'],
          '@p' => $summary['profiles_cleared'],
          '@s' => $summary['paragraphs_swept'],
          '@m' => $summary['map_rows_cleared'],
          '@u' => $summary['users_deleted'],
        ]
      ));
    }
    catch (\Throwable $e) {
      $this->reportSyncException('Purge', $e);
    }
    $form_state->setRedirect('crs_sync.operations');
  }

  public function submitCompanies(array &$form, FormStateInterface $form_state): void {
    $this->runSafe(fn() => $this->syncManager->syncCompanies(), 'companies', fn($n) => $this->t('Imported/updated @n companies.', ['@n' => $n]));
    $form_state->setRedirect('crs_sync.operations');
  }

  public function submitCoaches(array &$form, FormStateInterface $form_state): void {
    $this->runSafe(fn() => $this->syncManager->syncCoaches(), 'coaches', fn($n) => $this->t('Imported/updated @n coaches.', ['@n' => $n]));
    $form_state->setRedirect('crs_sync.operations');
  }

  public function submitEmployees(array &$form, FormStateInterface $form_state): void {
    $this->runSafe(fn() => $this->syncManager->syncEmployees(), 'employees', fn($n) => $this->t('Imported/updated @n employees.', ['@n' => $n]));
    $form_state->setRedirect('crs_sync.operations');
  }

  public function submitAllUsers(array &$form, FormStateInterface $form_state): void {
    try {
      $total = 0;
      $total += $this->syncManager->syncCompanies();
      $total += $this->syncManager->syncCoaches();
      $total += $this->syncManager->syncEmployees();
      $this->messenger()->addStatus($this->t('All user types synced. Total row operations: @c.', ['@c' => $total]));
    }
    catch (\Throwable $e) {
      $this->reportSyncException('User sync', $e);
    }
    $form_state->setRedirect('crs_sync.operations');
  }

  public function submitQuestionnaires(array &$form, FormStateInterface $form_state): void {
    try {
      [$created, $updated] = $this->syncManager->syncQuestionnaires();
      $this->messenger()->addStatus($this->t(
        'Questionnaires synced. Created: @c, updated: @u.',
        ['@c' => $created, '@u' => $updated]
      ));
    }
    catch (\Throwable $e) {
      $this->reportSyncException('Questionnaire sync', $e);
    }
    $form_state->setRedirect('crs_sync.operations');
  }

  public function submitAssignments(array &$form, FormStateInterface $form_state): void {
    try {
      [$created, $updated, $skipped] = $this->syncManager->syncCompanyQuestionnaireAssignments();
      $this->messenger()->addStatus($this->t(
        'Assignments: @c created, @u updated, @s skipped.',
        ['@c' => $created, '@u' => $updated, '@s' => $skipped]
      ));
    }
    catch (\Throwable $e) {
      $this->reportSyncException('Assignment sync', $e);
    }
    $form_state->setRedirect('crs_sync.operations');
  }

  public function submitVerifyFields(array &$form, FormStateInterface $form_state): void {
    $missing = $this->fieldAudit->getMissingRequiredFields();
    if ($missing) {
      foreach ($missing as $line) {
        $this->messenger()->addWarning($line);
      }
      $this->messenger()->addError($this->t('Some required fields are missing. Import configuration or add fields before migrating.'));
    }
    else {
      $this->messenger()->addStatus($this->t('All required fields for crs_sync are present.'));
    }
    $form_state->setRedirect('crs_sync.operations');
  }

  public function submitLegacyTest(array &$form, FormStateInterface $form_state): void {
    $diag = $this->syncManager->legacyDiagnostics();
    if ($diag['ok']) {
      $this->messenger()->addStatus(Html::escape($diag['detail']));
    }
    else {
      $this->messenger()->addError(Html::escape($diag['detail']));
    }
    $form_state->setRedirect('crs_sync.operations');
  }

  public function submitCoachSubmissionSessions(array &$form, FormStateInterface $form_state): void {
    $this->runMigrationImport('crs_coach_submission_session', $this->t('Coach submission sessions'));
    $form_state->setRedirect('crs_sync.operations');
  }

  public function submitCoachSubmissionAnswers(array &$form, FormStateInterface $form_state): void {
    $this->runMigrationImport('crs_coach_submission_answer', $this->t('Coach submission answers'));
    $form_state->setRedirect('crs_sync.operations');
  }

  public function submitCoachSubmissionsAll(array &$form, FormStateInterface $form_state): void {
    $this->runMigrationImport('crs_coach_submission_session', $this->t('Coach submission sessions'));
    $this->runMigrationImport('crs_coach_submission_answer', $this->t('Coach submission answers'));
    $form_state->setRedirect('crs_sync.operations');
  }

  public function submitEmpFillingSessions(array &$form, FormStateInterface $form_state): void {
    $this->runMigrationImport('crs_emp_filling_session', $this->t('Employee questionnaire filling sessions'));
    $form_state->setRedirect('crs_sync.operations');
  }

  /**
   * Runs a single migration import by id (logs migrate messages to watchdog).
   */
  protected function runMigrationImport(string $migration_id, $label): void {
    if (!\Drupal::hasService('plugin.manager.migration')) {
      $this->messenger()->addError($this->t('@label: the core Migrate module must be enabled. Use the Extend page or <code>drush en migrate</code>, then clear caches.', [
        '@label' => $label,
      ]));
      return;
    }
    /** @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_manager */
    $migration_manager = \Drupal::service('plugin.manager.migration');
    if (!\Drupal::moduleHandler()->moduleExists('crs_migrate')) {
      $this->messenger()->addWarning($this->t('Enable the <em>crs_migrate</em> module. It provides the @session and @answer migrations.', [
        '@session' => 'crs_coach_submission_session',
        '@answer' => 'crs_coach_submission_answer',
      ]));
      return;
    }
    if (!$migration_manager->hasDefinition($migration_id)) {
      $this->messenger()->addError($this->t('@label: migration @id is not registered. Enable crs_migrate, run database updates, and clear caches. On existing sites, ensure migration config is imported.', [
        '@label' => $label,
        '@id' => $migration_id,
      ]));
      return;
    }
    $legacy_tables_error = $this->syncManager->legacyMigrateSourceTablesError($migration_id);
    if ($legacy_tables_error !== NULL) {
      $this->messenger()->addError($this->t('@label: @detail', [
        '@label' => $label,
        '@detail' => $legacy_tables_error,
      ]));
      return;
    }
    try {
      /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
      $migration = $migration_manager->createInstance($migration_id);
      $executable = new MigrateExecutable($migration, new MigrateMessage());
      $result = $executable->import();
      $count = (int) $migration->getIdMap()->importedCount();
      if ($result === MigrationInterface::RESULT_COMPLETED) {
        $this->messenger()->addStatus($this->t('@label: completed (@n rows in map). Check logs for per-row skips.', [
          '@label' => $label,
          '@n' => $count,
        ]));
        if ($count === 0 && in_array($migration_id, [
          'crs_coach_submission_session',
          'crs_coach_submission_answer',
          'crs_emp_filling_session',
        ], TRUE)) {
          foreach ($this->syncManager->coachSubmissionPrerequisites()['lines'] as $line) {
            $this->messenger()->addWarning(Html::escape($line));
          }
          $this->messenger()->addWarning($this->t('If the legacy row counts above are greater than zero but this migration still imported nothing, run <code>drush migrate:messages @id</code> for per-row skip reasons.', [
            '@id' => $migration_id,
          ]));
        }
      }
      elseif ($result === MigrationInterface::RESULT_INCOMPLETE) {
        $this->messenger()->addWarning($this->t('@label: stopped early (incomplete). Increase PHP limits or run <code>drush migrate:import @id</code> from CLI.', [
          '@label' => $label,
          '@id' => $migration_id,
        ]));
      }
      else {
        $this->messenger()->addError($this->t('@label: import failed or was interrupted (result code @c). See watchdog under “migrate”.', [
          '@label' => $label,
          '@c' => (string) $result,
        ]));
      }
    }
    catch (\Throwable $e) {
      $this->reportSyncException((string) $label, $e);
    }
  }

  /**
   * @param callable(): int $callback
   * @param callable(int): \Drupal\Core\StringTranslation\TranslatableMarkup|string $successMessage
   */
  protected function runSafe(callable $callback, string $context, callable $successMessage): void {
    try {
      $n = (int) $callback();
      $this->messenger()->addStatus($successMessage($n));
    }
    catch (\Throwable $e) {
      $this->reportSyncException(ucfirst($context) . ' import', $e);
    }
  }

  /**
   * Logs and shows the underlying error to admins (escaped plain text).
   */
  protected function reportSyncException(string $label, \Throwable $e): void {
    $this->getLogger('crs_sync')->error('@label: @m', [
      '@label' => $label,
      '@m' => $e->getMessage(),
    ]);
    $this->messenger()->addError($this->t('@label: @msg', [
      '@label' => $label,
      '@msg' => substr($e->getMessage(), 0, 2000),
    ]));
  }

}
