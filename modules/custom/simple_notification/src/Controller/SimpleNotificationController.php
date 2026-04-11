<?php

namespace Drupal\simple_notification\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Drupal\user\Entity\User;

class SimpleNotificationController extends ControllerBase {

  public function userNotifications($user) {
    $account = User::load($user);
    if (!$account || $account->id() != $this->currentUser()->id()) {
      $this->messenger()->addError($this->t('Access denied.'));
      return $this->redirect('<front>');
    }

    $query = \Drupal::database()->select('simple_notification', 'n')
      ->fields('n', ['id', 'message', 'link', 'status', 'created'])
      ->condition('uid', $user)
      ->orderBy('created', 'DESC');
    $result = $query->execute()->fetchAll();

    if (!$result) {
      return ['#markup' => $this->t('No notifications found.')];
    }

    $form = [
      '#type' => 'form',
      '#action' => Url::fromRoute('simple_notification.bulk_delete', ['user' => $user])->toString(),
      '#method' => 'post',
    ];

    foreach ($result as $row) {
      $text = !empty($row->link)
        ? Link::fromTextAndUrl($row->message, Url::fromUri($row->link))->toString()
        : $row->message;

      $toggle_link = Link::fromTextAndUrl(
        $row->status ? 'Mark Unread' : 'Mark Read',
        Url::fromRoute('simple_notification.mark_read', ['id' => $row->id])
      )->toString();

      $delete_link = Link::fromTextAndUrl(
        'Delete',
        Url::fromRoute('simple_notification.delete', ['id' => $row->id])
      )->toString();

      $form['notifications'][$row->id] = [
        '#type' => 'checkbox',
        '#title' => $this->t('@msg (Created: @date) - @toggle - @delete', [
          '@msg' => $text,
          '@date' => date('Y-m-d H:i', $row->created),
          '@toggle' => $toggle_link,
          '@delete' => $delete_link,
        ]),
      ];
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete Selected'),
    ];

    return $form;
  }

  public function toggleReadStatus($id) {
    $uid = $this->currentUser()->id();
    $row = \Drupal::database()->select('simple_notification', 'n')
      ->fields('n', ['uid', 'status'])
      ->condition('id', $id)
      ->execute()
      ->fetchObject();

    if ($row && $row->uid == $uid) {
      $new_status = $row->status ? 0 : 1;
      \Drupal::database()->update('simple_notification')
        ->fields(['status' => $new_status])
        ->condition('id', $id)
        ->execute();

      $this->messenger()->addStatus($new_status ? $this->t('Marked as read.') : $this->t('Marked as unread.'));
    } else {
      $this->messenger()->addError($this->t('Notification not found or access denied.'));
    }

    return $this->redirect('simple_notification.user_list', ['user' => $uid]);
  }

  public function deleteNotification($id) {
    $uid = $this->currentUser()->id();
    $row = \Drupal::database()->select('simple_notification', 'n')
      ->fields('n', ['uid'])
      ->condition('id', $id)
      ->execute()
      ->fetchObject();

    if ($row && $row->uid == $uid) {
      \Drupal::database()->delete('simple_notification')
        ->condition('id', $id)
        ->execute();
      $this->messenger()->addStatus($this->t('Notification deleted.'));
    } else {
      $this->messenger()->addError($this->t('Notification not found or access denied.'));
    }

    return $this->redirect('simple_notification.user_list', ['user' => $uid]);
  }

  public function bulkDelete(Request $request, $user) {
    $selected_ids = $request->request->get('notifications');
    if (is_array($selected_ids) && !empty($selected_ids)) {
      $uid = $this->currentUser()->id();
      \Drupal::database()->delete('simple_notification')
        ->condition('id', array_keys($selected_ids), 'IN')
        ->condition('uid', $uid)
        ->execute();
      $this->messenger()->addStatus($this->t('@count notifications deleted.', ['@count' => count($selected_ids)]));
    } else {
      $this->messenger()->addWarning($this->t('No notifications selected.'));
    }

    return $this->redirect('simple_notification.user_list', ['user' => $user]);
  }

  public function resendNotification($id) {
    $row = \Drupal::database()->select('simple_notification', 'n')
      ->fields('n', ['uid', 'message', 'link'])
      ->condition('id', $id)
      ->execute()
      ->fetchObject();

    if ($row) {
      $account = User::load($row->uid);
      if ($account && $account->getEmail()) {
        \Drupal::service('plugin.manager.mail')->mail(
          'simple_notification',
          'send_notification',
          $account->getEmail(),
          $account->getPreferredLangcode(),
          ['subject' => 'Resent Notification', 'body' => $row->message, 'link' => $row->link],
          NULL,
          TRUE
        );
        $this->messenger()->addStatus($this->t('Notification resent to @user.', ['@user' => $account->getDisplayName()]));
      }
    } else {
      $this->messenger()->addError($this->t('Notification not found.'));
    }

    return $this->redirect('simple_notification.send');
  }

    
    /**
     * Displays all sent notifications for admins.
     */
    public function adminNotificationList() {
      $query = \Drupal::database()->select('simple_notification', 'n')
        ->fields('n', ['id', 'uid', 'message', 'link', 'status', 'created'])
        ->orderBy('created', 'DESC');
      $result = $query->execute()->fetchAll();
    
      if (!$result) {
        return ['#markup' => $this->t('No notifications sent yet.')];
      }
    
      $rows = [];
      foreach ($result as $row) {
        $user = User::load($row->uid);
        $username = $user ? $user->toLink() : $this->t('Deleted user');
    
        $rows[] = [
          'id' => $row->id,
          'user' => $username,
          'message' => !empty($row->link)
            ? Link::fromTextAndUrl($row->message, Url::fromUri($row->link))
            : $row->message,
          'status' => $row->status ? $this->t('Read') : $this->t('Unread'),
          'created' => date('Y-m-d H:i', $row->created),
          'resend' => Link::fromTextAndUrl(
            $this->t('Resend'),
            Url::fromRoute('simple_notification.resend', ['id' => $row->id])
          ),
        ];
      }
    
      $header = [
        $this->t('ID'),
        $this->t('User'),
        $this->t('Message'),
        $this->t('Status'),
        $this->t('Created'),
        $this->t('Resend'),
      ];
    
      return [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No notifications sent yet.'),
      ];
    }

    
}
