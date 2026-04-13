<?php

namespace Drupal\Tests\menu_export\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * @group menu_export
 */
class MenuExportConfigurationFormTest extends BrowserTestBase {

  protected static $modules = ['menu_export'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  public function testFormLoad(): void {
    $user = $this->drupalCreateUser();
    $this->drupalLogin($user);

    $this->drupalGet('/admin/config/development/menu_export');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('/admin/config/development/menu_export/import');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('/admin/config/development/menu_export/export');
    $this->assertSession()->statusCodeEquals(403);

    $user = $this->drupalCreateUser(['export and import menu links']);
    $this->drupalLogin($user);

    $this->drupalGet('/admin/config/development/menu_export');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('/admin/config/development/menu_export/import');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('/admin/config/development/menu_export/export');
    $this->assertSession()->statusCodeEquals(200);
  }
}

