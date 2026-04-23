<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\DomCrawlerAssertionsTrait;
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerAnySelectorTextContains;
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerAnySelectorTextSame;
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerSelectorCount;
use Tests\Support\CodeceptTestCase;
use function class_exists;

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

    public function testAssertSelectorCount(): void
    {
        if (!class_exists(CrawlerSelectorCount::class)) {
            $this->markTestSkipped('assertSelectorCount requires CrawlerSelectorCount support in symfony/dom-crawler.');
        }

        $this->assertSelectorCount(2, 'input', 'Expected exactly 2 inputs on the test page.');
    }

    public function testAssertSelectorTextContains(): void
    {
        $this->assertSelectorTextContains('h1', 'Test', 'The <h1> tag should contain "Test".');
    }

    public function testAssertAnySelectorTextContains(): void
    {
        if (!class_exists(CrawlerAnySelectorTextContains::class)) {
            $this->markTestSkipped('assertAnySelectorTextContains requires CrawlerAnySelectorTextContains support in symfony/dom-crawler.');
        }

        $this->client->request('GET', '/register');
        $this->assertAnySelectorTextContains('label', 'Password', 'One label should contain the password text.');
    }

    public function testAssertAnySelectorTextSame(): void
    {
        if (!class_exists(CrawlerAnySelectorTextSame::class)) {
            $this->markTestSkipped('assertAnySelectorTextSame requires CrawlerAnySelectorTextSame support in symfony/dom-crawler.');
        }

        $this->client->request('GET', '/register');
        $this->assertAnySelectorTextSame('label', 'Email Address', 'One label should match the email text.');
    }

    public function testAssertSelectorTextNotContains(): void
    {
        $this->assertSelectorTextNotContains('h1', 'Error', 'The <h1> tag should not contain "Error".');
    }

    public function testAssertAnySelectorTextNotContains(): void
    {
        if (!class_exists(CrawlerAnySelectorTextContains::class)) {
            $this->markTestSkipped('assertAnySelectorTextNotContains requires CrawlerAnySelectorTextContains support in symfony/dom-crawler.');
        }

        $this->client->request('GET', '/register');
        $this->assertAnySelectorTextNotContains('label', 'forbidden_text', 'No label should contain the forbidden text.');
    }

    public function testAssertSelectorTextSame(): void
    {
        $this->assertSelectorTextSame('h1', 'Test Page', 'The text in the <h1> tag should be exactly "Test Page".');
    }
}
