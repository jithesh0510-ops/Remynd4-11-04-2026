<?php

namespace Drupal\coach_csv_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

class CoachCsvController extends ControllerBase {

  public function downloadSample(): Response {
    $csv  = "email,first_name,last_name\n";
    $csv .= "coach1@example.com,John,Doe\n";
    $csv .= "coach2@example.com,Jane,Doe\n";
    return $this->csvResponse($csv, 'coach_sample.csv');
  }

  public function downloadKey(): Response {
    $db = \Drupal::database();

    // Export existing coaches with their company id (if any).
    $rows = $db->query("
      SELECT u.uid, u.mail AS email, fc.field_company_target_id AS company_id
      FROM {users_field_data} u
      INNER JOIN {user__roles} ur ON ur.entity_id = u.uid AND ur.roles_target_id = :role
      LEFT JOIN {profile} pr ON pr.uid = u.uid
      LEFT JOIN {profile__field_company} fc ON fc.entity_id = pr.profile_id
      WHERE u.status = 1
      ORDER BY u.uid ASC
    ", [':role' => 'coach'])->fetchAll();

    $out = "uid,email,company_id\n";
    foreach ($rows as $r) {
      $out .= (int) $r->uid . "," . $this->esc($r->email) . "," . (int) ($r->company_id ?? 0) . "\n";
    }

    return $this->csvResponse($out, 'coach_key.csv');
  }

  private function csvResponse(string $csv, string $filename): Response {
    $response = new Response($csv);
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    return $response;
  }

  private function esc($v): string {
    $v = (string) $v;
    $v = str_replace('"', '""', $v);
    return '"' . $v . '"';
  }

}
