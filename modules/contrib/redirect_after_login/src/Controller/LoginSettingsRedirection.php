<?php

namespace Drupal\redirect_after_login\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Login Settings Redirection class.
 */
class LoginSettingsRedirection extends ControllerBase {

  /**
   * Old settings form url redirection.
   */
  public function settingsRedirect() {
    return $this->redirect('redirect_after_login.admin_settings');
  }

}
