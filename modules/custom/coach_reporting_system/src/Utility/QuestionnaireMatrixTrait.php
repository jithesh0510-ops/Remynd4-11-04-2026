<?php

namespace Drupal\coach_reporting_system\Utility;

use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Shared helpers to extract a questionnaire "matrix" definition
 * (score options, headings, questions) from Paragraphs content.
 */
trait QuestionnaireMatrixTrait {

  protected function buildStepsFromField(NodeInterface $node, string $field_name): array {
    $steps = [];
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) { return $steps; }
    foreach ($node->get($field_name)->referencedEntities() as $para) {
      if ($para instanceof ParagraphInterface) {
        $step = $this->stepFromParagraph($para);
        if ($step) { $steps[] = $step; }
      }
    }
    return $steps;
  }

  protected function stepFromParagraph(ParagraphInterface $p): array {
    $bundle = strtolower(str_replace([' ', '-'], '_', $p->bundle()));
    $label  = $this->extractLabel($p) ?: ucfirst(str_replace('_', ' ', $bundle));
    if ($bundle === 'questionnaire') {
      return [
        'widget' => 'matrix',
        'def' => [
          'options' => $this->extractScoreOptions($p),
          'rows'    => $this->collectRowsFromCategories($p),
        ],
        'label' => $label,
      ];
    }
    return [];
  }

  protected function extractScoreOptions(ParagraphInterface $questionnaire): array {
    $opts = [];
    if ($questionnaire->hasField('field_options') && !$questionnaire->get('field_options')->isEmpty()) {
      foreach ($questionnaire->get('field_options')->referencedEntities() as $score_para) {
        $label = $this->extractLabel($score_para);
        $value = $score_para->get('field_option_value')->value;
        $opts[$value] = $label;
      }
    }
    if (!$opts) { $opts = ['100'=>'100','75'=>'75','50'=>'50','25'=>'25','0'=>'0']; }
    krsort($opts, SORT_NUMERIC);
    return $opts;
  }

  protected function collectRowsFromCategories(ParagraphInterface $questionnaire): array {
    $rows = [];
    if ($questionnaire->hasField('field_category') && !$questionnaire->get('field_category')->isEmpty()) {
      foreach ($questionnaire->get('field_category')->referencedEntities() as $cat) {
        $this->collectCategoryDeep($cat, $rows);
      }
    }
    return $rows;
  }

  protected function collectCategoryDeep(ParagraphInterface $p, array &$rows): void {
    $label = $this->extractLabel($p);
    $children = [];
    foreach ($p->getFieldDefinitions() as $fname => $def) {
      if ($def->getType() === 'entity_reference_revisions'
        && ($def->getSettings()['target_type'] ?? NULL) === 'paragraph'
        && $p->hasField($fname) && !$p->get($fname)->isEmpty()) {
        foreach ($p->get($fname)->referencedEntities() as $ref) {
          if ($ref instanceof ParagraphInterface) { $children[] = $ref; }
        }
      }
    }
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

  protected function extractLabel(ParagraphInterface $p): string {
    foreach ([
      'field_category','field_question','field_title','field_label',
      'field_heading','field_name','field_text','field_description','field_option_title'
    ] as $f) {
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
