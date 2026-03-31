<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\DomCrawlerAssertionsTrait;
use Tests\Support\CodeceptTestCase;

final class DomCrawlerAssertionsTest extends CodeceptTestCase
{
    use DomCrawlerAssertionsTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->request('GET', '/test_page');
    }

    public function testAssertCheckboxChecked(): void
    {
        $this->assertCheckboxChecked('exampleCheckbox', 'The checkbox should be checked.');
    }

    public function testAssertCheckboxNotChecked(): void
    {
        $this->assertCheckboxNotChecked('nonExistentCheckbox', 'This checkbox should not be checked.');
    }

    public function testAssertInputValueNotSame(): void
    {
        $this->assertInputValueNotSame('exampleInput', 'Wrong Value', 'The input value should not be "Wrong Value".');
    }

    public function testAssertInputValueSame(): void
    {
        $this->assertInputValueSame('exampleInput', 'Expected Value', 'The input value should be "Expected Value".');
    }

    public function testAssertPageTitleContains(): void
    {
        $this->assertPageTitleContains('Test', 'The page title should contain "Test".');
    }

    public function testAssertPageTitleSame(): void
    {
        $this->assertPageTitleSame('Test Page', 'The page title should be "Test Page".');
    }

    public function testAssertSelectorExists(): void
    {
        $this->assertSelectorExists('h1', 'The <h1> element should be present.');
    }

    public function testAssertSelectorNotExists(): void
    {
        $this->assertSelectorNotExists('.non-existent-class', 'This selector should not exist.');
    }

    public function testAssertSelectorTextContains(): void
    {
        $this->assertSelectorTextContains('h1', 'Test', 'The <h1> tag should contain "Test".');
    }

    public function testAssertSelectorTextNotContains(): void
    {
        $this->assertSelectorTextNotContains('h1', 'Error', 'The <h1> tag should not contain "Error".');
    }

    public function testAssertSelectorTextSame(): void
    {
        $this->assertSelectorTextSame('h1', 'Test Page', 'The text in the <h1> tag should be exactly "Test Page".');
    }
}
