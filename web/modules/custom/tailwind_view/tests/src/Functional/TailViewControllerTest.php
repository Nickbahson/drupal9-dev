<?php

namespace Drupal\Tests\og_addition\Functional;

use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests tailwind_view controller(s) functionality.
 *
 * @group tailwind_view
 */
class TailViewControllerTest extends ExistingSiteBase {
  /**
   * {@inheritdoc}
   */
  public static $modules = ['tailwind_view'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the cards output @ /style-guide url.
   */
  public function testCardsPage(){

    $this->drupalGet('style-guide');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Person card ');
    $this->assertSession()->pageTextContains('Person Cards');

    // At least we have Email and Call text too
    $this->assertSession()->pageTextContains('Email');
    $this->assertSession()->pageTextContains('Call');
  }

}
