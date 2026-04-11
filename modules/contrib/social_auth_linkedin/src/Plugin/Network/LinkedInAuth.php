<?php

namespace Drupal\social_auth_linkedin\Plugin\Network;

use Drupal\social_auth\Plugin\Network\NetworkBase;

/**
 * Defines a Network Plugin for Social Auth LinkedIn.
 *
 * @package Drupal\social_auth_linkedin\Plugin\Network
 *
 * @Network(
 *   id = "social_auth_linkedin",
 *   short_name = "linkedin",
 *   social_network = "LinkedIn",
 *   img_path = "img/linkedin_logo.svg",
 *   type = "social_auth",
 *   class_name = "\League\OAuth2\Client\Provider\LinkedIn",
 *   auth_manager = "\Drupal\social_auth_linkedin\LinkedInAuthManager",
 *   routes = {
 *     "redirect": "social_auth.network.redirect",
 *     "callback": "social_auth.network.callback",
 *     "settings_form": "social_auth.network.settings_form",
 *   },
 *   handlers = {
 *     "settings": {
 *       "class": "\Drupal\social_auth\Settings\SettingsBase",
 *       "config_id": "social_auth_linkedin.settings"
 *     }
 *   }
 * )
 */
class LinkedInAuth extends NetworkBase {}
