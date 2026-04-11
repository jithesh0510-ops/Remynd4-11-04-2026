<?php

namespace Drupal\coach_reporting_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;

/**
 * Provides the Add Coach page.
 */
class CoachAddController extends ControllerBase {

  /**
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  public function __construct(EntityFormBuilderInterface $entity_form_builder) {
    $this->entityFormBuilder = $entity_form_builder;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.form_builder')
    );
  }

  /**
   * Returns the user entity form for creating a Coach.
   *
   * Uses the "Register" form mode (admin/config/people/accounts/form-display).
   */
  public function build() {
    // Create a new user entity.
    $account = User::create([
      'status' => 1, // Active
    ]);

    // Pre-assign the coach role.
    if ($account->hasField('roles')) {
      $account->addRole('coach');
    }

    // Use the "register" operation so fields follow your form display config.
    return $this->entityFormBuilder->getForm($account, 'register');
  }

}
