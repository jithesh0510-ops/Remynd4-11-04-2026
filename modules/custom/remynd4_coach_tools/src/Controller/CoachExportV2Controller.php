<?php

namespace Drupal\remynd4_coach_tools\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coach export CSV endpoints.
 */
final class CoachExportV2Controller extends ControllerBase {

  /**
   * Sample CSV download (route expects ::sample()).
   */
  public function sample(): Response {
    $headers = [
      'coach_id','first_name','last_name','address1','address2','email','phone_no',
      'see_quest','see_action','status','skills_asse','lagard_to','see_previous_date'
    ];

    $rows = [
      ['1234','John','Doe','Addr line 1','Addr line 2','john@example.com','9999999999','Yes','No','Yes','No','No','No'],
    ];

    return $this->csvResponse($headers, $rows, 'coach_import_sample.csv');
  }

  /**
   * URL: /coach/importcsv-v2/coach-key?company=2584
   * Exports REAL coaches mapped to company via:
   * profile__field_company -> profile ids
   * profile__field_coach   -> coach user ids
   */
  public function coachKey(Request $request): Response {
    $company_uid = (int) $request->query->get('company');
    if ($company_uid <= 0) {
      return new Response("Missing/invalid company parameter.\n", 400);
    }

    $headers = [
      'coach_id',
      'first_name',
      'last_name',
      'address1',
      'address2',
      'email',
      'phone_no',
      'see_quest',
      'see_action',
      'status',
      'skills_asse',
      'lagard_to',
      'see_previous_date',
    ];

    $db = Database::getConnection();

    // 1) Company -> profile ids.
    $profile_ids = $db->select('profile__field_company', 'pco')
      ->fields('pco', ['entity_id'])
      ->condition('pco.field_company_target_id', $company_uid)
      ->execute()
      ->fetchCol();

    $profile_ids = array_values(array_unique(array_map('intval', $profile_ids)));

    if (empty($profile_ids)) {
      return $this->csvResponse($headers, [
        ['NO_COMPANY', 'No profiles mapped to this company'],
      ], "coach_key_company_{$company_uid}.csv");
    }

    // 2) Profiles -> coach uids.
    $coach_uids = $db->select('profile__field_coach', 'pc')
      ->fields('pc', ['field_coach_target_id'])
      ->condition('pc.entity_id', $profile_ids, 'IN')
      ->isNotNull('pc.field_coach_target_id')
      ->distinct()
      ->execute()
      ->fetchCol();

    $coach_uids = array_values(array_filter(array_unique(array_map('intval', $coach_uids))));

    if (empty($coach_uids)) {
      return $this->csvResponse($headers, [
        ['NO_COACH', 'No coaches mapped to these company profiles: ' . implode(',', $profile_ids)],
      ], "coach_key_company_{$company_uid}.csv");
    }

    // 3) Load real users.
    $users = User::loadMultiple($coach_uids);

    $rows = [];
    foreach ($coach_uids as $uid) {
      if (empty($users[$uid])) {
        continue;
      }
      /** @var \Drupal\user\Entity\User $u */
      $u = $users[$uid];

      // If these field names differ in your site, they will be blank (export still works).
      $first = $this->fieldValue($u, 'field_first_name');
      $last  = $this->fieldValue($u, 'field_last_name');
      if ($first === '' && $last === '') {
        $first = (string) $u->getAccountName();
      }

      $rows[] = [
        (string) $u->id(),
        $first,
        $last,
        $this->fieldValue($u, 'field_address1'),
        $this->fieldValue($u, 'field_address2'),
        (string) $u->getEmail(),
        $this->fieldValue($u, 'field_phone_no'),
        $this->yesNo($this->fieldValue($u, 'field_see_quest')),
        $this->yesNo($this->fieldValue($u, 'field_see_action')),
        $this->fieldValue($u, 'field_status'),
        $this->yesNo($this->fieldValue($u, 'field_skills_asse')),
        $this->fieldValue($u, 'field_lagard_to'),
        $this->fieldValue($u, 'field_see_previous_date'),
      ];
    }

    if (empty($rows)) {
      return $this->csvResponse($headers, [
        ['NO_COACH', 'Coach users not found/loaded for uids: ' . implode(',', $coach_uids)],
      ], "coach_key_company_{$company_uid}.csv");
    }

    return $this->csvResponse($headers, $rows, "coach_key_company_{$company_uid}.csv");
  }

  private function fieldValue(User $u, string $field): string {
    if (!$u->hasField($field) || $u->get($field)->isEmpty()) {
      return '';
    }
    $item = $u->get($field)->first();
    if (!$item) {
      return '';
    }
    if (isset($item->value)) {
      return (string) $item->value;
    }
    if (isset($item->target_id)) {
      return (string) $item->target_id;
    }
    $val = $item->getValue();
    return is_array($val) && isset($val[0]) ? (string) $val[0] : '';
  }

  private function yesNo(string $v): string {
    $v = trim($v);
    if ($v === '' || $v === '0') return 'No';
    if ($v === '1') return 'Yes';
    return $v;
  }

  private function csvResponse(array $headers, array $rows, string $filename): Response {
    $out = fopen('php://temp', 'w+');
    fputcsv($out, $headers);
    foreach ($rows as $r) {
      fputcsv($out, $r);
    }
    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);

    $res = new Response($csv);
    $res->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $res->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    $res->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    $res->headers->set('Pragma', 'no-cache');
    return $res;
  }

}
