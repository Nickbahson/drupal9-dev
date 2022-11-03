<?php

namespace Drupal\Tests\og_addition\Functional;

use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests og_addition functionality.
 *
 * @group og_addition
 */
class OgAdditionSubscribeLinkTest extends ExistingSiteBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'node', 'og', 'og_ui', 'server_general', 'og_addition',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'server_theme';

  /**
   * Test normal functionality that comes with og module.
   *
   *  For admins.
   */
  public function testAdminGuestDefaults() {
    // Creates a user to test.
    $user = $this->createUser([], "test_user_og_additons", TRUE);

    // We can login and browse.
    $this->drupalLogin($user);

    // Create node to test.
    $node = $this->createNode([
      'title' => 'Football is a great sports',
      'type' => 'group',
      'uid' => $user->id(),
      'body' => 'Just texting contents',
    ]);
    $node->setPublished()->save();
    $this->assertEquals($user->id(), $node->getOwnerId());

    // We can browse pages.
    $this->drupalGet($node->toUrl());
    $this->assertSession()->statusCodeEquals(200);

    $name = $user->getDisplayName();
    $group_name = $node->label();

    $text = "Hi $name, you're already subscribed to this group called, $group_name, click here if you would like to unsubscribe.";

    $this->assertSession()->elementTextContains('css', '.unsubscribe', $text);

  }

  /**
   * Tests if content author is already subscribed.
   */
  public function testAuthorIsSubscribed() {
    $author = $this->createUser([], NULL, FALSE);
    $node = $this->createNode([
      'title' => 'Football is a great sports too',
      'type' => 'group',
      'uid' => $author->id(),
    ]);

    // We can login and browse this node page.
    $this->drupalLogin($author);
    $this->drupalGet($node->toUrl());

    $this->assertSession()->pageTextContains($author->getAccountName());
    $name = $author->getAccountName();
    $group_name = $node->label();

    $text = "Hi $name, you're already subscribed to this group called, $group_name, click here if you would like to unsubscribe.";
    $this->assertSession()->pageTextContains($text);
  }

  /**
   * Test un|subscription workflow for logged in user.
   */
  public function testLoggedInAndSubscribe() {
    $web_assert = $this->assertSession();
    $author = $this->createUser([], NULL, FALSE);
    $node = $this->createNode([
      'title' => 'Football is a great sports again',
      'type' => 'group',
      'uid' => $author->id(),
    ]);

    $another_user = $this->createUser([], NULL, FALSE);
    // Login another_user.
    $this->drupalLogin($another_user);
    $this->drupalGet($node->toUrl());
    $this->assertSession()->statusCodeEquals(200);

    $name = $another_user->getAccountName();
    $group_name = $node->label();

    $text = "Hi $name, click here if you would like to subscribe to this group called $group_name.";
    $this->assertSession()->elementTextContains('css', '.subscribe', $text);
    $this->assertSession()->pageTextContains($text);

    // Subscribe another user.
    // Go to subscription page.
    $this->getCurrentPage()->findLink('og_group')
      ->click();

    // Current page should have subscribe button text,.
    $web_assert->pageTextContains('Request membership');

    // Subscribe to this group.
    $sub_form = $this->getCurrentPage()->findById('og-subscribe-confirm-form');
    $sub_form->findButton('edit-submit')->click();

    // Already subscribed.
    $text = "Hi $name, you're already subscribed to this group called, $group_name, click here if you would like to unsubscribe.";
    $this->assertSession()->pageTextContains($text);

  }

}
