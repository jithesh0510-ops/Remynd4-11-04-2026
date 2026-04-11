<?php

namespace Drupal\coach_reporting_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Company management: export CSV.
 */
class CompanyManageController extends ControllerBase {

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Export companies to CSV.
   */
  public function exportCsv(): Response {
    $user_storage = $this->entityTypeManager->getStorage('user');
    $profile_storage = $this->entityTypeManager->getStorage('profile');

    $uids = $user_storage->getQuery()
      ->condition('status', 1)
      ->condition('roles', 'company')
      ->accessCheck(TRUE)
      ->sort('name')
      ->execute();

    $response = new StreamedResponse(function () use ($user_storage, $profile_storage, $uids) {
      $handle = fopen('php://output', 'w');
      if (!$handle) {
        return;
      }
      // UTF-8 BOM for Excel
      fwrite($handle, "\xEF\xBB\xBF");
      fputcsv($handle, ['uid', 'name', 'mail', 'status', 'program_nids'], ',');

      $users = $user_storage->loadMultiple($uids);
      foreach ($users as $user) {
        $program_nids = [];
        $profiles = $profile_storage->loadByUser($user, 'company', TRUE);
        if (is_array($profiles)) {
          $profiles = $profiles ? [reset($profiles)] : [];
        } else {
          $profiles = $profiles ? [$profiles] : [];
        }
        foreach ($profiles as $profile) {
          if ($profile && $profile->hasField('field_select_questionnaire') && !$profile->get('field_select_questionnaire')->isEmpty()) {
            foreach ($profile->get('field_select_questionnaire')->referencedEntities() as $para) {
              if ($para->hasField('field_questionnaire') && !$para->get('field_questionnaire')->isEmpty()) {
                $node = $para->get('field_questionnaire')->entity;
                if ($node && $node->bundle() === 'questionnaire') {
                  $program_nids[] = $node->id();
                }
              }
            }
          }
        }
        fputcsv($handle, [
          $user->id(),
          $user->getDisplayName(),
          $user->getEmail(),
          $user->isActive() ? '1' : '0',
          implode(';', $program_nids),
        ], ',');
      }
      fclose($handle);
    });

    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="companies-' . date('Y-m-d') . '.csv"');
    return $response;
  }
}
