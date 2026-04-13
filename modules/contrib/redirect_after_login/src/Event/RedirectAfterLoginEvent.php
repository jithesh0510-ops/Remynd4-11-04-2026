<?php

declare(strict_types=1);

namespace Drupal\redirect_after_login\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event that is fired when a user is redirected after logging in.
 */
class RedirectAfterLoginEvent extends Event {

  /**
   * Whether or not to perform the redirect.
   */
  protected bool $redirect = TRUE;

  /**
   * The redirect URL.
   */
  protected string $url;

  /**
   * Constructs a new RedirectAfterLoginEvent.
   *
   * @param string $url
   *   The redirect URL.
   */
  public function __construct(string $url) {
    $this->url = $url;
  }

  /**
   * Returns the redirect URL.
   *
   * @return string
   *   The redirect URL.
   */
  public function getUrl(): string {
    return $this->url;
  }

  /**
   * Sets the redirect URL.
   *
   * @param string $url
   *   The redirect URL.
   *
   * @return \Drupal\redirect_after_login\Event\RedirectAfterLoginEvent
   *   The event, for chaining.
   */
  public function setUrl(string $url): self {
    $this->url = $url;
    return $this;
  }

  /**
   * Returns whether or not to perform the redirect.
   *
   * @return bool
   *   Whether or not to perform the redirect.
   */
  public function isRedirectAllowed(): bool {
    return $this->redirect;
  }

  /**
   * Sets whether or not to perform the redirect.
   *
   * @param bool $redirect
   *   Whether or not to perform the redirect.
   *
   * @return \Drupal\redirect_after_login\Event\RedirectAfterLoginEvent
   *   The event, for chaining.
   */
  public function setRedirectAllowed(bool $redirect): self {
    $this->redirect = $redirect;
    return $this;
  }

}
