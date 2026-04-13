<?php

namespace Drupal\report_upload\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\report_upload\Form\LagardStartsForm;
use Drupal\report_upload\Form\PrePostRelationForm;
use Drupal\report_upload\Form\OnJobProgressForm;

class FormPageController extends ControllerBase {

  public function content() {
    $form_builder = \Drupal::formBuilder();

    return [
      '#type' => 'container',
      'lagard_starts_header' => [
        '#markup' => '<h2>' . $this->t('Lagard Starts') . '</h2>',
      ],
      'lagard_starts_form' => $form_builder->getForm(LagardStartsForm::class),
      'pre_post_relation_header' => [
        '#markup' => '<h2>' . $this->t('Pre/Post Relation') . '</h2>',
      ],
      'pre_post_relation_form' => $form_builder->getForm(PrePostRelationForm::class),
      'on_job_progress_header' => [
        '#markup' => '<h2>' . $this->t('On the Job Progress') . '</h2>',
      ],
      'on_job_progress_form' => $form_builder->getForm(OnJobProgressForm::class),
    ];
  }

}
