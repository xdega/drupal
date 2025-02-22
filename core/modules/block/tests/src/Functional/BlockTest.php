<?php

namespace Drupal\Tests\block\Functional;

use Drupal\Component\Utility\Html;
use Drupal\block\Entity\Block;
use Drupal\Core\Url;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Tests basic block functionality.
 *
 * @group block
 */
class BlockTest extends BlockTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * Tests block visibility.
   */
  public function testBlockVisibility() {
    $block_name = 'system_powered_by_block';
    // Create a random title for the block.
    $title = $this->randomMachineName(8);
    // Enable a standard block.
    $default_theme = $this->config('system.theme')->get('default');
    $edit = [
      'id' => strtolower($this->randomMachineName(8)),
      'region' => 'sidebar_first',
      'settings[label]' => $title,
      'settings[label_display]' => TRUE,
    ];
    // Set the block to be hidden on any user path, and to be shown only to
    // authenticated users.
    $edit['visibility[request_path][pages]'] = '/user*';
    $edit['visibility[request_path][negate]'] = TRUE;
    $edit['visibility[user_role][roles][' . RoleInterface::AUTHENTICATED_ID . ']'] = TRUE;
    $this->drupalGet('admin/structure/block/add/' . $block_name . '/' . $default_theme);
    $this->assertSession()->checkboxChecked('edit-visibility-request-path-negate-0');

    $this->submitForm($edit, 'Save block');
    $this->assertText('The block configuration has been saved.');

    $this->clickLink('Configure');
    $this->assertSession()->checkboxChecked('edit-visibility-request-path-negate-1');

    // Confirm that the block is displayed on the front page.
    $this->drupalGet('');
    $this->assertText($title);

    // Confirm that the block is not displayed according to block visibility
    // rules.
    $this->drupalGet('user');
    $this->assertNoText($title);

    // Confirm that the block is not displayed to anonymous users.
    $this->drupalLogout();
    $this->drupalGet('');
    $this->assertNoText($title);

    // Confirm that an empty block is not displayed.
    $this->assertNoText('Powered by Drupal');
    $this->assertNoRaw('sidebar-first');
  }

  /**
   * Tests that visibility can be properly toggled.
   */
  public function testBlockToggleVisibility() {
    $block_name = 'system_powered_by_block';
    // Create a random title for the block.
    $title = $this->randomMachineName(8);
    // Enable a standard block.
    $default_theme = $this->config('system.theme')->get('default');
    $edit = [
      'id' => strtolower($this->randomMachineName(8)),
      'region' => 'sidebar_first',
      'settings[label]' => $title,
    ];
    $block_id = $edit['id'];
    // Set the block to be shown only to authenticated users.
    $edit['visibility[user_role][roles][' . RoleInterface::AUTHENTICATED_ID . ']'] = TRUE;
    $this->drupalPostForm('admin/structure/block/add/' . $block_name . '/' . $default_theme, $edit, 'Save block');
    $this->clickLink('Configure');
    $this->assertSession()->checkboxChecked('edit-visibility-user-role-roles-authenticated');

    $edit = [
      'visibility[user_role][roles][' . RoleInterface::AUTHENTICATED_ID . ']' => FALSE,
    ];
    $this->submitForm($edit, 'Save block');
    $this->clickLink('Configure');
    $this->assertSession()->checkboxNotChecked('edit-visibility-user-role-roles-authenticated');

    // Ensure that no visibility is configured.
    /** @var \Drupal\block\BlockInterface $block */
    $block = Block::load($block_id);
    $visibility_config = $block->getVisibilityConditions()->getConfiguration();
    $this->assertIdentical([], $visibility_config);
    $this->assertIdentical([], $block->get('visibility'));
  }

  /**
   * Test block visibility when leaving "pages" textarea empty.
   */
  public function testBlockVisibilityListedEmpty() {
    $block_name = 'system_powered_by_block';
    // Create a random title for the block.
    $title = $this->randomMachineName(8);
    // Enable a standard block.
    $default_theme = $this->config('system.theme')->get('default');
    $edit = [
      'id' => strtolower($this->randomMachineName(8)),
      'region' => 'sidebar_first',
      'settings[label]' => $title,
      'visibility[request_path][negate]' => TRUE,
    ];
    // Set the block to be hidden on any user path, and to be shown only to
    // authenticated users.
    $this->drupalPostForm('admin/structure/block/add/' . $block_name . '/' . $default_theme, $edit, 'Save block');
    $this->assertText('The block configuration has been saved.');

    // Confirm that block was not displayed according to block visibility
    // rules.
    $this->drupalGet('user');
    $this->assertNoText($title);

    // Confirm that block was not displayed according to block visibility
    // rules regardless of path case.
    $this->drupalGet('USER');
    $this->assertNoText($title);

    // Confirm that the block is not displayed to anonymous users.
    $this->drupalLogout();
    $this->drupalGet('');
    $this->assertNoText($title);
  }

  /**
   * Tests adding a block from the library page with a weight query string.
   */
  public function testAddBlockFromLibraryWithWeight() {
    $default_theme = $this->config('system.theme')->get('default');
    // Test one positive, zero, and one negative weight.
    foreach (['7', '0', '-9'] as $weight) {
      $options = [
        'query' => [
          'region' => 'sidebar_first',
          'weight' => $weight,
        ],
      ];
      $this->drupalGet(Url::fromRoute('block.admin_library', ['theme' => $default_theme], $options));

      $block_name = 'system_powered_by_block';
      $add_url = Url::fromRoute('block.admin_add', [
        'plugin_id' => $block_name,
        'theme' => $default_theme,
      ]);
      $links = $this->xpath('//a[contains(@href, :href)]', [':href' => $add_url->toString()]);
      $this->assertCount(1, $links, 'Found one matching link.');
      $this->assertEqual(t('Place block'), $links[0]->getText(), 'Found the expected link text.');

      list($path, $query_string) = explode('?', $links[0]->getAttribute('href'), 2);
      parse_str($query_string, $query_parts);
      $this->assertEqual($weight, $query_parts['weight'], 'Found the expected weight query string.');

      // Create a random title for the block.
      $title = $this->randomMachineName(8);
      $block_id = strtolower($this->randomMachineName(8));
      $edit = [
        'id' => $block_id,
        'settings[label]' => $title,
      ];
      // Create the block using the link parsed from the library page.
      $this->drupalPostForm($this->getAbsoluteUrl($links[0]->getAttribute('href')), $edit, 'Save block');

      // Ensure that the block was created with the expected weight.
      /** @var \Drupal\block\BlockInterface $block */
      $block = Block::load($block_id);
      $this->assertEqual($weight, $block->getWeight(), 'Found the block with expected weight.');
    }
  }

  /**
   * Test configuring and moving a module-define block to specific regions.
   */
  public function testBlock() {
    // Place page title block to test error messages.
    $this->drupalPlaceBlock('page_title_block');

    // Disable the block.
    $this->drupalGet('admin/structure/block');
    $this->clickLink('Disable');

    // Select the 'Powered by Drupal' block to be configured and moved.
    $block = [];
    $block['id'] = 'system_powered_by_block';
    $block['settings[label]'] = $this->randomMachineName(8);
    $block['settings[label_display]'] = TRUE;
    $block['theme'] = $this->config('system.theme')->get('default');
    $block['region'] = 'header';

    // Set block title to confirm that interface works and override any custom titles.
    $this->drupalPostForm('admin/structure/block/add/' . $block['id'] . '/' . $block['theme'], ['settings[label]' => $block['settings[label]'], 'settings[label_display]' => $block['settings[label_display]'], 'id' => $block['id'], 'region' => $block['region']], 'Save block');
    $this->assertText('The block configuration has been saved.');
    // Check to see if the block was created by checking its configuration.
    $instance = Block::load($block['id']);

    $this->assertEqual($instance->label(), $block['settings[label]'], 'Stored block title found.');

    // Check whether the block can be moved to all available regions.
    foreach ($this->regions as $region) {
      $this->moveBlockToRegion($block, $region);
    }

    // Disable the block.
    $this->drupalGet('admin/structure/block');
    $this->clickLink('Disable');

    // Confirm that the block is now listed as disabled.
    $this->assertText('The block settings have been updated.');

    // Confirm that the block instance title and markup are not displayed.
    $this->drupalGet('node');
    $this->assertNoText($block['settings[label]']);
    // Check for <div id="block-my-block-instance-name"> if the machine name
    // is my_block_instance_name.
    $xpath = $this->assertSession()->buildXPathQuery('//div[@id=:id]/*', [':id' => 'block-' . str_replace('_', '-', strtolower($block['id']))]);
    $this->assertSession()->elementNotExists('xpath', $xpath);

    // Test deleting the block from the edit form.
    $this->drupalGet('admin/structure/block/manage/' . $block['id']);
    $this->clickLink(t('Remove block'));
    $this->assertRaw(t('Are you sure you want to remove the block @name?', ['@name' => $block['settings[label]']]));
    $this->submitForm([], 'Remove');
    $this->assertRaw(t('The block %name has been removed.', ['%name' => $block['settings[label]']]));

    // Test deleting a block via "Configure block" link.
    $block = $this->drupalPlaceBlock('system_powered_by_block');
    $this->drupalGet('admin/structure/block/manage/' . $block->id(), ['query' => ['destination' => 'admin']]);
    $this->clickLink(t('Remove block'));
    $this->assertRaw(t('Are you sure you want to remove the block @name?', ['@name' => $block->label()]));
    $this->submitForm([], 'Remove');
    $this->assertRaw(t('The block %name has been removed.', ['%name' => $block->label()]));
    $this->assertSession()->addressEquals('admin');
    $this->assertNoRaw($block->id());
  }

  /**
   * Tests that the block form has a theme selector when not passed via the URL.
   */
  public function testBlockThemeSelector() {
    // Install all themes.
    \Drupal::service('theme_installer')->install(['bartik', 'seven', 'stark']);
    $theme_settings = $this->config('system.theme');
    foreach (['bartik', 'seven', 'stark'] as $theme) {
      $this->drupalGet('admin/structure/block/list/' . $theme);
      $this->assertSession()->titleEquals('Block layout | Drupal');
      // Select the 'Powered by Drupal' block to be placed.
      $block = [];
      $block['id'] = strtolower($this->randomMachineName());
      $block['theme'] = $theme;
      $block['region'] = 'content';
      $this->drupalPostForm('admin/structure/block/add/system_powered_by_block', $block, 'Save block');
      $this->assertText('The block configuration has been saved.');
      $this->assertSession()->addressEquals('admin/structure/block/list/' . $theme . '?block-placement=' . Html::getClass($block['id']));

      // Set the default theme and ensure the block is placed.
      $theme_settings->set('default', $theme)->save();
      $this->drupalGet('');
      $elements = $this->xpath('//div[@id = :id]', [':id' => Html::getUniqueId('block-' . $block['id'])]);
      $this->assertTrue(!empty($elements), 'The block was found.');
    }
  }

  /**
   * Test block display of theme titles.
   */
  public function testThemeName() {
    // Enable the help block.
    $this->drupalPlaceBlock('help_block', ['region' => 'help']);
    $this->drupalPlaceBlock('local_tasks_block');
    // Explicitly set the default and admin themes.
    $theme = 'block_test_specialchars_theme';
    \Drupal::service('theme_installer')->install([$theme]);
    $this->drupalGet('admin/structure/block');
    $this->assertSession()->assertEscaped('<"Cat" & \'Mouse\'>');
    $this->drupalGet('admin/structure/block/list/block_test_specialchars_theme');
    $this->assertSession()->assertEscaped('Demonstrate block regions (<"Cat" & \'Mouse\'>)');
  }

  /**
   * Test block title display settings.
   */
  public function testHideBlockTitle() {
    $block_name = 'system_powered_by_block';
    // Create a random title for the block.
    $title = $this->randomMachineName(8);
    $id = strtolower($this->randomMachineName(8));
    // Enable a standard block.
    $default_theme = $this->config('system.theme')->get('default');
    $edit = [
      'id' => $id,
      'region' => 'sidebar_first',
      'settings[label]' => $title,
    ];
    $this->drupalPostForm('admin/structure/block/add/' . $block_name . '/' . $default_theme, $edit, 'Save block');
    $this->assertText('The block configuration has been saved.');

    // Confirm that the block is not displayed by default.
    $this->drupalGet('user');
    $this->assertNoText($title);

    $edit = [
      'settings[label_display]' => TRUE,
    ];
    $this->drupalPostForm('admin/structure/block/manage/' . $id, $edit, 'Save block');
    $this->assertText('The block configuration has been saved.');

    $this->drupalGet('admin/structure/block/manage/' . $id);
    $this->assertSession()->checkboxChecked('edit-settings-label-display');

    // Confirm that the block is displayed when enabled.
    $this->drupalGet('user');
    $this->assertText($title);
  }

  /**
   * Moves a block to a given region via the UI and confirms the result.
   *
   * @param array $block
   *   An array of information about the block, including the following keys:
   *   - module: The module providing the block.
   *   - title: The title of the block.
   *   - delta: The block's delta key.
   * @param string $region
   *   The machine name of the theme region to move the block to, for example
   *   'header' or 'sidebar_first'.
   */
  public function moveBlockToRegion(array $block, $region) {
    // Set the created block to a specific region.
    $block += ['theme' => $this->config('system.theme')->get('default')];
    $edit = [];
    $edit['blocks[' . $block['id'] . '][region]'] = $region;
    $this->drupalPostForm('admin/structure/block', $edit, 'Save blocks');

    // Confirm that the block was moved to the proper region.
    $this->assertText('The block settings have been updated.');

    // Confirm that the block is being displayed.
    $this->drupalGet('');
    $this->assertText($block['settings[label]']);

    // Confirm that the custom block was found at the proper region.
    $xpath = $this->assertSession()->buildXPathQuery('//div[@class=:region-class]//div[@id=:block-id]/*', [
      ':region-class' => 'region region-' . Html::getClass($region),
      ':block-id' => 'block-' . str_replace('_', '-', strtolower($block['id'])),
    ]);
    $this->assertSession()->elementExists('xpath', $xpath);
  }

  /**
   * Test that cache tags are properly set and bubbled up to the page cache.
   *
   * Verify that invalidation of these cache tags works:
   * - "block:<block ID>"
   * - "block_plugin:<block plugin ID>"
   */
  public function testBlockCacheTags() {
    // The page cache only works for anonymous users.
    $this->drupalLogout();

    // Enable page caching.
    $config = $this->config('system.performance');
    $config->set('cache.page.max_age', 300);
    $config->save();

    // Place the "Powered by Drupal" block.
    $block = $this->drupalPlaceBlock('system_powered_by_block', ['id' => 'powered']);

    // Prime the page cache.
    $this->drupalGet('<front>');
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'MISS');

    // Verify a cache hit, but also the presence of the correct cache tags in
    // both the page and block caches.
    $this->drupalGet('<front>');
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'HIT');
    $cid_parts = [Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString(), ''];
    $cid = implode(':', $cid_parts);
    $cache_entry = \Drupal::cache('page')->get($cid);
    $expected_cache_tags = [
      'config:block_list',
      'block_view',
      'config:block.block.powered',
      'config:user.role.anonymous',
      'http_response',
      'rendered',
    ];
    sort($expected_cache_tags);
    $keys = \Drupal::service('cache_contexts_manager')->convertTokensToKeys(['languages:language_interface', 'theme', 'user.permissions'])->getKeys();
    $this->assertIdentical($cache_entry->tags, $expected_cache_tags);
    $cache_entry = \Drupal::cache('render')->get('entity_view:block:powered:' . implode(':', $keys));
    $expected_cache_tags = [
      'block_view',
      'config:block.block.powered',
      'rendered',
    ];
    sort($expected_cache_tags);
    $this->assertIdentical($cache_entry->tags, $expected_cache_tags);

    // The "Powered by Drupal" block is modified; verify a cache miss.
    $block->setRegion('content');
    $block->save();
    $this->drupalGet('<front>');
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'MISS');

    // Now we should have a cache hit again.
    $this->drupalGet('<front>');
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'HIT');

    // Place the "Powered by Drupal" block another time; verify a cache miss.
    $this->drupalPlaceBlock('system_powered_by_block', ['id' => 'powered-2']);
    $this->drupalGet('<front>');
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'MISS');

    // Verify a cache hit, but also the presence of the correct cache tags.
    $this->drupalGet('<front>');
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'HIT');
    $cid_parts = [Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString(), ''];
    $cid = implode(':', $cid_parts);
    $cache_entry = \Drupal::cache('page')->get($cid);
    $expected_cache_tags = [
      'config:block_list',
      'block_view',
      'config:block.block.powered',
      'config:block.block.powered-2',
      'config:user.role.anonymous',
      'http_response',
      'rendered',
    ];
    sort($expected_cache_tags);
    $this->assertEqual($cache_entry->tags, $expected_cache_tags);
    $expected_cache_tags = [
      'block_view',
      'config:block.block.powered',
      'rendered',
    ];
    sort($expected_cache_tags);
    $keys = \Drupal::service('cache_contexts_manager')->convertTokensToKeys(['languages:language_interface', 'theme', 'user.permissions'])->getKeys();
    $cache_entry = \Drupal::cache('render')->get('entity_view:block:powered:' . implode(':', $keys));
    $this->assertIdentical($cache_entry->tags, $expected_cache_tags);
    $expected_cache_tags = [
      'block_view',
      'config:block.block.powered-2',
      'rendered',
    ];
    sort($expected_cache_tags);
    $keys = \Drupal::service('cache_contexts_manager')->convertTokensToKeys(['languages:language_interface', 'theme', 'user.permissions'])->getKeys();
    $cache_entry = \Drupal::cache('render')->get('entity_view:block:powered-2:' . implode(':', $keys));
    $this->assertIdentical($cache_entry->tags, $expected_cache_tags);

    // Now we should have a cache hit again.
    $this->drupalGet('<front>');
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'HIT');

    // Delete the "Powered by Drupal" blocks; verify a cache miss.
    $block_storage = \Drupal::entityTypeManager()->getStorage('block');
    $block_storage->load('powered')->delete();
    $block_storage->load('powered-2')->delete();
    $this->drupalGet('<front>');
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'MISS');
  }

  /**
   * Tests that a link exists to block layout from the appearance form.
   */
  public function testThemeAdminLink() {
    $this->drupalPlaceBlock('help_block', ['region' => 'help']);
    $theme_admin = $this->drupalCreateUser([
      'administer blocks',
      'administer themes',
      'access administration pages',
    ]);
    $this->drupalLogin($theme_admin);
    $this->drupalGet('admin/appearance');
    $this->assertText('You can place blocks for each theme on the block layout page');
    $this->assertSession()->linkByHrefExists('admin/structure/block');
  }

  /**
   * Tests that uninstalling a theme removes its block configuration.
   */
  public function testUninstallTheme() {
    /** @var \Drupal\Core\Extension\ThemeInstallerInterface $theme_installer */
    $theme_installer = \Drupal::service('theme_installer');

    $theme_installer->install(['seven']);
    $this->config('system.theme')->set('default', 'seven')->save();
    $block = $this->drupalPlaceBlock('system_powered_by_block', ['theme' => 'seven', 'region' => 'help']);
    $this->drupalGet('<front>');
    $this->assertText('Powered by Drupal');

    $this->config('system.theme')->set('default', 'classy')->save();
    $theme_installer->uninstall(['seven']);

    // Ensure that the block configuration does not exist anymore.
    $this->assertIdentical(NULL, Block::load($block->id()));
  }

  /**
   * Tests the block access.
   */
  public function testBlockAccess() {
    $this->drupalPlaceBlock('test_access', ['region' => 'help']);

    $this->drupalGet('<front>');
    $this->assertNoText('Hello test world');

    \Drupal::state()->set('test_block_access', TRUE);
    $this->drupalGet('<front>');
    $this->assertText('Hello test world');
  }

  /**
   * Tests block_user_role_delete.
   */
  public function testBlockUserRoleDelete() {
    $role1 = Role::create(['id' => 'test_role1', 'name' => $this->randomString()]);
    $role1->save();

    $role2 = Role::create(['id' => 'test_role2', 'name' => $this->randomString()]);
    $role2->save();

    $block = Block::create([
      'id' => $this->randomMachineName(),
      'plugin' => 'system_powered_by_block',
    ]);

    $block->setVisibilityConfig('user_role', [
      'roles' => [
        $role1->id() => $role1->id(),
        $role2->id() => $role2->id(),
      ],
    ]);

    $block->save();

    $this->assertEqual($block->getVisibility()['user_role']['roles'], [
      $role1->id() => $role1->id(),
      $role2->id() => $role2->id(),
    ]);

    $role1->delete();

    $block = Block::load($block->id());
    $this->assertEqual($block->getVisibility()['user_role']['roles'], [
      $role2->id() => $role2->id(),
    ]);
  }

}
