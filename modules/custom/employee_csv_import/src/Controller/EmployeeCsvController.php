<?php

namespace Drupal\employee_csv_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

class EmployeeCsvController extends ControllerBase {

  public function downloadSample(): Response {
    $csv  = "email,first_name,last_name,name\n";
    $csv .= "emp1@example.com,John,Doe,\n";
    $csv .= "emp2@example.com,,,'Jane Doe'\n";
    return $this->csvResponse($csv, 'employee_sample.csv');
  }

  public function downloadKey(): Response {
    $csv  = "uid,email,company_id\n";
    $csv .= "123,emp@example.com,456\n";
    return $this->csvResponse($csv, 'employee_key.csv');
  }

  private function csvResponse(string $csv, string $filename): Response {
    $response = new Response($csv);
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    return $response;
  }
}
