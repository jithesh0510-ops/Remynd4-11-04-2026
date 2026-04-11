<?php

namespace Drupal\simple_notification\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

class SendNotificationForm extends FormBase {

  public function getFormId() {
    return 'simple_notification_send_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notification Message'),
      '#required' => TRUE,
    ];

    $form['link'] = [
      '#type' => 'url',
      '#title' => $this->t('Optional Link'),
      '#description' => $this->t('Add a link for users to click.'),
      '#required' => FALSE,
    ];

    $form['target'] = [
      '#type' => 'radios',
      '#title' => $this->t('Send To'),
      '#options' => [
        'all' => $this->t('All users'),
        'roles' => $this->t('Specific roles'),
        'users' => $this->t('Specific users'),
      ],
      '#default_value' => 'all',
    ];

    $form['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select Roles'),
      '#options' => user_role_names(TRUE),
      '#states' => [
        'visible' => [':input[name="target"]' => ['value' => 'roles']],
      ],
    ];

    $form['users'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Select Users'),
      '#target_type' => 'user',
      '#tags' => TRUE,
      '#states' => [
        'visible' => [':input[name="target"]' => ['value' => 'users']],
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send Notification'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $message = $form_state->getValue('message');
    $link = $form_state->getValue('link');
    $target = $form_state->getValue('target');
    $uids = [];

    if ($target === 'all') {
      $uids = \Drupal::entityQuery('user')->condition('status', 1)->execute();
    } elseif ($target === 'roles') {
      $roles = array_filter($form_state->getValue('roles'));
      if ($roles) {
        $uids = \Drupal::entityQuery('user')
          ->condition('status', 1)
          ->condition('roles', array_keys($roles), 'IN')
          ->execute();
      }
    } elseif ($target === 'users') {
      foreach ($form_state->getValue('users') as $user) {
        $uids[] = $user['target_id'];
      }
    }

    foreach ($uids as $uid) {
      \Drupal::database()->insert('simple_notification')
        ->fields(['uid' => $uid, 'message' => $message, 'link' => $link, 'status' => 0, 'created' => \Drupal::time()->getCurrentTime()])
        ->execute();

      $account = User::load($uid);
      if ($account && $account->getEmail()) {
        \Drupal::service('plugin.manager.mail')->mail(
          'simple_notification',
          'send_notification',
          $account->getEmail(),
          $account->getPreferredLangcode(),
          ['subject' => 'New Notification', 'body' => $message, 'link' => $link],
          NULL,
          TRUE
        );
      }
    }

    $this->messenger()->addStatus($this->t('Notification sent to @count user(s).', ['@count' => count($uids)]));
  }
}
