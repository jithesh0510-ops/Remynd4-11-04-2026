<?php

namespace Drupal\crs_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Legacy CRS sync routes (dashboard redirects to the unified admin form).
 */
class SyncController extends ControllerBase {

  public static function create(ContainerInterface $container): self {
    return new static();
  }

  /**
   * Redirects the legacy dashboard URL to the unified admin form.
   */
  public function dashboard(): TrustedRedirectResponse {
    $url = Url::fromRoute('crs_sync.operations', [], ['absolute' => TRUE])->toString();
    return new TrustedRedirectResponse($url);
  }

}
