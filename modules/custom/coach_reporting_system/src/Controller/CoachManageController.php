<?php

namespace Drupal\coach_reporting_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Coach management: export CSV.
 */
class CoachManageController extends ControllerBase {

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container): self {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Export coaches to CSV.
   */
  public function exportCsv(): Response {
    $user_storage = $this->entityTypeManager->getStorage('user');
    $profile_storage = $this->entityTypeManager->getStorage('profile');

    $uids = $user_storage->getQuery()
      ->condition('status', 1)
      ->condition('roles', 'coach')
      ->accessCheck(TRUE)
      ->sort('name')
      ->execute();

    $response = new StreamedResponse(function () use ($user_storage, $profile_storage, $uids) {
      $handle = fopen('php://output', 'w');
      if (!$handle) {
        return;
      }
      fwrite($handle, "\xEF\xBB\xBF");
      fputcsv($handle, ['uid', 'name', 'mail', 'status', 'company_uids'], ',');

      foreach ($user_storage->loadMultiple($uids) as $user) {
        $company_uids = [];
        $profiles = $profile_storage->loadByUser($user, 'coach', TRUE);
        if (is_array($profiles)) {
          $profiles = $profiles ? [reset($profiles)] : [];
        } else {
          $profiles = $profiles ? [$profiles] : [];
        }
        foreach ($profiles as $profile) {
          if ($profile && $profile->hasField('field_company') && !$profile->get('field_company')->isEmpty()) {
            foreach ($profile->get('field_company')->referencedEntities() as $company) {
              $company_uids[] = $company->id();
            }
          }
        }
        fputcsv($handle, [
          $user->id(),
          $user->getDisplayName(),
          $user->getEmail(),
          $user->isActive() ? '1' : '0',
          implode(';', $company_uids),
        ], ',');
      }
      fclose($handle);
    });

    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="coaches-' . date('Y-m-d') . '.csv"');
    return $response;
  }
}
