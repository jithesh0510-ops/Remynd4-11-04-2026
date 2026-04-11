<?php

namespace Drupal\coach_reporting_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the Uploads page displaying three upload forms.
 */
class UploadsController extends ControllerBase {

  public function __construct(FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('form_builder'));
  }

  /**
   * Page that renders three upload forms as shown in the image.
   */
  public function page(): array {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['crs-uploads-grid']],
      '#attached' => ['library' => ['coach_reporting_system/uploads']],
    ];

    // 1) Pre and Post Training Analysis Results (WIN)
    $build['win'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['crs-card']],
      'title' => ['#markup' => '<h2 class="crs-card__title">Pre and Post Training Analysis Results (WIN)</h2>'],
      'form' => $this->formBuilder->getForm(\Drupal\coach_reporting_system\Form\WinAnalysisForm::class),
    ];

    // 2) Pre & Post Training Results
    $build['prepost'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['crs-card']],
      'title' => ['#markup' => '<h2 class="crs-card__title">Pre &amp; Post Training Results</h2>'],
      'form' => $this->formBuilder->getForm(\Drupal\coach_reporting_system\Form\PrePostImportForm::class),
    ];

    // 3) On the Job Progress Results (Stars, Core & Laggards)
    $build['otj'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['crs-card', 'crs-card--full']],
      'title' => ['#markup' => '<h2 class="crs-card__title">On the Job Progress Results (Stars, Core &amp; Laggards)</h2>'],
      'form' => $this->formBuilder->getForm(\Drupal\coach_reporting_system\Form\OnTheJobProgressForm::class),
    ];

    return $build;
  }

}