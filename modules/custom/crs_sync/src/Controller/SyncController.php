<?php

namespace Drupal\crs_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\crs_sync\Sync\SyncManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse; 

/**
 * Admin UI for CRS sync.
 */
class SyncController extends ControllerBase {

  /** @var \Drupal\crs_sync\Sync\SyncManager */
  protected SyncManager $syncManager;

  public function __construct(SyncManager $sync_manager) {
    $this->syncManager = $sync_manager;
  }

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('crs_sync.sync_manager')
    );
  }

  /**
   * Simple dashboard with action links.
   */
  public function dashboard(): array {
    $items = [
      Link::fromTextAndUrl($this->t('Import Companies'), Url::fromRoute('crs_sync.import_companies'))->toRenderable(),
      Link::fromTextAndUrl($this->t('Import Coaches'), Url::fromRoute('crs_sync.import_coaches'))->toRenderable(),
      Link::fromTextAndUrl($this->t('Import Employees'), Url::fromRoute('crs_sync.import_employees'))->toRenderable(),
      Link::fromTextAndUrl($this->t('Import Questionnaires'), Url::fromRoute('crs_sync.import_questionnaires'))->toRenderable(),
      Link::fromTextAndUrl($this->t('Assign Questionnaires'), Url::fromRoute('crs_sync.company_questionnaire_sync'))->toRenderable(),
    ];
    return [
      '#theme' => 'item_list',
      '#title' => $this->t('CRS Sync'),
      '#items' => $items,
    ];
  }
  
  /**
   * Run the Company ↔ Questionnaire assignment import (multi-assign).
   */
  public function importCompanyQuestionnaires(): array {
    try {
      [$created, $updated, $skipped] = $this->syncManager->syncCompanyQuestionnaireAssignments();
      $this->messenger()->addStatus($this->t(
        'Assignments: @c created, @u updated, @s skipped.',
        ['@c' => $created, '@u' => $updated, '@s' => $skipped]
      ));
    }
    catch (\Throwable $e) {
      $this->getLogger('crs_sync')->error('Company ↔ Questionnaire sync failed: @m', ['@m' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Company ↔ Questionnaire sync failed. Check logs.'));
    }
    return $this->redirectToDashboard();
  }


  public function importCompanies(): array {
    try {
      $n = $this->syncManager->syncCompanies();
      $this->messenger()->addStatus($this->t('Imported/updated @n companies.', ['@n' => $n]));
    }
    catch (\Throwable $e) {
      $this->getLogger('crs_sync')->error('Companies sync failed: @m', ['@m' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Companies sync failed. Check logs.'));
    }
    return $this->redirectToDashboard();
  }

  public function importCoaches(): array {
    try {
      $n = $this->syncManager->syncCoaches();
      $this->messenger()->addStatus($this->t('Imported/updated @n coaches.', ['@n' => $n]));
    }
    catch (\Throwable $e) {
      $this->getLogger('crs_sync')->error('Coaches sync failed: @m', ['@m' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Coaches sync failed. Check logs.'));
    }
    return $this->redirectToDashboard();
  }

  public function importEmployees(): array {
    try {
      $n = $this->syncManager->syncEmployees();
      $this->messenger()->addStatus($this->t('Imported/updated @n employees.', ['@n' => $n]));
    }
    catch (\Throwable $e) {
      $this->getLogger('crs_sync')->error('Employees sync failed: @m', ['@m' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Employees sync failed. Check logs.'));
    }
    return $this->redirectToDashboard();
  }

  public function importQuestionnaires(): array {
    try {
      $n = $this->syncManager->syncQuestionnaires();
      $this->messenger()->addStatus($this->t('Imported/updated @n questionnaires (with categories/questions/options).', ['@n' => $n]));
    }
    catch (\Throwable $e) {
      $this->getLogger('crs_sync')->error('Questionnaire sync failed: @m', ['@m' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Questionnaire sync failed. Check logs.'));
    }
    return $this->redirectToDashboard();
  }

  protected function redirectToDashboard(): array {
    // Return a small render array with a back link; avoids issues when invoking
    // via GET and keeps messages visible.
    return [
      '#type' => 'container',
      'back' => Link::fromTextAndUrl(
        $this->t('Back to CRS Sync dashboard'),
        Url::fromRoute('crs_sync.dashboard')
      )->toRenderable(),
      '#attached' => ['library' => ['core/drupal']],
    ];
  }

}
