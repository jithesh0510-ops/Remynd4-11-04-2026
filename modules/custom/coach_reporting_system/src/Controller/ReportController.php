<?php

namespace Drupal\coach_reporting_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns the Coach Reporting form page.
 */
class ReportController extends ControllerBase {

  /** @var \Drupal\Core\Form\FormBuilderInterface */
  protected $formBuilder;

  public function __construct(FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('form_builder')
    );
  }

  /**
   * Page callback to render the report form.
   */
  public function report(): array {
    $build['wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'coach-report-form-wrapper'],
      '#weight' => 0,
    ];
    $build['wrapper']['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -100,
    ];
    $build['wrapper']['form'] = $this->formBuilder->getForm('\Drupal\coach_reporting_system\Form\ReportForm');
    return $build;
  }
}
