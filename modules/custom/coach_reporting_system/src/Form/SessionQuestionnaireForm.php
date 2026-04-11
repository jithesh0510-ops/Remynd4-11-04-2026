<?php

namespace Drupal\coach_reporting_system\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SessionQuestionnaireForm extends FormBase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected $tempstore;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    PrivateTempStoreFactory $tempstore_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->tempstore = $tempstore_factory->get('coach_reporting_system');
  }

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('tempstore.private')
    );
  }

  public function getFormId(): string {
    return 'coach_reporting_system_session_questionnaire_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $account = $this->currentUser();
    if (!in_array('coach', $account->getRoles(), TRUE)) {
      return ['#markup' => $this->t('Access denied. Coaches only.')];
    }

    $session = $this->tempstore->get('current_session');
    if (empty($session)) {
      return ['#markup' => $this->t('No active session found. Please start a session first.')];
    }

    $program  = $this->entityTypeManager->getStorage('node')->load($session['program_nid']);
    $company  = $this->entityTypeManager->getStorage('user')->load($session['company_uid']);
    $employee = $this->entityTypeManager->getStorage('user')->load($session['employee_uid']);

    $form['summary'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['crs-session-summary']],
      'company'  => ['#markup' => '<div><strong>'.$this->t('Company Name').'</strong><br>'. ($company ? $company->label() : $session['company_uid']) .'</div>'],
      'program'  => ['#markup' => '<div><strong>'.$this->t('Questionnaire Name').'</strong><br>'. ($program ? $program->label() : $session['program_nid']) .'</div>'],
      'employee' => ['#markup' => '<div><strong>'.$this->t('Employee Name').'</strong><br>'. ($employee ? $employee->label() : $session['employee_uid']) .'</div>'],
      'date'     => ['#markup' => '<div><strong>'.$this->t('Filling Date').'</strong><br>'. $session['fill_date'] .'</div>'],
    ];
    
    
    // If we have validation errors, show them in a popup dialog.
    $errors = (array) $form_state->get('crs_errors', []);
    if (!empty($errors)) {
      $list = '<ul class="crs-error-list"><li>'. implode('</li><li>', array_map('htmlspecialchars', $errors)) .'</li></ul>';
      $form['error_dialog'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'crs-error-dialog', 'title' => $this->t('Please fix the following')],
        '#markup' => $list,
      ];
      $form_state->set('crs_errors', []);
    }
    

    if (!$program instanceof NodeInterface) {
      $form['summary']['warn'] = ['#markup' => '<div class="messages messages--error">'.$this->t('Questionnaire not found.').'</div>'];
      return $form;
    }

    // Steps are the TOP-LEVEL paragraphs from field_create_questionnaire.
    $steps = $form_state->get('crs_steps');
    if ($steps === NULL) {
      $steps = $this->buildStepsFromField($program, 'field_create_questionnaire');
      $form_state->set('crs_steps', $steps);
      $form_state->set('crs_step', 0);
      $form_state->set('crs_responses', []);
    }

    $idx     = (int) ($form_state->get('crs_step') ?? 0);
    $count   = max(1, count($steps));
    $idx     = max(0, min($idx, $count - 1));
    $current = $steps[$idx] ?? NULL;
    $saved   = (array) $form_state->get('crs_responses', []);

    $form['progress'] = [
      '#markup' => '<div class="crs-progress">'.$this->t('Step @i of @t', ['@i' => $idx + 1, '@t' => $count]).'</div>',
    ];

    $form['step'] = ['#type' => 'container', '#attributes' => ['class' => ['crs-step']]];

    if ($current) {
      $uuid   = $current['uuid'];
      $label  = $current['label'];
      $widget = $current['widget'];
      $def    = $current['def'];

      $form['step']['label'] = ['#markup' => '<h3 class="crs-step-title">'.$this->t('@t', ['@t' => $label]).'</h3>'];

      if ($widget === 'matrix') {
        $options = $def['options'];   // columns
        $rows    = $def['rows'];      // rows
        $header  = array_merge([$this->t('Question')], array_values($options));

        $form['step']['matrix'] = [
          '#type' => 'table',
          '#header' => $header,
          '#attributes' => ['class' => ['crs-likert']],
        ];

        $matrix_saved = $saved[$uuid] ?? []; // per-row saved values
        foreach ($rows as $r) {
          $rid = $r['id'];
          if ($r['type'] === 'heading') {
            $form['step']['matrix'][$rid]['q'] = [
              '#markup' => '<div class="crs-heading">'.$r['label'].'</div>',
              '#wrapper_attributes' => ['class' => ['crs-cell-heading']],
            ];
            foreach ($options as $k => $_) {
              $form['step']['matrix'][$rid]["opt_$k"] = ['#markup' => '&nbsp;'];
            }
            continue;
          }
          $form['step']['matrix'][$rid]['q'] = ['#markup' => '<span class="crs-question-label">'.$r['label'].'</span>'];
          $default = $matrix_saved[$rid] ?? NULL;
          foreach ($options as $k => $lbl) {
            $form['step']['matrix'][$rid]["opt_$k"] = [
              '#type' => 'radio',
              '#title' => '',
              '#return_value' => $k,
              '#parents' => ['matrix', $rid],     // ← groups per row (required per row)
              '#default_value' => $default,
              '#attributes' => ['aria-label' => $lbl],
            ];
          }
        }
        $form['current_uuid'] = ['#type' => 'hidden', '#value' => $uuid];
      }
      elseif ($widget === 'radios') {
        $form['step']['answer'] = [
          '#type' => 'radios',
          '#title' => $this->t('Select one'),
          '#options' => $def['options'] ?? [],
          '#required' => TRUE,                   // everything is required
          '#default_value' => $saved[$uuid] ?? NULL,
        ];
        $form['current_uuid'] = ['#type' => 'hidden', '#value' => $uuid];
      }
      elseif ($widget === 'textfield') {
        $form['step']['answer'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Your answer'),
          '#required' => TRUE,                   // everything is required
          '#default_value' => $saved[$uuid] ?? '',
        ];
        $form['current_uuid'] = ['#type' => 'hidden', '#value' => $uuid];
      }
      else {
        $form['step']['answer'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Your notes'),
          '#rows' => 5,
          '#required' => TRUE,                   // everything is required
          '#default_value' => $saved[$uuid] ?? '',
        ];
        $form['current_uuid'] = ['#type' => 'hidden', '#value' => $uuid];
      }
    } else {
      $form['step']['empty'] = ['#markup' => $this->t('No steps on this questionnaire.')];
    }

    // Actions.
    $form['actions'] = ['#type' => 'actions'];
    if ($idx > 0) {
      $form['actions']['back'] = [
        '#type' => 'submit',
        '#value' => $this->t('Back'),
        '#limit_validation_errors' => [],
        '#submit' => ['::goBack'],
      ];
    }
    if ($idx < $count - 1) {
      $form['actions']['next'] = [
        '#type' => 'submit',
        '#value' => $this->t('Next'),
        '#submit' => ['::goNext'],
      ];
    } else {
      $form['actions']['finish'] = [
        '#type' => 'submit',
        '#value' => $this->t('Finish & Save'),
        '#button_type' => 'primary',
        '#submit' => ['::finish'],
      ];
    }

    

    // Inline CSS.
    $form['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => '
          .crs-session-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:12px 0 18px}
          .crs-session-summary div{background:#0f172a0d;padding:10px;border-radius:6px}
          .crs-progress{margin:4px 0 14px;font-weight:600;color:#4b5563}
          .crs-step-title{margin:.25rem 0 1rem}
          table.crs-likert{width:100%}
          table.crs-likert .crs-heading{font-weight:600;color:#198754}
          .crs-question-label{display:block;max-width:980px}
          table.crs-likert td{vertical-align:middle}
          table.crs-likert input[type=radio]{display:block;margin:auto}
          .crs-error-list{margin:.5rem 0 .25rem 1rem}
          .crs-error-list {	margin: .5rem 0 .25rem 1rem;	background: #a80b0b;	color: #fff;	padding: 0.5rem 2rem;	border-radius: 5px;}
        ',
      ],
      'crs_wizard_inline_css',
    ];

    return $form;
  }

  /** Back button. */
  public function goBack(array &$form, FormStateInterface $form_state): void {
    $this->captureCurrent($form_state);
    $i = (int) ($form_state->get('crs_step') ?? 0);
    $form_state->set('crs_step', max(0, $i - 1))->setRebuild(TRUE);
  }

  /** Next button. */
  public function goNext(array &$form, FormStateInterface $form_state): void {
    if (!$this->captureCurrent($form_state, TRUE)) { return; }
    $steps = (array) $form_state->get('crs_steps');
    $i = (int) ($form_state->get('crs_step') ?? 0);
    $form_state->set('crs_step', min(count($steps) - 1, $i + 1))->setRebuild(TRUE);
  }

  /** Finish & Save. */
  public function finish(array &$form, FormStateInterface $form_state): void {
    if (!$this->captureCurrent($form_state, TRUE)) { return; }

    $session = $this->tempstore->get('current_session');
    if (empty($session)) {
      $this->messenger()->addError($this->t('No active session to save.'));
      return;
    }

    $answers = (array) $form_state->get('crs_responses', []);
    $conn = \Drupal::database();

    // Ensure table exists (best practice is in hook_install, but this is a safety net).
    if (!$conn->schema()->tableExists('coach_reporting_session_answer')) {
      $spec = [
        'description' => 'Stores answers for coach reporting sessions.',
        'fields' => [
          'id'        => ['type' => 'serial', 'not null' => TRUE],
          'sid'       => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
          'step_uuid' => ['type' => 'varchar', 'length' => 128, 'not null' => TRUE],
          'row_uuid'  => ['type' => 'varchar', 'length' => 128, 'not null' => FALSE, 'default' => NULL],
          'value'     => ['type' => 'text', 'size' => 'big', 'not null' => FALSE],
          'created'   => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        ],
        'primary key' => ['id'],
        'indexes' => [
          'sid' => ['sid'],
          'step_uuid' => ['step_uuid'],
          'row_uuid' => ['row_uuid'],
        ],
      ];
      $conn->schema()->createTable('coach_reporting_session_answer', $spec);
    }

    // Persist.
    $conn->delete('coach_reporting_session_answer')
      ->condition('sid', (int) $session['sid'])
      ->execute();

    foreach ($answers as $step_uuid => $value) {
      if (is_array($value)) {
        // Matrix: per-row answer.
        foreach ($value as $row_uuid => $selected_key) {
          if ($selected_key === NULL || $selected_key === '') { continue; }
          $conn->insert('coach_reporting_session_answer')
            ->fields([
              'sid'       => (int) $session['sid'],
              'step_uuid' => (string) $step_uuid,
              'row_uuid'  => (string) $row_uuid,
              'value'     => (string) $selected_key,
              'created'   => \Drupal::time()->getRequestTime(),
            ])->execute();
        }
      }
      else {
        if ($value === NULL || $value === '') { continue; }
        $conn->insert('coach_reporting_session_answer')
          ->fields([
            'sid'       => (int) $session['sid'],
            'step_uuid' => (string) $step_uuid,
            'row_uuid'  => NULL,
            'value'     => (string) $value,
            'created'   => \Drupal::time()->getRequestTime(),
          ])->execute();
      }
    }

    // Mark session row as submitted if your table exists.
    if ($conn->schema()->tableExists('coach_reporting_session')) {
      $conn->update('coach_reporting_session')
        ->fields(['submitted' => \Drupal::time()->getRequestTime()])
        ->condition('sid', (int) $session['sid'])
        ->execute();
    }

    // Clear the temp session (as requested) and redirect back to Start.
    $this->tempstore->delete('current_session');
    $this->messenger()->addStatus($this->t('Session saved.'));
    $form_state->setRedirect('coach_reporting_system.session_start');
  }

  /** Fallback submit (pressing Enter). */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $steps = (array) $form_state->get('crs_steps', []);
    $i = (int) ($form_state->get('crs_step') ?? 0);
    if ($i < (count($steps) - 1)) { $this->goNext($form, $form_state); }
    else { $this->finish($form, $form_state); }
  }

  // ───────────────────── Helpers ─────────────────────

  /** Build steps only from a single paragraph ref field (keeps author’s order). */
  protected function buildStepsFromField(NodeInterface $node, string $field_name): array {
    $steps = [];
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return $steps;
    }
    foreach ($node->get($field_name)->referencedEntities() as $para) {
      if ($para instanceof ParagraphInterface) {
        $step = $this->stepFromParagraph($para);
        if ($step) { $steps[] = $step; }
      }
    }
    return $steps;
  }

  /** Paragraph → Step mapping. */
  protected function stepFromParagraph(ParagraphInterface $p): array {
    $uuid = $p->uuid();
    $bundle = $this->normalizeBundle($p->bundle());
    $label  = $this->extractLabel($p) ?: ucfirst(str_replace('_', ' ', $bundle));

    if ($bundle === 'questionnaire') {
      $options = $this->extractScoreOptions($p);       // field_options (Score)
      $rows    = $this->collectRowsFromCategories($p); // field_category (Category)
      return [
        'uuid'   => $uuid,
        'bundle' => $bundle,
        'label'  => $label,
        'widget' => 'matrix',
        'def'    => ['options' => $options, 'rows' => $rows],
      ];
    }

    if ($bundle === 'fabs') {
      return [
        'uuid'   => $uuid,
        'bundle' => $bundle,
        'label'  => $label,
        'widget' => 'textfield',
        'def'    => ['required' => TRUE],
      ];
    }

    return [
      'uuid'   => $uuid,
      'bundle' => $bundle,
      'label'  => $label,
      'widget' => 'textarea',
      'def'    => ['required' => TRUE],
    ];
  }

  /** Validate & capture the current step into form_state. */
  protected function captureCurrent(FormStateInterface $form_state, bool $validate = FALSE): bool {
    $steps = (array) $form_state->get('crs_steps', []);
    $i     = (int) ($form_state->get('crs_step') ?? 0);
    $curr  = $steps[$i] ?? NULL;
    if (!$curr) { return TRUE; }

    $uuid   = (string) $form_state->getValue('current_uuid');
    $widget = $curr['widget'];
    $errors = [];

    if ($widget === 'matrix') {
      $value = (array) $form_state->getValue('matrix', []);
      // EVERY question-row must be answered.
      $must_rows = array_values(array_filter($curr['def']['rows'], fn($r) => $r['type'] === 'question'));
      foreach ($must_rows as $r) {
        $rid = $r['id'];
        if (!isset($value[$rid]) || $value[$rid] === '' || $value[$rid] === NULL) {
          $errors[] = $this->t('Please select a score for “@q”.', ['@q' => $r['label']]);
        }
      }
      if ($validate && $errors) {
        $form_state->set('crs_errors', $errors)->setRebuild(TRUE);
        return FALSE;
      }
      $res = (array) $form_state->get('crs_responses', []);
      $res[$uuid] = $value;
      $form_state->set('crs_responses', $res);
      return TRUE;
    }

    // Scalar widgets (all required).
    $value = $form_state->getValue('answer');
    if ($validate && ($value === NULL || $value === '')) {
      $errors[] = $this->t('This field is required.');
      $form_state->set('crs_errors', $errors)->setRebuild(TRUE);
      return FALSE;
    }
    $res = (array) $form_state->get('crs_responses', []);
    $res[$uuid] = $value;
    $form_state->set('crs_responses', $res);
    return TRUE;
  }

  /** Columns from field_options (Paragraph type: Score). */
  protected function extractScoreOptions(ParagraphInterface $questionnaire): array {
    $fallback = [
      'vgood' => (string) $this->t('Very Good'),
      'good'  => (string) $this->t('Good'),
      'avg'   => (string) $this->t('Average'),
      'poor'  => (string) $this->t('Poor'),
      'vpoor' => (string) $this->t('Very Poor'),
    ];
    if (!$questionnaire->hasField('field_options') || $questionnaire->get('field_options')->isEmpty()) {
     // return $fallback;
    }
    $opts = [];
    foreach ($questionnaire->get('field_options')->referencedEntities() as $score_para) {
      if ($score_para instanceof \Drupal\paragraphs\ParagraphInterface) {
        // Label from your helper.
        $label = $this->extractLabel($score_para);
    
        // Get the numeric value from field_option_value.
        $value = $score_para->get('field_option_value')->value;
    
        $opts[$value] = $label;
      }
    }

    return $opts ?: $fallback;
  }

  /** Rows from field_category (Paragraph type: Category), recursing. */
  protected function collectRowsFromCategories(ParagraphInterface $questionnaire): array {
    $rows = [];
    if (!$questionnaire->hasField('field_category') || $questionnaire->get('field_category')->isEmpty()) {
      return $rows;
    }
    foreach ($questionnaire->get('field_category')->referencedEntities() as $cat) {
      if ($cat instanceof ParagraphInterface) {
        $this->collectCategoryDeep($cat, $rows);
      }
    }
    return $rows;
  }

  protected function collectCategoryDeep(ParagraphInterface $p, array &$rows): void {
    $label = $this->extractLabel($p);
    $children = $this->paragraphChildren($p);

    if ($label !== '') {
      $rows[] = ['type' => 'heading', 'id' => $p->uuid(), 'label' => $label];
    }

    if ($children) {
      foreach ($children as $child) { $this->collectCategoryDeep($child, $rows); }
      return;
    }

    if ($label !== '') {
      $rows[] = ['type' => 'question', 'id' => $p->uuid(), 'label' => $label];
    }
  }

  protected function paragraphChildren(ParagraphInterface $p): array {
    $out = [];
    foreach ($p->getFieldDefinitions() as $fname => $def) {
      if ($this->isParagraphReference($def) && $p->hasField($fname) && !$p->get($fname)->isEmpty()) {
        foreach ($p->get($fname)->referencedEntities() as $ref) {
          if ($ref instanceof ParagraphInterface) { $out[] = $ref; }
        }
      }
    }
    return $out;
  }

  protected function isParagraphReference(FieldDefinitionInterface $def): bool {
    if ($def->getType() !== 'entity_reference_revisions') { return FALSE; }
    $settings = (array) $def->getSettings();
    return ($settings['target_type'] ?? NULL) === 'paragraph';
  }

  protected function normalizeBundle(string $bundle): string {
    $bundle = strtolower(str_replace([' ', '-'], '_', $bundle));
    $map = [
      'coaching_one_to_one_report' => 'coaching_one_to_one_report',
      'color_&_interaction_tool'   => 'color_interaction_tool',
      'color_and_interaction_tool' => 'color_interaction_tool',
      'decision_making_unit'       => 'decision_making_unit',
      'fabs'                       => 'fabs',
      'handle_objections'          => 'handle_objections',
      'kpi'                        => 'kpi',
      'pipeline'                   => 'pipeline',
      'questionnaire'              => 'questionnaire',
      'testimonial'                => 'testimonial',
    ];
    return $map[$bundle] ?? $bundle;
  }

  protected function extractLabel(ParagraphInterface $p): string {
    foreach (['field_category','field_question','field_title','field_label','field_heading','field_name','field_text','field_description','field_option_title'] as $f) {
      if ($p->hasField($f) && !$p->get($f)->isEmpty()) {
        $item = $p->get($f)->first();
        if ($item && isset($item->value) && trim((string) $item->value) !== '') {
          return trim((string) $item->value);
        }
        if ($item && isset($item->processed) && trim((string) $item->processed) !== '') {
          return trim((string) $item->processed);
        }
      }
    }
    return '';
  }

}
