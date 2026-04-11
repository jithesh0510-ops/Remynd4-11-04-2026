<?php

namespace Drupal\redirect_after_login\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\user\Entity\Role;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Login Redirection Form class.
 */
class LoginRedirectionForm extends ConfigFormBase {

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * Constructs a new LoginRedirectionForm.
   *
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The path validator service.
   */
  public function __construct(PathValidatorInterface $path_validator) {
    $this->pathValidator = $path_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('path.validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'login_redirection_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('redirect_after_login.settings');
    $savedPathRoles = $config->get('login_redirection');

    $form['roles'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('All roles'),
    ];

    $roles = Role::loadMultiple();
    foreach ($roles as $role_id => $role) {
      if ($role_id != "anonymous") {
        $form['roles'][$role_id] = [
          '#type'          => 'textfield',
          '#title'         => $role->label(),
          '#size'          => 60,
          '#maxlength'     => 128,
          '#description'   => $this->t('Add a valid url or <front> for the main page'),
          '#required'      => TRUE,
          '#default_value' => $savedPathRoles[$role_id] ?? '',
        ];
      }
    }

    $form['exclude_urls'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Exclude url from redirection'),
      '#description'   => $this->t('One url per line. Redirection on this urls will be skipped. You can use wildcard "*".'),
      '#default_value' => $config->get('exclude_urls'),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    $roles = Role::loadMultiple();

    foreach ($roles as $role_id => $role) {
      // Skip the "anonymous" role.
      if ($role_id == "anonymous") {
        continue;
      }

      $role_name = $role->label();
      $role_value = $form_state->getValue($role_id);

      // Validate the role URL.
      if (!(preg_match('/^[#?\/]+/', $role_value) || $role_value == '<front>')) {
        $form_state->setErrorByName($role_id, $this->t('This URL %url is not valid for role %role.', [
          '%url'  => $role_value,
          '%role' => $role_name,
        ]));
      }

      // Check if the path is valid.
      $is_valid = $this->pathValidator->isValid($role_value);
      if ($is_valid == NULL) {
        $form_state->setErrorByName($role_id, $this->t('Path does not exist.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $loginUrls = [];
    // Load all roles.
    $roles = Role::loadMultiple();

    foreach ($roles as $role_id => $role) {
      // Get the form value for the current role.
      $role_value = $form_state->getValue($role_id);

      // Check if the form value is '<front>'.
      if ($role_value == '<front>') {
        $loginUrls[$role_id] = '/';
      }
      else {
        $loginUrls[$role_id] = $role_value;
      }
    }

    $this->config('redirect_after_login.settings')
      ->set('login_redirection', $loginUrls)
      ->set('exclude_urls', $form_state->getValue('exclude_urls'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Get Editable config names.
   *
   * @inheritDoc
   */
  protected function getEditableConfigNames() {
    return ['redirect_after_login.settings'];
  }

}
