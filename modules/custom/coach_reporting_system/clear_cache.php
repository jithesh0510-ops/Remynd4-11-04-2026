<?php
/**
 * Simple cache clear script for Drupal
 * Run this from your Drupal root directory
 */

// Bootstrap Drupal
$autoloader = require_once 'autoload.php';
\Drupal\Core\DrupalKernel::bootEnvironment();

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$kernel = \Drupal\Core\DrupalKernel::createFromRequest($request, $autoloader, 'prod');
$kernel->boot();
$kernel->preHandle($request);

// Clear all caches
$container = \Drupal::getContainer();
$container->get('cache_tags.invalidator')->invalidateTags(['*']);
$container->get('cache.discovery')->deleteAll();
$container->get('cache.config')->deleteAll();
$container->get('cache.container')->deleteAll();
$container->get('cache.bootstrap')->deleteAll();
$container->get('cache.default')->deleteAll();

// Clear Twig cache
$twig_cache_dir = 'sites/default/files/php/twig';
if (is_dir($twig_cache_dir)) {
    $files = glob($twig_cache_dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

echo "Cache cleared successfully!\n";
echo "You can now access your site normally.\n";
