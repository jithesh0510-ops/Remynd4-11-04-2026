<?php

namespace Drupal\crs_sync\Batch;

class SyncUsersBatch {
  public static function run(string $type, int $offset, int $limit, array &$context) {
    /** @var \Drupal\crs_sync\Sync\SyncManager $manager */
    $manager = \Drupal::service('crs_sync.sync_manager');
    $res = $manager->syncChunk($type, $offset, $limit);

    $context['results'][$type]['processed'] = ($context['results'][$type]['processed'] ?? 0) + $res['processed'];
    $context['message'] = t('Synced @n @t (offset @o).', ['@n' => $res['processed'], '@t' => $type, '@o' => $offset]);
  }
}
