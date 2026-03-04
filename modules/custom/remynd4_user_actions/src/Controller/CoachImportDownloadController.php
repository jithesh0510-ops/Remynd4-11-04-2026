<?php

namespace Drupal\remynd4_user_actions\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CoachImportDownloadController extends ControllerBase {

  /**
   * Download CSV sample for coach import.
   */
  public function sample(): Response {
    $out = fopen('php://temp', 'w+');
    $headers = [
      'coach_id','first_name','last_name','address1','address2','email','phone_no',
      'see_quest','see_action','status','skills_asse','lagard_to','see_previous_date',
    ];
    fputcsv($out, $headers);
    fputcsv($out, [0,'John','Doe','Addr line 1','Addr line 2','john@example.com','9999999999','Yes','No','Yes','No','No','No']);
    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);

    $res = new Response($csv);
    $res->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $res->headers->set('Content-Disposition', 'attachment; filename="coach_import_sample.csv"');
    $res->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    $res->headers->set('Pragma', 'no-cache');
    return $res;
  }

  /**
   * URL: /coach/importcsv/coach-key?company=2584
   * Downloads coaches linked to the company's profile(s).
   */
  public function coachKey(Request $request): Response {
    $company_uid = (int) $request->query->get('company');
    if ($company_uid <= 0) {
      return new Response("Missing/invalid company parameter.\n", 400);
    }

    // 1) Company uid -> profile ids (profile__field_company stores company user ref).
    $profile_ids = $this->getCompanyProfileIdsViaProfileField($company_uid);

    // 2) profile ids -> coach uids (profile__field_coach stores coach user ref).
    $coach_uids = $this->getCoachUidsByCompanyProfilesViaProfileField($profile_ids);

    // 3) Build CSV.
    $out = fopen('php://temp', 'w+');
    $headers = [
      'coach_id','first_name','last_name','address1','address2','email','phone_no',
      'see_quest','see_action','status','skills_asse','lagard_to','see_previous_date',
    ];
    fputcsv($out, $headers);

    if (empty($coach_uids)) {
      // Helpful message row so it never looks like "blank file".
      fputcsv($out, ['NO_COACHES', 'No coaches mapped to this company profiles: ' . implode(',', $profile_ids)]);
    }
    else {
      $users = \Drupal\user\Entity\User::loadMultiple($coach_uids);

      foreach ($coach_uids as $uid) {
        $u = $users[$uid] ?? NULL;
        if (!$u) {
          continue;
        }

        // These field names may differ in your site. Keep safe defaults.
        $first = $u->hasField('field_first_name') ? (string) $u->get('field_first_name')->value : '';
        $last  = $u->hasField('field_last_name')  ? (string) $u->get('field_last_name')->value  : '';
        $email = (string) $u->getEmail();

        $phone = $u->hasField('field_phone_no') ? (string) $u->get('field_phone_no')->value : '';

        $see_quest = $u->hasField('field_see_quest') ? ($u->get('field_see_quest')->value ? 'Yes' : 'No') : 'No';
        $see_action = $u->hasField('field_see_action') ? ($u->get('field_see_action')->value ? 'Yes' : 'No') : 'No';

        // Put blanks for unknown columns rather than crashing.
        fputcsv($out, [
          $uid,
          $first,
          $last,
          '', // address1
          '', // address2
          $email,
          $phone,
          $see_quest,
          $see_action,
          'Yes', // status default
          'No',  // skills_asse default
          'No',  // lagard_to default
          'No',  // see_previous_date default
        ]);
      }
    }

    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);

    $res = new Response($csv);
    $res->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $res->headers->set('Content-Disposition', 'attachment; filename="coach_key_company_' . $company_uid . '.csv"');
    $res->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    $res->headers->set('Pragma', 'no-cache');
    return $res;
  }

  private function getCompanyProfileIdsViaProfileField(int $company_uid): array {
    $db = \Drupal::database();
    $ids = $db->select('profile__field_company', 'pc')
      ->fields('pc', ['entity_id'])
      ->condition('pc.field_company_target_id', $company_uid)
      ->distinct()
      ->execute()
      ->fetchCol();

    return array_values(array_unique(array_map('intval', $ids)));
  }

  private function getCoachUidsByCompanyProfilesViaProfileField(array $profile_ids): array {
    if (empty($profile_ids)) {
      return [];
    }

    $db = \Drupal::database();

    // Get coach uids from profile->field_coach reference.
    $uids = $db->select('profile__field_coach', 'p')
      ->fields('p', ['field_coach_target_id'])
      ->condition('p.entity_id', $profile_ids, 'IN')
      ->condition('p.field_coach_target_id', 0, '>')
      ->distinct()
      ->execute()
      ->fetchCol();

    $uids = array_values(array_unique(array_map('intval', $uids)));
    if (empty($uids)) {
      return [];
    }

    // Optional: keep only users that actually have role 'coach'.
    $coach_uids = $db->select('user__roles', 'r')
      ->fields('r', ['entity_id'])
      ->condition('r.entity_id', $uids, 'IN')
      ->condition('r.roles_target_id', 'coach')
      ->distinct()
      ->execute()
      ->fetchCol();

    return array_values(array_unique(array_map('intval', $coach_uids)));
  }

}
