<?php

namespace Drupal\crs_sync\Sync;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceRevisionsFieldItemList;
use Drupal\Core\Session\AccountInterface;
use Drupal\paragraphs\ParagraphInterface;
use Psr\Log\LoggerInterface;

/**
 * Destroys CRS sync–generated content so a fresh legacy import can run.
 *
 * Preserves user 1 and any account without the company, coach, or employee
 * roles (e.g. site builders with only “administrator”).
 */
class MigrationResetService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
    protected LoggerInterface $logger,
    protected AccountInterface $currentUser,
  ) {}

  /**
   * Runs the full purge. Returns a short summary for CLI/UI.
   *
   * @return array<string, int>
   *   Counts: profiles_cleared, questionnaires_deleted, paragraphs_swept,
   *   map_rows_cleared, users_deleted.
   */
  public function purgeAll(): array {
    $summary = [
      'profiles_cleared' => 0,
      'questionnaires_deleted' => 0,
      'paragraphs_swept' => 0,
      'map_rows_cleared' => 0,
      'users_deleted' => 0,
    ];

    $summary['profiles_cleared'] = $this->purgeCompanyProfileParagraphs();
    $summary['questionnaires_deleted'] = $this->purgeQuestionnaireNodes();
    $summary['paragraphs_swept'] = $this->sweepQuestionnaireParagraphOrphans();
    $summary['map_rows_cleared'] = $this->truncateMapTables();
    $summary['users_deleted'] = $this->deleteMigratedUsers();

    $this->logger->notice('CRS migration purge completed: @s', ['@s' => json_encode($summary)]);
    return $summary;
  }

  /**
   * Removes assign_questionnaire paragraphs from company profiles.
   */
  protected function purgeCompanyProfileParagraphs(): int {
    if (!\Drupal::moduleHandler()->moduleExists('profile')) {
      return 0;
    }
    $storage = $this->entityTypeManager->getStorage('profile');
    $ids = $storage->getQuery()
      ->condition('type', 'company')
      ->accessCheck(FALSE)
      ->execute();
    if (!$ids) {
      return 0;
    }
    $count = 0;
    foreach ($storage->loadMultiple($ids) as $profile) {
      if (!$profile->hasField('field_select_questionnaire') || $profile->get('field_select_questionnaire')->isEmpty()) {
        continue;
      }
      $this->deleteParagraphsFromField($profile, 'field_select_questionnaire');
      $profile->set('field_select_questionnaire', []);
      $profile->save();
      $count++;
    }
    return $count;
  }

  /**
   * Deletes all questionnaire nodes after removing nested paragraphs.
   */
  protected function purgeQuestionnaireNodes(): int {
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->condition('type', 'questionnaire')
      ->accessCheck(FALSE)
      ->execute();
    if (!$nids) {
      return 0;
    }
    $nodes = $storage->loadMultiple($nids);
    foreach ($nodes as $node) {
      foreach ($node->getFieldDefinitions() as $def) {
        if ($def->getType() !== 'entity_reference_revisions') {
          continue;
        }
        $name = $def->getName();
        if (!$node->hasField($name) || $node->get($name)->isEmpty()) {
          continue;
        }
        $this->deleteParagraphsFromField($node, $name);
        $node->set($name, []);
      }
      $node->delete();
    }
    return count($nodes);
  }

  /**
   * Deletes leftover paragraph entities used by questionnaire sync.
   */
  protected function sweepQuestionnaireParagraphOrphans(): int {
    $bundles = [
      'options',
      'question',
      'sub_sub_category',
      'sub_category',
      'category',
      'questionnaire',
      'assign_questionnaire',
    ];
    $storage = $this->entityTypeManager->getStorage('paragraph');
    $deleted = 0;
    foreach ($bundles as $bundle) {
      $ids = $storage->getQuery()
        ->condition('type', $bundle)
        ->accessCheck(FALSE)
        ->execute();
      if (!$ids) {
        continue;
      }
      foreach (array_chunk(array_values($ids), 50) as $chunk) {
        $paragraphs = $storage->loadMultiple($chunk);
        foreach ($paragraphs as $paragraph) {
          if ($paragraph instanceof ParagraphInterface) {
            try {
              $this->deleteParagraphTree($paragraph);
              $deleted++;
            }
            catch (\Throwable $e) {
              $this->logger->warning('Paragraph @id delete failed: @m', [
                '@id' => $paragraph->id(),
                '@m' => $e->getMessage(),
              ]);
            }
          }
        }
      }
    }
    return $deleted;
  }

  /**
   * Clears legacy ↔ Drupal map tables (Drupal default connection).
   */
  protected function truncateMapTables(): int {
    $rows = 0;
    $schema = $this->database->schema();
    foreach (['crs_sync_legacy_map', 'crs_sync_content_map'] as $table) {
      if ($schema->tableExists($table)) {
        $rows += (int) $this->database->select($table)->countQuery()->execute()->fetchField();
        $this->database->truncate($table)->execute();
      }
    }
    return $rows;
  }

  /**
   * Deletes users that have company, coach, or employee roles (not uid 1).
   */
  protected function deleteMigratedUsers(): int {
    $role_ids = ['company', 'coach', 'employee'];
    $storage = $this->entityTypeManager->getStorage('user');
    $candidate_ids = $storage->getQuery()
      ->condition('uid', 1, '>')
      ->accessCheck(FALSE)
      ->execute();
    if (!$candidate_ids) {
      return 0;
    }
    $deleted = 0;
    foreach ($storage->loadMultiple($candidate_ids) as $account) {
      $roles = $account->getRoles();
      $match = array_intersect($role_ids, $roles);
      if (!$match) {
        continue;
      }
      if ((int) $account->id() === 1) {
        continue;
      }
      if ($this->currentUser->id() === (int) $account->id()) {
        $this->logger->warning('Skipping deletion of current user @uid.', ['@uid' => $account->id()]);
        continue;
      }
      if (\Drupal::moduleHandler()->moduleExists('profile')) {
        $pstorage = $this->entityTypeManager->getStorage('profile');
        $pids = $pstorage->getQuery()
          ->condition('uid', $account->id())
          ->accessCheck(FALSE)
          ->execute();
        if ($pids) {
          $pstorage->delete($pstorage->loadMultiple($pids));
        }
      }
      $account->delete();
      $deleted++;
    }
    return $deleted;
  }

  /**
   * Deletes top-level paragraphs in a field (recursively).
   */
  protected function deleteParagraphsFromField($entity, string $field_name): void {
    if (!$entity->hasField($field_name)) {
      return;
    }
    $field = $entity->get($field_name);
    if (!$field instanceof EntityReferenceRevisionsFieldItemList || $field->isEmpty()) {
      return;
    }
    foreach ($field as $item) {
      $para = $item->entity;
      if ($para instanceof ParagraphInterface) {
        $this->deleteParagraphTree($para);
      }
    }
  }

  /**
   * Depth-first delete of a paragraph and its referenced paragraphs.
   */
  protected function deleteParagraphTree(ParagraphInterface $paragraph): void {
    foreach ($paragraph->getFields() as $field) {
      if ($field->getFieldDefinition()->getType() !== 'entity_reference_revisions') {
        continue;
      }
      if ($field->isEmpty()) {
        continue;
      }
      $children = [];
      foreach ($field as $item) {
        if ($item->entity instanceof ParagraphInterface) {
          $children[] = $item->entity;
        }
      }
      foreach ($children as $child) {
        $this->deleteParagraphTree($child);
      }
    }
    $paragraph->delete();
  }

}
