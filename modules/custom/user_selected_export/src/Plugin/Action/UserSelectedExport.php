<?php

namespace Drupal\user_selected_export\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Action(
 *   id = "user_selected_export_action",
 *   label = @Translation("Export selected users to CSV"),
 *   type = "user"
 * )
 */
class UserSelectedExport extends ActionBase {

  public function execute($entity = NULL) {}

  public function executeMultiple(array $entities) {

    $output = fopen('php://temp', 'r+');

    fputcsv($output, [
      'UID',
      'First Name',
      'Last Name',
      'Email'
    ]);

    foreach ($entities as $user) {
      fputcsv($output, [
        $user->id(),
        $user->get('field_first_name')->value ?? '',
        $user->get('field_last_name')->value ?? '',
        $user->getEmail(),
      ]);
    }

    rewind($output);
    $csv = stream_get_contents($output);

    return new Response(
      $csv,
      200,
      [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => 'attachment; filename="selected_users.csv"',
      ]
    );
  }

  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $account->hasPermission('administer users');
  }
}
