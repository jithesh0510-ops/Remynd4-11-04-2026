<?php

namespace Drupal\coach_reporting_system\Command;

use Drupal\Console\Core\Command\Command;
use Drupal\coach_reporting_system\Service\FileUploadService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Drush command to create upload directories.
 */
class CreateUploadDirectoriesCommand extends Command {

  protected FileUploadService $fileUploadService;

  public function __construct(FileUploadService $file_upload_service) {
    parent::__construct();
    $this->fileUploadService = $file_upload_service;
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('coach-reporting:create-upload-dirs')
      ->setDescription('Create upload directories for coach reporting system');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    try {
      $this->fileUploadService->ensureAllDirectoriesExist();
      $output->writeln('<info>✅ Upload directories created successfully!</info>');
      return 0;
    } catch (\Exception $e) {
      $output->writeln('<error>❌ Error creating upload directories: ' . $e->getMessage() . '</error>');
      return 1;
    }
  }

}
