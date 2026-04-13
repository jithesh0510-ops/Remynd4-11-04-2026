<?php

namespace Drupal\views_filters_populate\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\HandlerBase;

/**
 * Filter mock class.
 *
 * Takes care of removing populated filters from the view if the populated value
 * is empty and exposed.
 */
class PopulateRemoveEmptyFilterMock extends HandlerBase {

  /**
   * Handler.
   *
   * @var \Drupal\views\Plugin\views\filter\FilterPluginBase
   */
  private FilterPluginBase $viewsFiltersPopulateHandlerCaller;

  /**
   * {@inheritdoc}
   */
  public function __construct(FilterPluginBase $handler) {
    $this->viewsFiltersPopulateHandlerCaller = $handler;
  }

  /**
   * {@inheritdoc}
   */
  public function preQuery() {
    $handler = $this->viewsFiltersPopulateHandlerCaller;
    foreach ($handler->options['filters'] as $id) {
      unset($handler->view->filter[$id]);
    }
    foreach ($handler->view->filter as $k => $filter) {
      if ($filter === $this) {
        unset($handler->view->filter[$k]);
      }
    }
  }

}
