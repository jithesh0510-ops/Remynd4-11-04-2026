<?php

namespace Drupal\email_otp_login\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

class EmailOtpLoginForm extends FormBase {

  public function getFormId() {
    return 'email_otp_login_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $step = $form_state->get('otp_step') ?: 1;
    $saved_email = $form_state->get('otp_email') ?: '';

    $form['#attributes']['class'][] = 'email-otp-login-form';

    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => '<div class="email-otp-login-card-title">Login</div><div class="email-otp-login-card-subtitle"></div>',
      '#weight' => -100,
    ];

    if ($step == 1) {
      $form['email'] = [
        '#type' => 'email',
        '#title' => $this->t('Email address'),
        '#required' => TRUE,
        '#default_value' => $saved_email,
        '#attributes' => [
          'placeholder' => $this->t('Enter your email address'),
        ],
      ];

      $form['password'] = [
        '#type' => 'password',
        '#title' => $this->t('Password'),
        '#required' => TRUE,
        '#attributes' => [
          'placeholder' => $this->t('Enter your password'),
        ],
      ];

      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['continue'] = [
        '#type' => 'submit',
        '#value' => $this->t('Continue'),
        '#submit' => ['::stepOneSubmit'],
      ];
    }
    else {
      $form['current_email'] = [
        '#type' => 'markup',
        '#markup' => '<div class="otp-current-email"><strong>' . $this->t('Email:') . '</strong> ' . $saved_email . '</div>',
      ];

      $form['otp'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Validate OTP'),
        '#required' => TRUE,
        '#maxlength' => 6,
        '#attributes' => [
          'placeholder' => $this->t('Enter 6-digit OTP'),
          'autocomplete' => 'one-time-code',
        ],
      ];

      $form['actions'] = ['#type' => 'actions'];

      $form['actions']['validate_otp'] = [
        '#type' => 'submit',
        '#value' => $this->t('Validate OTP'),
        '#submit' => ['::submitForm'],
      ];

      $form['actions']['resend_otp'] = [
        '#type' => 'submit',
        '#value' => $this->t('Resend OTP'),
        '#submit' => ['::resendOtpSubmit'],
        '#limit_validation_errors' => [],
      ];

      $form['actions']['change_email'] = [
        '#type' => 'submit',
        '#value' => $this->t('Change Email'),
        '#submit' => ['::changeEmailSubmit'],
        '#limit_validation_errors' => [],
      ];
    }

