<?php

namespace Drupal\coach_csv_import\Controller;

use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Controller\ControllerBase;

class CoachCsvController extends ControllerBase {

  public function downloadSample(): Response {
    $csv = "mail,first_name,last_name,name\n";
    $csv .= "coach1@example.com,John,Doe,\n";
    $csv .= "coach2@example.com,,,Jane Doe\n";
    return $this->csvResponse($csv, 'coach_sample.csv');
  }

  public function downloadKey(): Response {
    // Simple "key" export template. If you need exact fields from your DB later,
    // we can change this to query users + company_id.
    $csv = "uid,mail,company_id\n";
    $csv .= "123,coach@example.com,456\n";
    return $this->csvResponse($csv, 'coach_key.csv');
  }

  private function csvResponse(string $csv, string $filename): Response {
    $response = new Response($csv);
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    return $response;
  }

}
