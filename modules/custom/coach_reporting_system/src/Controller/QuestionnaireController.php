<?php

namespace Drupal\coach_reporting_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Render\Markup;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class QuestionnaireController extends ControllerBase {

  /**
   * Canonical node route override:
   * - questionnaire/survey → custom detail view (preview-only)
   * - others → default node view
   */
  public function nodeViewOverride(Node $node): array {
    if (!in_array($node->bundle(), ['questionnaire', 'survey'], TRUE)) {
      // Unchanged for other bundles.
      return \Drupal::entityTypeManager()->getViewBuilder('node')->view($node, 'full');
    }

    $build = [];

    // Keep local tasks (View/Edit/Delete...) so Edit shows at the top.
    $build['tabs'] = [
      '#type' => 'local_tasks',
      '#route_name' => 'entity.node.canonical',
      '#route_parameters' => ['node' => $node->id()],
      '#weight' => -100,
    ];

    $build['content'] = $this->renderDetail($node);
    $build['#cache']['max-age'] = 0;

    return $build;
  }

  /**
   * Read-only detail rendering from the node only (no session, no saving).
   */
  protected function renderDetail(NodeInterface $node): array {
    if (!in_array($node->bundle(), ['questionnaire', 'survey'], TRUE)) {
      throw new NotFoundHttpException();
    }

    // Build steps exactly like the form logic, from field_create_questionnaire.
    $steps = $this->buildStepsFromField($node, 'field_create_questionnaire');

    $html = '<div class="crs-preview">';
    $html .= '<div class="crs-preview-header">';
   /* $html .= '<h2 class="crs-title">'. htmlspecialchars($node->label()) .'</h2>';*/
    $html .= '<div class="crs-meta"><strong>'. $this->t('Created') .':</strong> '
      . date('Y-m-d H:i:s', $node->getCreatedTime()) .' &nbsp; <strong>'. $this->t('Author') .':</strong> '
      . htmlspecialchars($node->getOwner()->getDisplayName()) .'</div>';
    $html .= '</div>';

    if (!$steps) {
      $html .= '<div class="messages messages--warning">'. $this->t('No steps on this questionnaire.') .'</div>';
    }

    foreach ($steps as $step) {
      $html .= '<div class="crs-step">';
      $html .= '<h3 class="crs-step-title">'. htmlspecialchars($step['label']) .'</h3>';

      if ($step['widget'] === 'matrix') {
        $opts = $step['def']['options']; // columns
        $rows = $step['def']['rows'];    // rows

        $html .= '<table class="crs-likert"><thead><tr>';
        $html .= '<th width="50%">'. $this->t('Question') .'</th>';
        foreach ($opts as $lbl) {
          $html .= '<th class="text-center">'. htmlspecialchars($lbl) .'</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $r) {
          if ($r['type'] === 'heading') {
            $html .= '<tr class="crs-row-heading"><td colspan="'. (count($opts) + 1) .'"><div class="crs-heading">'
              . htmlspecialchars($r['label']) .'</div></td></tr>';
            continue;
          }
          $html .= '<tr class="crs-row-question">';
          $html .= '<td><span class="crs-question-label">'. htmlspecialchars($r['label']) .'</span></td>';
          foreach ($opts as $lbl) {
            $html .= '<td class="text-center"><input type="radio" disabled aria-label="'. htmlspecialchars($lbl) .'"></td>';
          }
          $html .= '</tr>';
        }

        $html .= '</tbody></table>';
      }
      elseif ($step['widget'] === 'radios') {
        $opts = (array) ($step['def']['options'] ?? []);
        if ($opts) {
          $html .= '<ul class="crs-preview-list">';
          foreach ($opts as $lbl) {
            $html .= '<li>'. htmlspecialchars($lbl) .'</li>';
          }
          $html .= '</ul>';
        }
      }
      elseif ($step['widget'] === 'textfield') {
        $html .= '<div class="crs-preview-field"><label>'. $this->t('Your answer') .'</label>'
          . '<input type="text" disabled class="crs-input-disabled"></div>';
      }
      else {
        $html .= '<div class="crs-preview-field"><label>'. $this->t('Your notes') .'</label>'
          . '<textarea rows="4" disabled class="crs-input-disabled"></textarea></div>';
      }

      $html .= '</div>'; // .crs-step
    }

    $html .= '</div>'; // .crs-preview

    $styles = '
      .crs-preview-header{margin:6px 0 16px}
      .crs-title{margin:0 0 6px}
      .crs-meta{color:#4b5563}
      .crs-step{margin:18px 0 26px}
      .crs-step-title{margin:.25rem 0 1rem}
      table.crs-likert{width:100%;border-collapse:collapse}
      table.crs-likert th, table.crs-likert td{border:1px solid #e5e7eb;padding:8px;vertical-align:middle}
      table.crs-likert .crs-heading{font-weight:600;color:#198754}
      .crs-question-label{display:block;max-width:980px}
      table.crs-likert input[type=radio]{display:block;margin:auto}
      .crs-preview-field label{display:block;margin-bottom:6px;font-weight:600}
      .crs-input-disabled{width:100%;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:4px;padding:8px}
      .crs-preview-list{margin:.25rem 0 .5rem 1.25rem}
    ';

    return [
      '#markup' => Markup::create($html . '<style>'. $styles .'</style>'),
      '#cache'  => ['max-age' => 0],
    ];
  }

  // ───────── helpers copied from the form (read-only) ─────────

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

  protected function extractScoreOptions(ParagraphInterface $questionnaire): array {
    $fallback = [
      'vgood' => (string) $this->t('Very Good'),
      'good'  => (string) $this->t('Good'),
      'avg'   => (string) $this->t('Average'),
      'poor'  => (string) $this->t('Poor'),
      'vpoor' => (string) $this->t('Very Poor'),
    ];
    if (!$questionnaire->hasField('field_options') || $questionnaire->get('field_options')->isEmpty()) {
      return $fallback;
    }
    $opts = [];
    
    
    foreach ($questionnaire->get('field_options')->referencedEntities() as $score_para) {
      if ($score_para instanceof \Drupal\paragraphs\ParagraphInterface) {
        // Label from your helper.
        $label = $this->extractLabel($score_para);
    
        // Get the numeric value from field_option_value.
        $value = $score_para->get('field_option_value')->value;
    
        $opts[$score_para->uuid()] = $label;
      }
    }
    
    /*foreach ($questionnaire->get('field_options')->referencedEntities() as $score_para) {
      if ($score_para instanceof ParagraphInterface) {
        $label = $this->extractLabel($score_para);
        if ($label !== '') { $opts[$score_para->uuid()] = $label; }
      }
    }*/
    return $opts ?: $fallback;
  }

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
  
   public function viewQuestionnaire($nid) {
    $node = Node::load($nid);

    if (!$node || !in_array($node->getType(), ['questionnaire', 'survey'])) {
	  throw new NotFoundHttpException('Questionnaire not found.');
	}


    // Extract all data from node.
    $data = [
      'title' => $node->label(),
      'created' => date('Y-m-d H:i:s', $node->getCreatedTime()),
      'author' => $node->getOwner()->getDisplayName(),
      'fields' => [],
    ];

    foreach ($node->getFields() as $field_name => $field) {
      if ($field->getFieldDefinition()->getType() === 'entity_reference_revisions') {
        $items = [];
        foreach ($field->referencedEntities() as $paragraph) {
          $items[] = $this->buildNestedArray($paragraph);
        }
        $data['fields'][$field_name] = $items;
      } else {
        $value = $field->value ?? '';
        if (!empty($value)) {
          $data['fields'][$field_name] = $value;
        }
      }
    }

    // Fetch options for headers.
    $options = [];
    if (!empty($data['fields']['field_options'])) {
      $options = array_map(function ($opt) {
        return [
          'title' => $opt['fields']['field_option_title'],
          'value' => $opt['fields']['field_option_value'] ?? '',
        ];
      }, $data['fields']['field_options']);
    }

    // Start HTML Output
    $html = '<div class="questionnaire-report">';
    //$html .= '<h2 class="report-title">' . htmlspecialchars($data['title']) . '</h2>';
    $html .= '<p class="report-meta"><strong>Created:</strong> ' . htmlspecialchars($data['created']) . ' | <strong>Author:</strong> ' . htmlspecialchars($data['author']) . '</p>';

    
	
	
	// Render categories recursively.
    if (!empty($data['fields']['field_survey'])) {
		$html .= '<table class="">';
		$html .= '<tr>';
		$html .= '<th>1. ABOUT YOURSELF</th>';
		  foreach ($data['fields']['field_survey'] as $survey) {
			$html .= $this->renderSurvey($survey);
		  }
		$html .= '</tr><tbody>';
	}
	
	
	$html .= '<table class="">';
    $html .= '<tr>';
    $html .= '<th width="50%">Question</th>';
    foreach ($options as $option) {
      $html .= '<th class="text-center">' . htmlspecialchars($option['title']) . '</th>';
    }
    $html .= '</tr>';
	

    // Render categories recursively.
    if (!empty($data['fields']['field_category'])) {
      foreach ($data['fields']['field_category'] as $category) {
        $html .= $this->renderCategory($category, $options);
      }
    }

    $html .= '</table></div>';

    return [
      '#markup' => Markup::create($html . $this->getCSS()),
      '#cache' => ['max-age' => 0],
    ];
  }

 

}
