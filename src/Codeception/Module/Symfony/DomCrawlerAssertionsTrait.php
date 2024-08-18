<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Constraint\LogicalAnd;
use PHPUnit\Framework\Constraint\LogicalNot;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerAnySelectorTextContains;
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerAnySelectorTextSame;
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerSelectorAttributeValueSame;
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerSelectorCount;
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerSelectorExists;
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerSelectorTextContains;
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerSelectorTextSame;

trait DomCrawlerAssertionsTrait
{
    /**
     * Asserts that any element matching the given selector does contain the expected text.
     */
    public function assertAnySelectorTextContains(string $selector, string $text, string $message = ''): void
    {
        $this->assertThat($this->getCrawler(), LogicalAnd::fromConstraints(
            new CrawlerSelectorExists($selector),
            new CrawlerAnySelectorTextContains($selector, $text)
        ), $message);
    }

    /**
     * Asserts that any element matching the given selector does not contain the expected text.
     */
    public function assertAnySelectorTextNotContains(string $selector, string $text, string $message = ''): void
    {
        $this->assertThat($this->getCrawler(), LogicalAnd::fromConstraints(
            new CrawlerSelectorExists($selector),
            new LogicalNot(new CrawlerAnySelectorTextContains($selector, $text))
        ), $message);
    }

    /**
     * Asserts that any element matching the given selector does equal the expected text.
     */
    public function assertAnySelectorTextSame(string $selector, string $text, string $message = ''): void
    {
        $this->assertThat($this->getCrawler(), LogicalAnd::fromConstraints(
            new CrawlerSelectorExists($selector),
            new CrawlerAnySelectorTextSame($selector, $text)
        ), $message);
    }

    /**
     * Asserts that the checkbox with the given name is checked.
     */
    public function assertCheckboxChecked(string $fieldName, string $message = ''): void
    {
        $this->assertThat(
            $this->getCrawler(),
            new CrawlerSelectorExists("input[name=\"$fieldName\"]:checked"),
            $message
        );
    }

    /**
     * Asserts that the checkbox with the given name is not checked.
     */
    public function assertCheckboxNotChecked(string $fieldName, string $message = ''): void
    {
        $this->assertThat(
            $this->getCrawler(),
            new LogicalNot(new CrawlerSelectorExists("input[name=\"$fieldName\"]:checked")),
            $message
        );
    }

    /**
     * Asserts that value of the form input with the given name does not equal the expected value.
     */
    public function assertInputValueNotSame(string $fieldName, string $expectedValue, string $message = ''): void
    {
        $this->assertThat($this->getCrawler(), LogicalAnd::fromConstraints(
            new CrawlerSelectorExists("input[name=\"$fieldName\"]"),
            new LogicalNot(new CrawlerSelectorAttributeValueSame("input[name=\"$fieldName\"]", 'value', $expectedValue))
        ), $message);
    }

    /**
     * Asserts that value of the form input with the given name does equal the expected value.
     */
    public function assertInputValueSame(string $fieldName, string $expectedValue, string $message = ''): void
    {
        $this->assertThat($this->getCrawler(), LogicalAnd::fromConstraints(
            new CrawlerSelectorExists("input[name=\"$fieldName\"]"),
            new CrawlerSelectorAttributeValueSame("input[name=\"$fieldName\"]", 'value', $expectedValue)
        ), $message);
    }

    /**
     * Asserts that the `<title>` element contains the given title.
     */
    public function assertPageTitleContains(string $expectedTitle, string $message = ''): void
    {
        $this->assertSelectorTextContains('title', $expectedTitle, $message);
    }

    /**
     * Asserts that the `<title>` element is equal to the given title.
     */
    public function assertPageTitleSame(string $expectedTitle, string $message = ''): void
    {
        $this->assertSelectorTextSame('title', $expectedTitle, $message);
    }

    /**
     * Asserts that the expected number of selector elements are in the response.
     */
    public function assertSelectorCount(int $expectedCount, string $selector, string $message = ''): void
    {
        $this->assertThat($this->getCrawler(), new CrawlerSelectorCount($expectedCount, $selector), $message);
    }

    /**
     * Asserts that the given selector does match at least one element in the response.
     */
    public function assertSelectorExists(string $selector, string $message = ''): void
    {
        $this->assertThat($this->getCrawler(), new CrawlerSelectorExists($selector), $message);
    }

    /**
     * Asserts that the given selector does not match at least one element in the response.
     */
    public function assertSelectorNotExists(string $selector, string $message = ''): void
    {
        $this->assertThat($this->getCrawler(), new LogicalNot(new CrawlerSelectorExists($selector)), $message);
    }

    /**
     * Asserts that the first element matching the given selector does contain the expected text.
     */
    public function assertSelectorTextContains(string $selector, string $text, string $message = ''): void
    {
        $this->assertThat($this->getCrawler(), LogicalAnd::fromConstraints(
            new CrawlerSelectorExists($selector),
            new CrawlerSelectorTextContains($selector, $text)
        ), $message);
    }

    /**
     * Asserts that the first element matching the given selector does not contain the expected text.
     */
    public function assertSelectorTextNotContains(string $selector, string $text, string $message = ''): void
    {
        $this->assertThat($this->getCrawler(), LogicalAnd::fromConstraints(
            new CrawlerSelectorExists($selector),
            new LogicalNot(new CrawlerSelectorTextContains($selector, $text))
        ), $message);
    }

    /**
     * Asserts that the contents of the first element matching the given selector does equal the expected text.
     */
    public function assertSelectorTextSame(string $selector, string $text, string $message = ''): void
    {
        $this->assertThat($this->getCrawler(), LogicalAnd::fromConstraints(
            new CrawlerSelectorExists($selector),
            new CrawlerSelectorTextSame($selector, $text)
        ), $message);
    }

    protected function getCrawler(): Crawler
    {
        return $this->client->getCrawler();
    }
}