    return $form;
  }

  public function stepOneSubmit(array &$form, FormStateInterface $form_state) {
    $email = trim((string) $form_state->getValue('email'));
    $password = (string) $form_state->getValue('password');

    $uids = \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->condition('mail', $email)
      ->execute();

    if (empty($uids)) {
      $this->messenger()->addError($this->t('Sorry, we can’t find any user associated with this email.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $uid = reset($uids);
    $user = User::load($uid);

    if (!$user || !$user->isActive()) {
      $this->messenger()->addError($this->t('This account is inactive.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $password_service = \Drupal::service('password');
    if (!$password_service->check($password, $user->getPassword())) {
      $this->messenger()->addError($this->t('Invalid password.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $otp = (string) random_int(100000, 999999);
    $expire = \Drupal::time()->getRequestTime() + 300;

    \Drupal::state()->set('email_otp_login.' . $uid, [
      'otp' => $otp,
      'expire' => $expire,
      'mail' => $email,
    ]);

    $mail_sent = $this->sendOtpMail($user, $email, $otp);

    if (!$mail_sent) {
      $this->messenger()->addError($this->t('Unable to send OTP email. Contact the site administrator.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $form_state->set('otp_step', 2);
    $form_state->set('otp_uid', $uid);
    $form_state->set('otp_email', $email);
    $form_state->setRebuild(TRUE);

    $this->messenger()->addStatus($this->t('OTP has been sent to your email.'));
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $uid = $form_state->get('otp_uid');
    $email = $form_state->get('otp_email');
    $entered_otp = trim((string) $form_state->getValue('otp'));

    if (!$uid || !$email) {
      $this->messenger()->addError($this->t('Session expired. Please login again.'));
      $form_state->set('otp_step', 1);
      $form_state->setRebuild(TRUE);
      return;
    }

    $stored = \Drupal::state()->get('email_otp_login.' . $uid);

    if (empty($stored['otp']) || empty($stored['expire'])) {
      $this->messenger()->addError($this->t('OTP session expired. Please try again.'));
      $form_state->set('otp_step', 1);
      $form_state->setRebuild(TRUE);
      return;
    }

    if (\Drupal::time()->getRequestTime() > $stored['expire']) {
      \Drupal::state()->delete('email_otp_login.' . $uid);
      $this->messenger()->addError($this->t('OTP expired. Please request a new one.'));
      $form_state->set('otp_step', 1);
      $form_state->setRebuild(TRUE);
      return;
    }

    if ($entered_otp !== $stored['otp']) {
      $this->messenger()->addError($this->t('Invalid OTP.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $user = User::load($uid);
    if (!$user || !$user->isActive()) {
      $this->messenger()->addError($this->t('Unable to login. User account is invalid.'));
      $form_state->set('otp_step', 1);
      $form_state->setRebuild(TRUE);
      return;
    }

    user_login_finalize($user);
    \Drupal::state()->delete('email_otp_login.' . $uid);

    $form_state->setRedirect('<front>');
  }

  public function resendOtpSubmit(array &$form, FormStateInterface $form_state) {
    $uid = $form_state->get('otp_uid');
    $email = $form_state->get('otp_email');

    if (!$uid || !$email) {
      $this->messenger()->addError($this->t('Session expired. Please login again.'));
      $form_state->set('otp_step', 1);
      $form_state->setRebuild(TRUE);
      return;
    }

    $user = User::load($uid);
    if (!$user) {
      $this->messenger()->addError($this->t('User not found.'));
      $form_state->set('otp_step', 1);
      $form_state->setRebuild(TRUE);
      return;
    }

    $otp = (string) random_int(100000, 999999);
    $expire = \Drupal::time()->getRequestTime() + 300;

    \Drupal::state()->set('email_otp_login.' . $uid, [
      'otp' => $otp,
      'expire' => $expire,
      'mail' => $email,
    ]);

    $mail_sent = $this->sendOtpMail($user, $email, $otp);

    if (!$mail_sent) {
      $this->messenger()->addError($this->t('Unable to resend OTP email.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $this->messenger()->addStatus($this->t('OTP resent successfully.'));
    $form_state->set('otp_step', 2);
    $form_state->setRebuild(TRUE);
  }

  public function changeEmailSubmit(array &$form, FormStateInterface $form_state) {
    $uid = $form_state->get('otp_uid');

    if ($uid) {
      \Drupal::state()->delete('email_otp_login.' . $uid);
    }

    $form_state->set('otp_step', 1);
    $form_state->set('otp_uid', NULL);
    $form_state->set('otp_email', NULL);
    $form_state->setRebuild(TRUE);
  }

  protected function sendOtpMail(User $user, string $email, string $otp): bool {
    $body = '
  <div style="font-family:Arial,sans-serif;font-size:16px;color:#17324d;line-height:1.6;background:#f4f8fb;padding:30px;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #d9e7ee;border-radius:12px;overflow:hidden;">
      <div style="background:#17324d;padding:20px 24px;text-align:center;">
        <div style="font-size:24px;font-weight:bold;color:#ffffff;">Remynd4</div>
        <div style="font-size:13px;color:#cfeaf0;margin-top:4px;">Secure OTP Verification</div>
      </div>
      <div style="padding:28px 24px;">
        <p style="margin:0 0 14px 0;">Hello ' . htmlspecialchars($user->getDisplayName(), ENT_QUOTES, 'UTF-8') . ',</p>
        <p style="margin:0 0 14px 0;">Your one-time login OTP is:</p>
        <div style="margin:22px 0;text-align:center;">
          <span style="display:inline-block;font-size:30px;font-weight:bold;letter-spacing:6px;color:#1fa8c5;background:#eef9fc;border:1px dashed #1fa8c5;border-radius:10px;padding:14px 24px;">
            ' . $otp . '
          </span>
        </div>
        <p style="margin:0 0 10px 0;">This OTP is valid for <strong>5 minutes</strong>.</p>
        <p style="margin:0;color:#5f6f7f;">If you did not request this, you can safely ignore this email.</p>
      </div>
    </div>
  </div>';

    $result = \Drupal::service('plugin.manager.mail')->mail(
      'email_otp_login',
      'send_otp',
      $email,
      \Drupal::languageManager()->getDefaultLanguage()->getId(),
      [
        'subject' => 'Your OTP for login',
        'body' => $body,
        'user' => $user,
      ],
      NULL,
      TRUE
    );

    return !empty($result['result']);
  }

}
