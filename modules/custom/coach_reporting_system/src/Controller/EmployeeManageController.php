<?php

namespace Drupal\coach_reporting_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Employee management: export CSV.
 */
class EmployeeManageController extends ControllerBase {

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container): self {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Export employees to CSV.
   */
  public function exportCsv(): Response {
    $user_storage = $this->entityTypeManager->getStorage('user');
    $profile_storage = $this->entityTypeManager->getStorage('profile');

    $uids = $user_storage->getQuery()
      ->condition('status', 1)
      ->condition('roles', 'employee')
      ->accessCheck(TRUE)
      ->sort('name')
      ->execute();

    $response = new StreamedResponse(function () use ($user_storage, $profile_storage, $uids) {
      $handle = fopen('php://output', 'w');
      if (!$handle) {
        return;
      }
      fwrite($handle, "\xEF\xBB\xBF");
      fputcsv($handle, ['uid', 'name', 'mail', 'status', 'company_uid', 'coach_uid', 'program_nid'], ',');

      foreach ($user_storage->loadMultiple($uids) as $user) {
        $company_uid = '';
        $coach_uid = '';
        $program_nid = '';
        $profiles = $profile_storage->loadByUser($user, 'employee', TRUE);
        if (is_array($profiles)) {
          $profiles = $profiles ? [reset($profiles)] : [];
        } else {
          $profiles = $profiles ? [$profiles] : [];
        }
        foreach ($profiles as $profile) {
          if (!$profile) {
            continue;
          }
          if ($profile->hasField('field_company') && !$profile->get('field_company')->isEmpty()) {
            $company_uid = (string) $profile->get('field_company')->target_id;
          }
          if ($profile->hasField('field_coach') && !$profile->get('field_coach')->isEmpty()) {
            $coach_uid = (string) $profile->get('field_coach')->target_id;
          }
          if ($profile->hasField('field_program') && !$profile->get('field_program')->isEmpty()) {
            $program_nid = (string) $profile->get('field_program')->target_id;
          }
        }
        fputcsv($handle, [
          $user->id(),
          $user->getDisplayName(),
          $user->getEmail(),
          $user->isActive() ? '1' : '0',
          $company_uid,
          $coach_uid,
          $program_nid,
        ], ',');
      }
      fclose($handle);
    });

    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="employees-' . date('Y-m-d') . '.csv"');
    return $response;
  }
}
