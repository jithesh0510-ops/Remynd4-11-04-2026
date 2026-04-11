<?php

namespace Drupal\Tests\remove_username\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Class RemoveUsernameTest. The base class for testing username.
 */
class RemoveUsernameTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user', 'remove_username'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test removing username.
   */
  public function testRemoveUsername() {
    // Check the status of the user login page.
    $this->drupalGet('user/login');
    $this->assertSession()->statusCodeEquals(200);

    // Check field labels.
    $this->assertSession()->pageTextContains('Email address');
    $this->assertSession()->pageTextNotContains('Username');

    // Check the status of the user register page.
    $this->drupalGet('user/register');
    $this->assertSession()->statusCodeEquals(200);

    // Check field labels.
    $this->assertSession()->pageTextContains('Email address');
    $this->assertSession()->pageTextNotContains('Username');

    // Check creating a new user account.
    $user = $this->drupalCreateUser([], NULL, FALSE, ['mail' => 'username@test.com']);
    $this->assertEquals('username@test.com', $user->getDisplayName());
  }

}
