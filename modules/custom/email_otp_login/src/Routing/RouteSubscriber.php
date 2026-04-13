<?php

namespace Drupal\email_otp_login\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

class RouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('user.login')) {
      $route->setDefault('_form', '\Drupal\email_otp_login\Form\EmailOtpLoginForm');
      $route->setDefault('_title', 'Login with Email OTP');
    }
  }

}
