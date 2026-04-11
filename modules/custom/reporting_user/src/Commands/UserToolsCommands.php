<?php

namespace Drupal\reporting_user\Commands;

use Drush\Commands\DrushCommands;
use Drupal\user\Entity\User;

/**
 * Drush commands for user reporting.
 */
class UserToolsCommands extends DrushCommands {

  /**
   * Update user fields in bulk.
   *
   * @command reporting_user:update-users
   * @aliases uu
   * @description Updates empty user fields like first name, last name, and phone.
   */
  public function updateUsers() {
    $uids = \Drupal::entityQuery('user')->accessCheck(FALSE)->execute();
    $users = User::loadMultiple($uids);
    $updated = 0;

    foreach ($users as $user) {
      $changed = FALSE;
      $uid = $user->id();

      if ($user->get('field_first_name')->isEmpty()) {
        $user->set('field_first_name', 'John');
        $changed = TRUE;
      }

      if ($user->get('field_last_name')->isEmpty()) {
        $user->set('field_last_name', 'Doe');
        $changed = TRUE;
      }

      if ($user->get('field_phone_no')->isEmpty()) {
        $user->set('field_phone_no', '0000000000');
        $changed = TRUE;
      }

      if ($user->get('field_website')->isEmpty()) {
        $user->set('field_website', ['uri' => 'https://example.com']);
        $changed = TRUE;
      }

      if ($changed) {
        $user->save();
        $updated++;
        $this->output()->writeln("✅ Updated user: $uid");
      }
    }

    $this->output()->writeln("🎉 Done. Updated $updated user(s).");
  }

}
