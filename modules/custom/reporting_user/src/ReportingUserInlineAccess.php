<?php

namespace Drupal\reporting_user;

use Drupal\Core\Session\AccountInterface;
use Drupal\profile\Entity\ProfileInterface;

/**
 * Helpers for who may change employee data via inline JSON endpoints.
 */
class ReportingUserInlineAccess {

  /**
   * Whether the account may update this employee profile from inline UI.
   *
   * Mirrors typical product rules: admins, company for their employees,
   * assigned coach(es). Optional granular permissions override when set.
   *
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   Employee profile.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Acting user.
   *
   * @return bool
   *   TRUE if update is allowed.
   */
  public static function accountMayEditEmployeeProfile(ProfileInterface $profile, AccountInterface $account) {
    if ($account->isAnonymous() || $profile->bundle() !== 'employee') {
      return FALSE;
    }

    foreach (['edit company users', 'edit coach users', 'edit employee users'] as $permission) {
      if ($account->hasPermission($permission)) {
        return TRUE;
      }
    }

    if ($account->hasPermission('administer users')) {
      return TRUE;
    }

    $roles = $account->getRoles();
    if (in_array('administrator', $roles, TRUE)) {
      return TRUE;
    }

    $current_uid = (int) $account->id();

    if (in_array('company', $roles, TRUE)) {
      if ($profile->hasField('field_company') && !$profile->get('field_company')->isEmpty()) {
        $company = $profile->get('field_company')->entity;
        if ($company && (int) $company->id() === $current_uid) {
          return TRUE;
        }
      }
    }

    if (in_array('coach', $roles, TRUE)) {
      if ($profile->hasField('field_coach')) {
        foreach ($profile->get('field_coach') as $item) {
          if ((int) $item->target_id === $current_uid) {
            return TRUE;
          }
        }
      }
    }

    return (int) $profile->getOwnerId() === $current_uid;
  }

}
