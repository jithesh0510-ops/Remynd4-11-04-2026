<?php

namespace Drupal\company_import_history;

use Drupal\user\Entity\User;

class CompanyHelper {

  public static function getCompanyOptions(): array {
    $options = ['' => '- Select -'];

    $uids = \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('roles', 'company')
      ->sort('uid', 'ASC')
      ->execute();

    if (empty($uids)) {
      return $options;
    }

    $users = User::loadMultiple($uids);
    foreach ($users as $user) {
      $label = self::getCompanyDisplayName($user);
      $options[$user->id()] = $label;
    }

    asort($options);
    return $options;
  }

  public static function getCompanyDisplayName(User $u): string {
    $profiles = \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->loadByProperties(['uid' => $u->id()]);

    $preferred_fields = [
      'field_company_name',
      'field_business_name',
      'field_organization',
      'field_org_name',
      'field_org',
      'field_company',
      'field_name',
      'field_title',
    ];

    foreach ($profiles as $p) {
      if (method_exists($p, 'label')) {
        $lbl = trim((string) $p->label());
        if ($lbl !== '' && strpos($lbl, '@') === FALSE && stripos($lbl, 'company #') === FALSE) {
          return $lbl;
        }
      }

      foreach ($preferred_fields as $fn) {
        if (!$p->hasField($fn) || $p->get($fn)->isEmpty()) {
          continue;
        }

        $item = $p->get($fn);

        if (method_exists($item, 'referencedEntities')) {
          $ents = $item->referencedEntities();
          if (!empty($ents) && method_exists($ents[0], 'label')) {
            $lbl = trim((string) $ents[0]->label());
            if ($lbl !== '' && strpos($lbl, '@') === FALSE && stripos($lbl, 'company #') === FALSE) {
              return $lbl;
            }
          }
        }

        $val = trim((string) ($item->value ?? ''));
        if ($val !== '' && strpos($val, '@') === FALSE && stripos($val, 'company #') === FALSE) {
          return $val;
        }
      }
    }

    $fallback = trim((string) $u->getDisplayName());
    return $fallback !== '' ? $fallback : ('Company #' . $u->id());
  }
}
