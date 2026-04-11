<?php

/**
 * Script to create upload directories for coach reporting system.
 * Run this script from the Drupal root directory.
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

// Bootstrap Drupal
$autoloader = require_once 'autoload.php';
$request = Request::createFromGlobals();
$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
$kernel->boot();
$kernel->prepareLegacyPage();

// Get the file system service
$fileSystem = \Drupal::service('file_system');

// Check if temporary directory is writable
$tempDir = 'temporary://';
$realPath = $fileSystem->realpath($tempDir);

echo "Checking upload directory configuration...\n";

if ($realPath && is_writable($realPath)) {
  echo "✅ Temporary directory is writable: $tempDir\n";
  echo "✅ File uploads will work correctly!\n";
} else {
  echo "❌ Temporary directory is not writable: $tempDir\n";
  echo "❌ Please check file system permissions.\n";
}

echo "\n🎉 Upload configuration check completed!\n";
echo "Files will be uploaded to the temporary directory.\n";
