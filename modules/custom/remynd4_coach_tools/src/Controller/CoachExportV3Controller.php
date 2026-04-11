<?php

namespace Drupal\remynd4_coach_tools\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\user\Entity\User;

final class CoachExportV3Controller extends ControllerBase {

  public function coachKey(Request $request): Response {
    \Drupal::service('page_cache_kill_switch')->trigger();

    $company_id = (int) $request->query->get('company');
    if ($company_id <= 0) {
      return new Response("Missing/invalid company parameter.\n", 400);
    }

    $db = \Drupal::database();

    // DISTINCT coach uids linked via profiles of this company.
    $coach_uids = $db->select('profile__field_company', 'pco')
      ->join('profile__field_coach', 'pc', 'pc.entity_id = pco.entity_id')
      ->condition('pco.field_company_target_id', $company_id)
      ->isNotNull('pc.field_coach_target_id')
      ->distinct()
      ->fields('pc', ['field_coach_target_id'])
      ->execute()
      ->fetchCol();

    $headers = [
      'coach_id','first_name','last_name','address1','address2','email','phone_no',
      'see_quest','see_action','status','skills_asse','lagard_to','see_previous_date',
    ];

    $out = fopen('php://temp', 'w+');
    fputcsv($out, $headers);

    if (empty($coach_uids)) {
      $profile_ids = $db->select('profile__field_company', 'pco')
        ->condition('pco.field_company_target_id', $company_id)
        ->fields('pco', ['entity_id'])
        ->distinct()
        ->execute()
        ->fetchCol();

      fputcsv($out, ['NO_COACH', 'No coaches mapped to this company profiles: ' . implode(',', $profile_ids)]);
    }
    else {
      foreach ($coach_uids as $uid) {
        $uid = (int) $uid;
        $u = User::load($uid);
        if (!$u) { continue; }

        // Keep these fields as in your existing system.
        $first = $u->get('field_first_name')->value ?? ($u->get('first_name')->value ?? '');
        $last  = $u->get('field_last_name')->value ?? ($u->get('last_name')->value ?? '');
        $addr1 = $u->get('field_address1')->value ?? '';
        $addr2 = $u->get('field_address2')->value ?? '';
        $email = $u->getEmail() ?? '';
        $phone = $u->get('field_phone_no')->value ?? ($u->get('phone_no')->value ?? '');

        $see_q = $u->get('field_see_quest')->value ?? '';
        $see_a = $u->get('field_see_action')->value ?? '';
        $stat  = $u->get('field_status')->value ?? '';
        $skills= $u->get('field_skills_asse')->value ?? '';
        $lag   = $u->get('field_lagard_to')->value ?? '';
        $prev  = $u->get('field_see_previous_date')->value ?? '';

        fputcsv($out, [$uid,$first,$last,$addr1,$addr2,$email,$phone,$see_q,$see_a,$stat,$skills,$lag,$prev]);
      }
    }

    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);

    $res = new Response($csv);
    $res->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $res->headers->set('Content-Disposition', 'attachment; filename="coach_key_company_' . $company_id . '.csv"');
    $res->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    $res->headers->set('Pragma', 'no-cache');
    $res->headers->set('Expires', '0');
    return $res;
  }

}
