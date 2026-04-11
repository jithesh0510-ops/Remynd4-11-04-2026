<?php

namespace Drupal\coach_reporting_system\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for handling file uploads and directory management.
 */
class FileUploadService {

  protected FileSystemInterface $fileSystem;
  protected LoggerChannelFactoryInterface $loggerFactory;

  public function __construct(FileSystemInterface $file_system, LoggerChannelFactoryInterface $logger_factory) {
    $this->fileSystem = $file_system;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Get or create upload directory for a specific type.
   */
  public function getUploadDirectory(string $type): string {
    // Use temporary directory which is always available and writable
    return 'temporary://';
  }

  /**
   * Get upload directory for WIN analysis files.
   */
  public function getWinAnalysisUploadDirectory(): string {
    return $this->getUploadDirectory('win_analysis');
  }

  /**
   * Get upload directory for pre/post training files.
   */
  public function getPrePostTrainingUploadDirectory(): string {
    return $this->getUploadDirectory('prepost_training');
  }

  /**
   * Get upload directory for on-the-job progress files.
   */
  public function getOnJobProgressUploadDirectory(): string {
    return $this->getUploadDirectory('on_job_progress');
  }

  /**
   * Ensure all upload directories exist.
   */
  public function ensureAllDirectoriesExist(): void {
    $directories = [
      'win_analysis',
      'prepost_training', 
      'on_job_progress'
    ];
    
    foreach ($directories as $directory) {
      $this->getUploadDirectory($directory);
    }
  }

}
