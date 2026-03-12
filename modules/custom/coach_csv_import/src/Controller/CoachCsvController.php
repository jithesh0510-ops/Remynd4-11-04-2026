<?php

namespace Drupal\coach_csv_import\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Drupal\profile\Entity\Profile;
use Drupal\user\Entity\User;

class CoachCsvController {

  /**
   * Download Coach Key CSV filtered by company
   */
  public function downloadKey(Request $request) {

    $company_id = $request->query->get('company');

    $rows = [];

    // CSV Header
    $rows[] = ['email','first_name','last_name','name'];

    $query = \Drupal::entityQuery('profile')
      ->condition('type', 'coach')
      ->accessCheck(FALSE);

    // Apply company filter
    if (!empty($company_id)) {
      $query->condition('field_company_target_id.target_id', $company_id);
    }

    $profile_ids = $query->execute();

    if (!empty($profile_ids)) {

      $profiles = Profile::loadMultiple($profile_ids);

      foreach ($profiles as $profile) {

        $user = User::load($profile->getOwnerId());

        if ($user) {

          $email = $user->getEmail();
          $name  = $user->getDisplayName();

          $first = '';
          $last  = '';

          if ($profile->hasField('field_first_name')) {
            $first = $profile->get('field_first_name')->value;
          }

          if ($profile->hasField('field_last_name')) {
            $last = $profile->get('field_last_name')->value;
          }

          $rows[] = [$email,$first,$last,$name];
        }
      }
    }

    // Create CSV
    $handle = fopen('php://temp','r+');

    foreach ($rows as $row) {
      fputcsv($handle,$row);
    }

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    $response = new Response($csv);

    $response->headers->set('Content-Type','text/csv');
    $response->headers->set('Content-Disposition','attachment; filename="coach_key.csv"');

    return $response;
  }

  /**
   * Download Sample CSV
   */
  public function downloadSample() {

    $rows = [
      ['email','first_name','last_name','name'],
      ['coach@example.com','John','Doe','John Doe']
    ];

    $handle = fopen('php://temp','r+');

    foreach ($rows as $row) {
      fputcsv($handle,$row);
    }

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    $response = new Response($csv);

    $response->headers->set('Content-Type','text/csv');
    $response->headers->set('Content-Disposition','attachment; filename="coach_sample.csv"');

    return $response;
  }

}