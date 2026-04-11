<?php

namespace Drupal\persistent_login;

use Drupal\Component\Utility\Crypt;

/**
 * A Persistent Token with unhashed keys.
 */
class RawPersistentToken extends PersistentToken {

  /**
   * {@inheritdoc}
   */
  public function getRawSeries(): string {
    return $this->getSeries();
  }

  /**
   * {@inheritdoc}
   */
  public function getHashedSeries(): string {
    return Crypt::hashBase64($this->getSeries());
  }

  /**
   * {@inheritdoc}
   */
  public function getRawInstance(): string {
    return $this->getInstance();
  }

  /**
   * {@inheritdoc}
   */
  public function getHashedInstance(): string {
    return Crypt::hashBase64($this->getInstance());
  }

}
