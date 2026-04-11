<?php

namespace Drupal\mobile_otp_login\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

class MobileOtpLoginForm extends FormBase {

  public function getFormId(): string {
    return 'mobile_otp_login_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $session = \Drupal::request()->getSession();
    $otp_sent = (bool) $session->get('mobile_otp_sent', FALSE);
    $mobile = (string) $session->get('mobile_otp_number', '');

    $form['wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'style' => 'max-width:420px;margin:40px auto;padding:24px;border:1px solid #ddd;border-radius:8px;background:#fff;',
      ],
    ];

    $form['wrapper']['title'] = [
      '#markup' => '<h2 style="margin-top:0;">Login with Mobile OTP</h2>',
    ];

    if (!$otp_sent) {
      $form['wrapper']['mobile'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Registered mobile number'),
        '#required' => TRUE,
        '#maxlength' => 15,
        '#default_value' => $mobile,
        '#description' => $this->t('Uses user field_phone_no'),
      ];

      $form['wrapper']['send'] = [
        '#type' => 'submit',
        '#value' => $this->t('Send OTP'),
        '#submit' => ['::sendOtp'],
      ];
    }
    else {
      $form['wrapper']['info'] = [
        '#markup' => '<p>OTP sent to <strong>' . htmlspecialchars($mobile, ENT_QUOTES, 'UTF-8') . '</strong></p>',
      ];

      $form['wrapper']['otp'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Enter OTP'),
        '#required' => TRUE,
        '#maxlength' => 6,
        '#size' => 10,
      ];

      $form['wrapper']['actions'] = [
        '#type' => 'actions',
      ];

      $form['wrapper']['actions']['verify'] = [
        '#type' => 'submit',
        '#value' => $this->t('Verify OTP'),
        '#submit' => ['::verifyOtp'],
      ];

      $form['wrapper']['actions']['resend'] = [
        '#type' => 'submit',
        '#value' => $this->t('Resend OTP'),
        '#submit' => ['::resendOtp'],
        '#limit_validation_errors' => [],
      ];

      $form['wrapper']['actions']['change_mobile'] = [
        '#type' => 'submit',
        '#value' => $this->t('Change mobile'),
        '#submit' => ['::resetOtpState'],
        '#limit_validation_errors' => [],
      ];
    }

    return $form;
  }

  public function sendOtp(array &$form, FormStateInterface $form_state): void {
    $mobile = $this->normalizeMobile((string) $form_state->getValue('mobile'));
    $user = $this->loadUserByMobile($mobile);

    if (!$user) {
      \Drupal::messenger()->addError($this->t('No user found with this mobile number.'));
      return;
    }

    $otp = (string) random_int(100000, 999999);

    if (!$this->sendOtpSms($mobile, $otp)) {
      \Drupal::messenger()->addError($this->t('Unable to send mobile OTP.'));
      return;
    }

    $session = \Drupal::request()->getSession();
    $session->set('mobile_otp_code', $otp);
    $session->set('mobile_otp_number', $mobile);
    $session->set('mobile_otp_uid', (int) $user->id());
    $session->set('mobile_otp_sent', TRUE);
    $session->set('mobile_otp_time', time());

    \Drupal::messenger()->addStatus($this->t('OTP sent.'));
    $form_state->setRebuild();
  }

  public function verifyOtp(array &$form, FormStateInterface $form_state): void {
    $entered = trim((string) $form_state->getValue('otp'));
    $session = \Drupal::request()->getSession();

    $otp = (string) $session->get('mobile_otp_code', '');
    $uid = (int) $session->get('mobile_otp_uid', 0);
    $time = (int) $session->get('mobile_otp_time', 0);

    if (!$otp || !$uid || !$time) {
      \Drupal::messenger()->addError($this->t('OTP session missing. Please send OTP again.'));
      return;
    }

    if ((time() - $time) > 300) {
      $this->clearOtpSession();
      \Drupal::messenger()->addError($this->t('OTP expired. Please request a new OTP.'));
      return;
    }

    if ($entered !== $otp) {
      \Drupal::messenger()->addError($this->t('Invalid OTP.'));
      return;
    }

    $user = User::load($uid);
    if (!$user) {
      $this->clearOtpSession();
      \Drupal::messenger()->addError($this->t('User not found.'));
      return;
    }

    user_login_finalize($user);
    $this->clearOtpSession();
    $form_state->setRedirect('<front>');
  }

  public function resendOtp(array &$form, FormStateInterface $form_state): void {
    $session = \Drupal::request()->getSession();
    $mobile = (string) $session->get('mobile_otp_number', '');

    if (!$mobile) {
      \Drupal::messenger()->addError($this->t('No mobile found. Please enter it again.'));
      $this->clearOtpSession();
      $form_state->setRebuild();
      return;
    }

    $otp = (string) random_int(100000, 999999);

    if (!$this->sendOtpSms($mobile, $otp)) {
      \Drupal::messenger()->addError($this->t('Unable to resend mobile OTP.'));
      return;
    }

    $session->set('mobile_otp_code', $otp);
    $session->set('mobile_otp_time', time());

    \Drupal::messenger()->addStatus($this->t('A new OTP has been sent.'));
    $form_state->setRebuild();
  }

  public function resetOtpState(array &$form, FormStateInterface $form_state): void {
    $this->clearOtpSession();
    $form_state->setRebuild();
  }

  protected function clearOtpSession(): void {
    $session = \Drupal::request()->getSession();
    $session->remove('mobile_otp_code');
    $session->remove('mobile_otp_number');
    $session->remove('mobile_otp_uid');
    $session->remove('mobile_otp_sent');
    $session->remove('mobile_otp_time');
  }

  protected function normalizeMobile(string $mobile): string {
    $mobile = preg_replace('/\D+/', '', $mobile) ?? '';
    if (strlen($mobile) === 12 && str_starts_with($mobile, '91')) {
      $mobile = substr($mobile, 2);
    }
    return $mobile;
  }

  protected function loadUserByMobile(string $mobile): ?User {
    $uids = \Drupal::entityQuery('user')
      ->condition('field_phone_no', $mobile)
      ->accessCheck(FALSE)
      ->execute();

    if (empty($uids)) {
      return NULL;
    }

    $uid = reset($uids);
    return User::load($uid) ?: NULL;
  }

  protected function sendOtpSms(string $mobile, string $otp): bool {
    $auth_key = 'YOUR_MSG91_AUTH_KEY';
    $template_id = 'YOUR_MSG91_TEMPLATE_ID';

    $url = "https://control.msg91.com/api/v5/otp?template_id={$template_id}&mobile=91{$mobile}&authkey={$auth_key}&otp={$otp}";

    try {
      $client = \Drupal::httpClient();
      $response = $client->request('POST', $url, [
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'timeout' => 30,
      ]);

      return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }
    catch (\Throwable $e) {
      \Drupal::logger('mobile_otp_login')->error('MSG91 error: @msg', ['@msg' => $e->getMessage()]);
      return FALSE;
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
