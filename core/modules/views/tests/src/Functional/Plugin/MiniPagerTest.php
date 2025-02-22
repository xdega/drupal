<?php

namespace Drupal\Tests\views\Functional\Plugin;

use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\Views;

/**
 * Tests the mini pager plugin.
 *
 * @group views
 * @see \Drupal\views\Plugin\views\pager\Mini
 */
class MiniPagerTest extends ViewTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_mini_pager'];

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['node'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Nodes used by the test.
   *
   * @var array
   */
  protected $nodes;

  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    $this->drupalCreateContentType(['type' => 'page']);
    // Create a bunch of test nodes.
    for ($i = 0; $i < 20; $i++) {
      $this->nodes[] = $this->drupalCreateNode();
    }
  }

  /**
   * Tests the rendering of mini pagers.
   */
  public function testMiniPagerRender() {
    // On first page, current page and next page link appear, previous page link
    // does not.
    $this->drupalGet('test_mini_pager');
    $this->assertText('›› test');
    $this->assertText('Page 1');
    $this->assertNoText('‹‹ test');
    $this->assertText($this->nodes[0]->label());
    $this->assertText($this->nodes[1]->label());
    $this->assertText($this->nodes[2]->label());

    // On second page, current page and previous/next page links appear.
    $this->drupalGet('test_mini_pager', ['query' => ['page' => 1]]);
    $this->assertText('‹‹ test');
    $this->assertText('Page 2');
    $this->assertText('›› test');
    $this->assertText($this->nodes[3]->label());
    $this->assertText($this->nodes[4]->label());
    $this->assertText($this->nodes[5]->label());

    // On last page, current page and previous page link appear, next page link
    // does not.
    $this->drupalGet('test_mini_pager', ['query' => ['page' => 6]]);
    $this->assertNoText('›› test');
    $this->assertText('Page 7');
    $this->assertText('‹‹ test');
    $this->assertText($this->nodes[18]->label());
    $this->assertText($this->nodes[19]->label());

    // Test @total value in result summary
    $view = Views::getView('test_mini_pager');
    $view->setDisplay('page_4');
    $this->executeView($view);
    $this->assertTrue($view->get_total_rows, 'The query was set to calculate the total number of rows.');
    $this->assertSame(count($this->nodes), (int) $view->total_rows, 'The total row count is equal to the number of nodes.');

    $this->drupalGet('test_mini_pager_total', ['query' => ['page' => 1]]);
    $this->assertText('of ' . count($this->nodes));
    $this->drupalGet('test_mini_pager_total', ['query' => ['page' => 6]]);
    $this->assertText('of ' . count($this->nodes));

    // Test a mini pager with just one item per page.
    $this->drupalGet('test_mini_pager_one');
    $this->assertText('››');
    $this->assertText('Page 1');
    $this->assertText($this->nodes[0]->label());

    $this->drupalGet('test_mini_pager_one', ['query' => ['page' => 1]]);
    $this->assertText('‹‹');
    $this->assertText('Page 2');
    $this->assertText('››');
    $this->assertText($this->nodes[1]->label());

    $this->drupalGet('test_mini_pager_one', ['query' => ['page' => 19]]);
    $this->assertNoText('››');
    $this->assertText('Page 20');
    $this->assertText('‹‹');
    $this->assertText($this->nodes[19]->label());

    // Test a mini pager with all items on the page. No pager should display.
    $this->drupalGet('test_mini_pager_all');
    $this->assertNoText('‹‹ test');
    $this->assertNoText('Page 1');
    $this->assertNoText('test ››');
    $result = $this->xpath('//div[contains(@class, "views-row")]');
    $this->assertSame(count($this->nodes), count($result), 'All rows appear on the page.');

    // Remove all items beside 1, so there should be no links shown.
    for ($i = 0; $i < 19; $i++) {
      $this->nodes[$i]->delete();
    }

    $this->drupalGet('test_mini_pager');
    $this->assertNoText('‹‹ test');
    $this->assertNoText('Page 1');
    $this->assertNoText('‹‹ test');
    $this->assertText($this->nodes[19]->label());

    $view = Views::getView('test_mini_pager');
    $this->executeView($view);
    $this->assertIdentical($view->get_total_rows, NULL, 'The query was not forced to calculate the total number of results.');
    $this->assertIdentical($view->total_rows, 1, 'The pager calculated the total number of rows.');

    // Remove the last node as well and ensure that no "Page 1" is shown.
    $this->nodes[19]->delete();
    $this->drupalGet('test_mini_pager');
    $this->assertNoText('‹‹ test');
    $this->assertNoText('Page 1');
    $this->assertNoText('‹‹ test');
  }

}
