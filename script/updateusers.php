<?php

use Drupal\user\Entity\User;

// STEP 1: Load all user IDs (you can filter if needed)
$uids = \Drupal::entityQuery('user')
  ->accessCheck(FALSE)
  ->execute();

// STEP 2: Load all user entities
$users = User::loadMultiple($uids);

// STEP 3: Loop through each user and update fields if empty
foreach ($users as $user) {
  $uid = $user->id();
  $changed = FALSE;

  // Update First Name
  if ($user->get('field_first_name')->isEmpty()) {
    $user->set('field_first_name', 'John');
    $changed = TRUE;
  }

  // Update Last Name
  if ($user->get('field_last_name')->isEmpty()) {
    $user->set('field_last_name', 'Doe');
    $changed = TRUE;
  }

  // Update Phone No
  if ($user->get('field_phone_no')->isEmpty()) {
    $user->set('field_phone_no', '0000000000');
    $changed = TRUE;
  }

  // Update Website (optional)
  if ($user->get('field_website')->isEmpty()) {
    $user->set('field_website', ['uri' => 'https://example.com']);
    $changed = TRUE;
  }

  if ($changed) {
    $user->save();
    \Drupal::logger('user_fix')->notice('Updated user @uid.', ['@uid' => $uid]);
  }
}

echo "Done\n";
