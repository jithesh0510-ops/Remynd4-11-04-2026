<?php

namespace Drupal\remynd4_user_actions\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

final class CoachImportController extends ControllerBase {

  public function downloadSample(): Response {
    $csv = "email,first_name,last_name,company\njohn@example.com,John,Doe,1\n";
    $response = new Response($csv);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="coach_sample.csv"');
    return $response;
  }

  public function downloadKey(): Response {
    $csv = "coach_id,coach_key\n1,ABC123\n";
    $response = new Response($csv);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="coach_keys.csv"');
    return $response;
  }
}
