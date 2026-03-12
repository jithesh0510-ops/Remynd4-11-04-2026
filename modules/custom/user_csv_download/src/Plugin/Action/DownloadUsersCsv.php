<?php

namespace Drupal\user_csv_download\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download selected users CSV.
 *
 * @Action(
 *   id = "download_selected_users_csv",
 *   label = @Translation("Download selected users CSV"),
 *   type = "user"
 * )
 */
class DownloadUsersCsv extends ActionBase {

  public function execute($entity = NULL) {}

  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return TRUE;
  }

  public function executeMultiple(array $entities) {

    $response = new StreamedResponse(function () use ($entities) {

      $handle = fopen('php://output', 'w');

      fputcsv($handle, ['UID','Name','Email']);

      foreach ($entities as $user) {

        if ($user instanceof User) {

          fputcsv($handle, [
            $user->id(),
            $user->getDisplayName(),
            $user->getEmail(),
          ]);

        }

      }

      fclose($handle);

    });

    $response->headers->set('Content-Type','text/csv');
    $response->headers->set('Content-Disposition','attachment; filename="selected-users.csv"');

    $response->send();
    exit;
  }

}