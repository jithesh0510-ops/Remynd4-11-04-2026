<?php

namespace Drupal\remynd4_user_actions\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

final class CoachImportPageController extends ControllerBase {

  public function page(Request $request): array {
    $company = (int) $request->query->get('company');

    $items = [];
    $items[] = Link::fromTextAndUrl(
      'Download coach import sample CSV',
      Url::fromUserInput('/coach/importcsv/sample')
    )->toRenderable();

    $coach_key_url = '/coach/importcsv/coach-key' . ($company ? ('?company=' . $company) : '');
    $items[] = Link::fromTextAndUrl(
      'Download coach key CSV' . ($company ? " (company=$company)" : ''),
      Url::fromUserInput($coach_key_url)
    )->toRenderable();

    return [
      '#type' => 'container',
      'intro' => ['#markup' => '<h2>Coach Import CSV</h2><p>Use the links below.</p>'],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
    ];
  }

}
