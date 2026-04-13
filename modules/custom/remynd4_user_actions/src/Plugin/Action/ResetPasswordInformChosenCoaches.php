<?php

namespace Drupal\remynd4_user_actions\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Sends password reset email to selected users.
 *
 * @Action(
 *   id = "remynd4_reset_password_inform_coaches",
 *   label = @Translation("Reset Password & Inform chosen coaches"),
 *   type = "user"
 * )
 */
final class ResetPasswordInformChosenCoaches extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL): void {
    if (!$entity instanceof UserInterface) {
      return;
    }

    // Only for active users with an email.
    if (!$entity->isActive() || !$entity->getEmail()) {
      return;
    }

    // IMPORTANT: global function (leading backslash).
    \_user_mail_notify('password_reset', $entity);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowedIfHasPermission($account, 'administer users');
    return $return_as_object ? $result : $result->isAllowed();
  }

}
