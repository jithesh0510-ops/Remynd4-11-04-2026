<?php

namespace Drupal\coach_reporting_system\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form class for upload forms with common functionality.
 */
abstract class BaseUploadForm extends FormBase {

  protected EntityTypeManagerInterface $entityTypeManager;

  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * Get company options for the dropdown.
   */
  protected function getCompanyOptions(?array $limit_uids = NULL): array {
    $options = [];
    $query = $this->entityTypeManager->getStorage('user')->getQuery()
      ->condition('status', 1)
      ->condition('roles', 'company')
      ->accessCheck(TRUE)
      ->sort('name');
    if (!empty($limit_uids)) {
      $query->condition('uid', $limit_uids, 'IN');
    }
    $uids = $query->execute();
    if (!$uids) { return $options; }

    $accounts = $this->entityTypeManager->getStorage('user')->loadMultiple($uids);
    foreach ($accounts as $account) {
      $full_name = ($account->hasField('field_full_name') && !$account->get('field_full_name')->isEmpty())
        ? trim((string) $account->get('field_full_name')->value) : $account->label();
      $email = method_exists($account, 'getEmail') ? $account->getEmail() : ($account->get('mail')->value ?? '');
      $label = $email ? sprintf('%s (%s)', $full_name, $email) : $full_name;
      $options[$account->id()] = $label;
    }
    asort($options, SORT_NATURAL | SORT_FLAG_CASE);
    return $options;
  }

  /**
   * Get questionnaires by company.
   */
  protected function getQuestionnairesByCompany(int $company_uid): array {
    $options = [];
    $company_account = $this->entityTypeManager->getStorage('user')->load($company_uid);
    if (!$company_account) { return $options; }

    $profile_storage = $this->entityTypeManager->getStorage('profile');
    $profile = $profile_storage->loadByUser($company_account, 'company', TRUE);
    if (is_array($profile)) {
      $profile = reset($profile) ?: NULL;
    }
    if (!$profile) {
      $pids = $profile_storage->getQuery()
        ->condition('uid', $company_uid)
        ->condition('type', 'company')
        ->condition('status', 1)
        ->sort('created', 'DESC')
        ->range(0, 1)
        ->accessCheck(TRUE)
        ->execute();
      $profile = $pids ? $profile_storage->load(reset($pids)) : NULL;
      if (!$profile) { return $options; }
    }

    if (
      !$profile->hasField('field_select_questionnaire') ||
      $profile->get('field_select_questionnaire')->isEmpty()
    ) { return $options; }

    $qids = [];
    foreach ($profile->get('field_select_questionnaire')->referencedEntities() as $para) {
      if ($para->hasField('field_questionnaire') && !$para->get('field_questionnaire')->isEmpty()) {
        $node = $para->get('field_questionnaire')->entity;
        if ($node && $node->bundle() === 'questionnaire') {
          $qids[$node->id()] = $node->id();
        }
      }
    }
    if (!$qids) { return $options; }

    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple(array_values($qids));
    foreach ($nodes as $node) {
      $options[$node->id()] = $node->label();
    }
    asort($options, SORT_NATURAL | SORT_FLAG_CASE);
    return $options;
  }

  /**
   * Get current user's role-based company filtering.
   */
  protected function getCurrentUserCompanyFilter(): array {
    $current_user = \Drupal::currentUser();
    $current_uid = (int) $current_user->id();
    $roles = $current_user->getRoles(TRUE);
    $is_admin = in_array('administrator', $roles, TRUE) || $current_user->hasPermission('administer users');
    $is_company = !$is_admin && in_array('company', $roles, TRUE);

    $company_whitelist = NULL;
    if ($is_company) {
      $company_whitelist = [$current_uid];
    }

    return [
      'whitelist' => $company_whitelist,
      'is_company' => $is_company,
      'current_uid' => $current_uid,
    ];
  }

  /**
   * AJAX callback to update program options.
   */
  public function updateProgramOptions(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $form_id = $this->getFormId();
    
    // Determine which form is calling this based on the triggering element name
    if (strpos($triggering_element['#name'], 'prepost_company') === 0) {
      return $form['prepost_program'];
    } elseif (strpos($triggering_element['#name'], 'otj_company') === 0) {
      return $form['otj_program'];
    }
    
    // Fallback to generic program wrapper
    return $form['program'] ?? $form['prepost_program'] ?? $form['otj_program'];
  }

  /**
   * Validate CSV file format and content.
   */
  protected function validateCsvFile($fids, FormStateInterface $form_state, $field_name, $required_columns = []) {
    if (empty($fids)) {
      return;
    }

    $file_storage = $this->entityTypeManager->getStorage('file');
    foreach ($fids as $fid) {
      $file = $file_storage->load($fid);
      if (!$file) {
        $form_state->setErrorByName($field_name, $this->t('File not found.'));
        continue;
      }

      $file_path = \Drupal::service('file_system')->realpath($file->getFileUri());
      if (!$file_path || !file_exists($file_path)) {
        $form_state->setErrorByName($field_name, $this->t('File path not accessible.'));
        continue;
      }

      // Check file size (max 25MB)
      $file_size = filesize($file_path);
      if ($file_size > 25600000) {
        $form_state->setErrorByName($field_name, $this->t('File size exceeds 25MB limit.'));
        continue;
      }

      // Basic CSV validation
      $handle = fopen($file_path, 'r');
      if (!$handle) {
        $form_state->setErrorByName($field_name, $this->t('Cannot read the uploaded file.'));
        continue;
      }

      $first_row = fgetcsv($handle);
      fclose($handle);

      if (!$first_row) {
        $form_state->setErrorByName($field_name, $this->t('The uploaded file appears to be empty or invalid.'));
        continue;
      }

      // Check for required columns if specified
      if (!empty($required_columns)) {
        $missing_columns = [];
        foreach ($required_columns as $column) {
          if (!in_array($column, $first_row)) {
            $missing_columns[] = $column;
          }
        }

        if (!empty($missing_columns)) {
          $form_state->setErrorByName($field_name, $this->t('Missing required columns: @columns', [
            '@columns' => implode(', ', $missing_columns)
          ]));
        }
      }
    }
  }

  /**
   * Validate company and program selection.
   */
  protected function validateCompanyProgram(FormStateInterface $form_state, $company_field, $program_field) {
    $company_id = $form_state->getValue($company_field);
    $program_id = $form_state->getValue($program_field);

    if (empty($company_id)) {
      $form_state->setErrorByName($company_field, $this->t('Please select a company.'));
      return;
    }

    if (empty($program_id)) {
      $form_state->setErrorByName($program_field, $this->t('Please select a program.'));
      return;
    }

    // Validate company exists and user has access
    $company = $this->entityTypeManager->getStorage('user')->load($company_id);
    if (!$company || !$company->hasRole('company')) {
      $form_state->setErrorByName($company_field, $this->t('Invalid company selected.'));
      return;
    }

    // Validate program exists and belongs to company
    $program_options = $this->getQuestionnairesByCompany((int) $company_id);
    if (!array_key_exists($program_id, $program_options)) {
      $form_state->setErrorByName($program_field, $this->t('Invalid program selected for this company.'));
      return;
    }
  }

}
