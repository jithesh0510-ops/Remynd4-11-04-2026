<?php

namespace Drupal\coach_creator\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\profile\Entity\Profile;

class AddCoachForm extends FormBase {

  public function getFormId() {
    return 'add_coach_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['email'] = [
      '#type' => 'email',
      '#title' => 'Email',
      '#required' => TRUE,
    ];

    $form['password'] = [
      '#type' => 'password',
      '#title' => 'Password',
      '#required' => TRUE,
    ];

    $form['company'] = [
      '#type' => 'textfield',
      '#title' => 'Company',
    ];

    $form['enable_list'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable (Coach will see employees)',
    ];

    $form['see_laggards'] = [
      '#type' => 'checkbox',
      '#title' => 'See Laggards to Stars',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Create Coach',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

    $user = User::create([
      'name' => $form_state->getValue('email'),
      'mail' => $form_state->getValue('email'),
      'status' => 1,
    ]);

    $user->addRole('coach');
    $user->setPassword($form_state->getValue('password'));
    $user->save();

    $profile = Profile::create([
      'type' => 'coach',
      'uid' => $user->id(),
    ]);

    $profile->set('field_company', $form_state->getValue('company'));
    $profile->set('field_enable_the_coach_will_see', $form_state->getValue('enable_list'));
    $profile->set('field_see_laggards_to_stars', $form_state->getValue('see_laggards'));

    $profile->save();

    \Drupal::messenger()->addMessage('Coach created successfully.');
  }
}
