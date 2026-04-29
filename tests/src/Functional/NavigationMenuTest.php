<?php

namespace Drupal\Tests\forseti_content\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Validates the main navigation menu has exactly the expected items.
 *
 * Regression test for double-menu-item bug caused by forseti_safety_content
 * and forseti_content both defining the same top-level nav links.
 *
 * Expected top-level items (in weight order):
 *   About, How It Works, Talk with Forseti, Family & Institutions, Job Hunter
 *
 * @group forseti_content
 * @group navigation
 */
class NavigationMenuTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'image',
    'link',
    'options',
    'menu_ui',
    'block',
    'ai_conversation',
    'forseti_content',
  ];

  /**
   * Expected top-level menu item titles, in order.
   */
  const EXPECTED_TOP_LEVEL_ITEMS = [
    'About',
    'How It Works',
    'Talk with Forseti',
    'Family & Institutions',
    'Job Hunter',
  ];

  /**
   * Expected items that must NOT appear in the main nav.
   */
  const FORBIDDEN_ITEMS = [
    'Home',
    'Privacy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the main navigation block in the content region so stark renders it.
    $this->drupalPlaceBlock('system_menu_block:main', [
      'region' => 'content',
      'id' => 'main-navigation-test',
    ]);
  }

  /**
   * Assert top-level nav items appear exactly once each.
   */
  public function testMainMenuItemsAreNotDuplicated(): void {
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);

    foreach (self::EXPECTED_TOP_LEVEL_ITEMS as $title) {
      $links = $this->getSession()->getPage()->findAll(
        'xpath',
        '//nav//a[normalize-space(text())="' . $title . '"]'
      );
      $count = count($links);
      $this->assertEquals(
        1,
        $count,
        "Expected exactly 1 nav link for '$title', found $count. Possible duplicate menu link definitions."
      );
    }
  }

  /**
   * Assert forbidden items are absent from the main nav.
   */
  public function testForbiddenItemsAbsentFromMainNav(): void {
    $this->drupalGet('<front>');

    foreach (self::FORBIDDEN_ITEMS as $title) {
      $this->assertSession()->elementNotExists(
        'xpath',
        '//nav//a[normalize-space(text())="' . $title . '"]'
      );
    }
  }

  /**
   * Assert the expected set of top-level items is complete — no extras.
   */
  public function testMainMenuHasNoUnexpectedTopLevelItems(): void {
    $this->drupalGet('<front>');

    $navLinks = $this->getSession()->getPage()->findAll(
      'xpath',
      '//nav//ul[contains(@class,"navbar-nav") or @id="main-navigation-test"]/li/a'
    );

    $renderedTitles = array_map(
      fn($link) => trim($link->getText()),
      $navLinks
    );

    // Filter out empty strings and forbidden items.
    $renderedTitles = array_values(array_filter(
      $renderedTitles,
      fn($t) => $t !== '' && !in_array($t, self::FORBIDDEN_ITEMS, TRUE)
    ));

    $unexpected = array_diff($renderedTitles, self::EXPECTED_TOP_LEVEL_ITEMS);
    $this->assertEmpty(
      $unexpected,
      'Unexpected top-level nav items found: ' . implode(', ', $unexpected)
    );
  }

  /**
   * Assert the Talk with Forseti menu link uses the chat launcher route.
   */
  public function testTalkWithForsetiMenuLinkTarget(): void {
    $this->drupalGet('<front>');

    $link = $this->assertSession()->linkByHrefExists('/talk-with-forseti');
    $this->assertSame('Talk with Forseti', trim($link->getText()));
    $this->assertSession()->elementNotExists(
      'xpath',
      '//nav//a[normalize-space(text())="Talk with Forseti" and @href="/contact"]'
    );
  }

  /**
   * Assert anonymous users are redirected into login when self-signup is closed.
   */
  public function testAnonymousTalkWithForsetiRedirectsToRegistration(): void {
    $this->config('user.settings')
      ->set('register', 'admin_only')
      ->save();

    $this->drupalGet('/talk-with-forseti');
    $this->assertSession()->addressEquals('/user/login');
    $this->assertSession()->pageTextContains('Please log in to start a conversation with Forseti.');
  }

  /**
   * Assert authenticated users get a fresh conversation and land in chat.
   */
  public function testAuthenticatedTalkWithForsetiCreatesConversation(): void {
    $account = $this->drupalCreateUser([
      'access content',
      'use ai conversation',
      'create ai_conversation content',
    ]);
    $this->drupalLogin($account);

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $before_ids = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'ai_conversation')
      ->condition('uid', $account->id())
      ->execute();

    $this->drupalGet('/talk-with-forseti');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressMatches('#^/node/\\d+/chat$#');

    $after_ids = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'ai_conversation')
      ->condition('uid', $account->id())
      ->execute();

    $this->assertCount(count($before_ids) + 1, $after_ids);

    $new_ids = array_values(array_diff($after_ids, $before_ids));
    $this->assertCount(1, $new_ids, 'Exactly one new AI conversation node was created.');

    $conversation = $storage->load(reset($new_ids));
    $this->assertNotNull($conversation);
    $this->assertSame('ai_conversation', $conversation->bundle());
    $this->assertStringContainsString('Conversation with Forseti - ', $conversation->label());
  }

}
