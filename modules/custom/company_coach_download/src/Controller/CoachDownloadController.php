<?php

namespace Drupal\company_coach_download\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

class CoachDownloadController extends ControllerBase {

  public function download() {

    $company_id = (int) \Drupal::request()->query->get('company_id');

    $query = \Drupal::entityQuery('profile')
      ->accessCheck(FALSE)
      ->condition('type', 'coach');

    if ($company_id) {
      $query->condition('field_company.target_id', $company_id);
    }

    $ids = $query->execute();

    $profiles = \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->loadMultiple($ids);

    $csv = "company_id,company_name,first_name,last_name,email\n";

    foreach ($profiles as $profile) {

      $user = $profile->getOwner();

      $email = '';
      $first = '';
      $last  = '';

      if ($user) {

        $email = $user->getEmail();

        if ($user->hasField('field_first_name')) {
          $first = $user->get('field_first_name')->value;
        }

        if ($user->hasField('field_last_name')) {
          $last = $user->get('field_last_name')->value;
        }

      }

      $cid = $profile->get('field_company')->target_id ?? '';
      $company_name = '';

      if ($cid) {

        $company_profiles = \Drupal::entityTypeManager()
          ->getStorage('profile')
          ->loadByProperties([
            'type' => 'company',
            'uid' => $cid
          ]);

        if ($company_profiles) {

          $company_profile = reset($company_profiles);

          if ($company_profile->hasField('field_company_name')) {
            $company_name = $company_profile->get('field_company_name')->value;
          }

        }

      }

      $row = [
        $cid,
        $company_name,
        $first,
        $last,
        $email
      ];

      $csv .= implode(',', $row) . "\n";

    }

    $response = new Response($csv);

    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="coach_export.csv"');

    return $response;

  }

}
