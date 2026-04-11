<?php

namespace Drupal\Tests\views_filters_populate\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\Views;

/**
 * Tests basic functionality of the populate filter.
 *
 * @group views_filters_populate
 */
final class FilterTest extends BrowserTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static array $testViews = ['test_populate_filter'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'views',
    'views_filters_populate',
    'views_filters_populate_test_config',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);

    $field_names = ['field_foo', 'field_bar'];
    foreach ($field_names as $field_name) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => 'integer',
        'cardinality' => 1,
      ])->save();
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => 'page',
        'label' => $field_name . '_label',
      ])->save();
    }

    // Create a view that already has a filter populated from field_foo and
    // field bar.
    ViewTestData::createTestViews(FilterTest::class, ['views_filters_populate_test_config']);

    // Create some test content.
    $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Page 1',
      'field_foo' => 1,
      'field_bar' => 2,
    ]);
    $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Page 2',
      'field_foo' => 2,
      'field_bar' => 1,
    ]);
    $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Page 3',
      'field_foo' => 3,
      'field_bar' => 4,
    ]);
  }

  /**
   * Test the filter.
   */
  public function testFilter(): void {
    // We have three nodes. First execute the view with no value in our filter,
    // so we get all results.
    $view = Views::getView('test_populate_filter');
    $view->build('page_1');
    $view->execute('page_1');
    $this->assertEquals(3, count($view->result));

    // Then execute the view with the filter set to 2 - the results should
    // include Page 1 and 2 which use the populated filter to search across
    // both field_foo and field_bar.
    $view = Views::getView('test_populate_filter');
    $view->setExposedInput(['populate' => 2]);
    $view->build('page_1');
    $view->execute('page_1');
    $this->assertEquals(2, count($view->result));
  }

}
