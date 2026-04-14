<?php

namespace Drupal\crs_sync\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\crs_sync\Sync\MigrationResetService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirms destructive purge of CRS sync data.
 */
class PurgeMigrationConfirmForm extends ConfirmFormBase {

  public function __construct(
    protected MigrationResetService $migrationReset,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('crs_sync.migration_reset'),
    );
  }

  public function getFormId(): string {
    return 'crs_sync_purge_migration_confirm';
  }

  public function getQuestion() {
    return $this->t('Delete all questionnaire content, assignment paragraphs, legacy map tables, and users with company / coach / employee roles (except user ID 1)?');
  }

  public function getCancelUrl() {
    return Url::fromRoute('crs_sync.operations');
  }

  public function getConfirmText() {
    return $this->t('Purge');
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $summary = $this->migrationReset->purgeAll();
    $this->messenger()->addStatus($this->t(
      'Purge done. Questionnaires deleted: @q, profile clears: @p, paragraph sweep: @s, map rows cleared: @m, users deleted: @u.',
      [
        '@q' => $summary['questionnaires_deleted'],
        '@p' => $summary['profiles_cleared'],
        '@s' => $summary['paragraphs_swept'],
        '@m' => $summary['map_rows_cleared'],
        '@u' => $summary['users_deleted'],
      ]
    ));
    $form_state->setRedirectUrl(Url::fromRoute('crs_sync.operations'));
  }

}
