<?php

namespace Drupal\coach_reporting_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for serving sample CSV files.
 */
class SampleFileController extends ControllerBase {

  /**
   * Serve sample CSV files.
   */
  public function downloadSample($filename) {
    $module_path = \Drupal::service('extension.list.module')->getPath('coach_reporting_system');
    $file_path = $module_path . '/samples/' . $filename;
    
    // Security check - only allow specific CSV files
    $allowed_files = [
      'pre-CPSM.csv',
      'pre-CSC.csv', 
      'prepost-training-sample.csv',
      'on-job-progress-sample.csv',
      'employee-key.csv'
    ];
    
    if (!in_array($filename, $allowed_files)) {
      throw new NotFoundHttpException('File not found.');
    }
    
    if (!file_exists($file_path)) {
      throw new NotFoundHttpException('Sample file not found.');
    }
    
    $response = new BinaryFileResponse($file_path);
    $response->setContentDisposition('attachment', $filename);
    $response->headers->set('Content-Type', 'text/csv');
    
    return $response;
  }

}
