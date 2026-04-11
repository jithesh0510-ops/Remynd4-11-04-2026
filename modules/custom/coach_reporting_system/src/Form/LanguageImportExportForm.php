<?php

namespace Drupal\coach_reporting_system\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Language import/export: export interface strings to CSV, import translations from CSV.
 *
 * Requires the Locale module for full functionality. Export produces source strings
 * (and existing translations if a language is selected). Import creates/updates
 * locale translations from CSV.
 */
class LanguageImportExportForm extends FormBase {

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  public function __construct(LanguageManagerInterface $language_manager) {
    $this->languageManager = $language_manager;
  }

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('language_manager')
    );
  }

  public function getFormId(): string {
    return 'coach_reporting_system_language_import_export';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $locale_enabled = \Drupal::moduleHandler()->moduleExists('locale');

    if (!$locale_enabled) {
      $form['message'] = [
        '#markup' => '<p>' . $this->t('The <a href=":url">Locale module</a> is not enabled. Enable it to export and import interface translations.', [
          ':url' => '/admin/modules',
        ]) . '</p>',
        '#weight' => -10,
      ];
      return $form;
    }

    $languages = $this->languageManager->getLanguages();
    $options = ['_source' => $this->t('Source only (no translations)')];
    foreach ($languages as $langcode => $language) {
      if ($langcode !== 'en') {
        $options[$langcode] = $language->getName();
      }
    }

    $form['export'] = [
      '#type' => 'details',
      '#title' => $this->t('Export translations to CSV'),
      '#open' => TRUE,
      '#description' => $this->t('Export interface strings and (optionally) translations for a language. CSV columns: source, context, language, translation.'),
    ];
    $form['export']['export_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $options,
      '#default_value' => '_source',
    ];
    $form['export']['export_actions'] = [
      '#type' => 'actions',
    ];
    $form['export']['export_actions']['submit_export'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export CSV'),
      '#submit' => ['::submitExport'],
      '#name' => 'op_export',
    ];

    $form['import'] = [
      '#type' => 'details',
      '#title' => $this->t('Import translations from CSV'),
      '#open' => TRUE,
      '#description' => $this->t('Upload a CSV with columns: source, context, language (langcode), translation. Existing translations for the same source/context/language will be updated.'),
    ];
    $form['import']['import_csv'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('CSV file'),
      '#upload_location' => 'temporary://',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
        'file_validate_size' => [10 * 1024 * 1024],
      ],
    ];
    $form['import']['import_actions'] = [
      '#type' => 'actions',
    ];
    $form['import']['import_actions']['submit_import'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import CSV'),
      '#submit' => ['::submitImport'],
      '#name' => 'op_import',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Export handler: generate CSV and send as download.
   */
  public function submitExport(array &$form, FormStateInterface $form_state): void {
    if (!\Drupal::moduleHandler()->moduleExists('locale')) {
      return;
    }
    $langcode = $form_state->getValue('export_language');
    $response = $this->buildExportResponse($langcode);
    if ($response) {
      $form_state->setResponse($response);
    } else {
      $this->messenger()->addWarning($this->t('No strings to export.'));
    }
  }

  /**
   * Build CSV export response for locale strings.
   */
  protected function buildExportResponse(string $langcode): ?Response {
    $storage = \Drupal::database();
    if (!$storage->schema()->tableExists('locales_source')) {
      return NULL;
    }
    $query = $storage->select('locales_source', 's')->fields('s', ['lid', 'source', 'context']);
    $query->orderBy('s.lid');
    $result = $query->execute();

    $response = new StreamedResponse(function () use ($storage, $result, $langcode) {
      $handle = fopen('php://output', 'w');
      if (!$handle) {
        return;
      }
      fwrite($handle, "\xEF\xBB\xBF");
      fputcsv($handle, ['source', 'context', 'language', 'translation'], ',');

      $targets = [];
      if ($langcode !== '_source' && $storage->schema()->tableExists('locales_target')) {
        $target_query = $storage->select('locales_target', 't')
          ->fields('t', ['lid', 'translation'])
          ->condition('t.language', $langcode);
        foreach ($target_query->execute() as $row) {
          $targets[$row->lid] = $row->translation;
        }
      }

      foreach ($result as $row) {
        $translation = ($langcode !== '_source' && isset($targets[$row->lid])) ? $targets[$row->lid] : '';
        fputcsv($handle, [
          $row->source,
          $row->context ?? '',
          $langcode === '_source' ? '' : $langcode,
          $translation,
        ], ',');
      }
      fclose($handle);
    });

    $filename = $langcode === '_source' ? 'locale-source.csv' : 'locale-' . $langcode . '.csv';
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    return $response;
  }

  /**
   * Import handler: parse CSV and update locale_target.
   */
  public function submitImport(array &$form, FormStateInterface $form_state): void {
    if (!\Drupal::moduleHandler()->moduleExists('locale')) {
      return;
    }
    $fids = $form_state->getValue('import_csv', []);
    if (empty($fids)) {
      $this->messenger()->addWarning($this->t('Please upload a CSV file.'));
      return;
    }
    $file = \Drupal::entityTypeManager()->getStorage('file')->load(reset($fids));
    if (!$file) {
      $this->messenger()->addError($this->t('File not found.'));
      return;
    }
    $path = \Drupal::service('file_system')->realpath($file->getFileUri());
    if (!$path || !is_readable($path)) {
      $this->messenger()->addError($this->t('Cannot read file.'));
      return;
    }

    $rows = \Drupal::service('coach_reporting_system.csv_importer')->readAssoc($path);
    if (empty($rows)) {
      $this->messenger()->addWarning($this->t('CSV has no data rows.'));
      return;
    }

    $db = \Drupal::database();
    if (!$db->schema()->tableExists('locales_source') || !$db->schema()->tableExists('locales_target')) {
      $this->messenger()->addError($this->t('Locale tables not found.'));
      return;
    }

    $updated = 0;
    $inserted = 0;
    foreach ($rows as $row) {
      $source = trim($row['source'] ?? '');
      $context = trim($row['context'] ?? '');
      $language = trim($row['language'] ?? $row['langcode'] ?? '');
      $translation = trim($row['translation'] ?? '');
            if ($source === '' || $language === '' || $translation === '') {
        continue;
      }
      $lid = $db->select('locales_source', 's')
        ->fields('s', ['lid'])
        ->condition('s.source', $source)
        ->condition('s.context', $context)
        ->execute()
        ->fetchField();
      if (!$lid) {
        $db->insert('locales_source')->fields(['source' => $source, 'context' => $context])->execute();
        $lid = $db->select('locales_source', 's')
          ->fields('s', ['lid'])
          ->condition('source', $source)
          ->condition('context', $context)
          ->orderBy('lid', 'DESC')
          ->range(0, 1)
          ->execute()
          ->fetchField();
      }
      if (!$lid) {
        continue;
      }
      $existing = $db->select('locales_target', 't')
        ->fields('t', ['lid'])
        ->condition('t.lid', $lid)
        ->condition('t.language', $language)
        ->execute()
        ->fetchField();
      if ($existing) {
        $db->update('locales_target')
          ->fields(['translation' => $translation])
          ->condition('lid', $lid)
          ->condition('language', $language)
          ->execute();
        $updated++;
      } else {
        $db->insert('locales_target')->fields([
          'lid' => $lid,
          'language' => $language,
          'translation' => $translation,
        ])->execute();
        $inserted++;
      }
    }

    $this->messenger()->addStatus($this->t('Import complete: @inserted new, @updated updated.', [
      '@inserted' => $inserted,
      '@updated' => $updated,
    ]));
    $form_state->setRebuild(TRUE);
  }
}
