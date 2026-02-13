<?php

namespace Drupal\persistent_login;

/**
 * A Persistent Token with hashed keys.
 */
class HashedPersistentToken extends PersistentToken {

  /**
   * {@inheritdoc}
   */
  public function getHashedSeries(): string {
    return $this->getSeries();
  }

  /**
   * {@inheritdoc}
   */
  public function getHashedInstance(): string {
    return $this->getInstance();
  }

}
