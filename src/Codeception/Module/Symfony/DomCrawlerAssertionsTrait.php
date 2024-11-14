<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\Constraint\LogicalNot;
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerSelectorAttributeValueSame;
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerSelectorExists;
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerSelectorTextContains;
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerSelectorTextSame;

trait DomCrawlerAssertionsTrait
{
    /**
     * Asserts that the checkbox with the given name is checked.
     */
    public function assertCheckboxChecked(string $fieldName, string $message = ''): void
    {
        $this->assertThatCrawler(new CrawlerSelectorExists("input[name=\"$fieldName\"]:checked"), $message);
    }

    /**
     * Asserts that the checkbox with the given name is not checked.
     */
    public function assertCheckboxNotChecked(string $fieldName, string $message = ''): void
    {
        $this->assertThatCrawler(new LogicalNot(
            new CrawlerSelectorExists("input[name=\"$fieldName\"]:checked")
        ), $message);
    }

    /**
     * Asserts that the value of the form input with the given name does not equal the expected value.
     */
    public function assertInputValueNotSame(string $fieldName, string $expectedValue, string $message = ''): void
    {
        $this->assertThatCrawler(new CrawlerSelectorExists("input[name=\"$fieldName\"]"), $message);
        $this->assertThatCrawler(new LogicalNot(
            new CrawlerSelectorAttributeValueSame("input[name=\"$fieldName\"]", 'value', $expectedValue)
        ), $message);
    }

    /**
     * Asserts that the value of the form input with the given name equals the expected value.
     */
    public function assertInputValueSame(string $fieldName, string $expectedValue, string $message = ''): void
    {
        $this->assertThatCrawler(new CrawlerSelectorExists("input[name=\"$fieldName\"]"), $message);
        $this->assertThatCrawler(
            new CrawlerSelectorAttributeValueSame("input[name=\"$fieldName\"]", 'value', $expectedValue),
            $message
        );
    }

    /**
     * Asserts that the `<title>` element contains the given title.
     */
    public function assertPageTitleContains(string $expectedTitle, string $message = ''): void
    {
        $this->assertSelectorTextContains('title', $expectedTitle, $message);
    }

    /**
     * Asserts that the `<title>` element equals the given title.
     */
    public function assertPageTitleSame(string $expectedTitle, string $message = ''): void
    {
        $this->assertSelectorTextSame('title', $expectedTitle, $message);
    }

    /**
     * Asserts that the given selector matches at least one element in the response.
     */
    public function assertSelectorExists(string $selector, string $message = ''): void
    {
        $this->assertThatCrawler(new CrawlerSelectorExists($selector), $message);
    }

    /**
     * Asserts that the given selector does not match at least one element in the response.
     */
    public function assertSelectorNotExists(string $selector, string $message = ''): void
    {
        $this->assertThatCrawler(new LogicalNot(new CrawlerSelectorExists($selector)), $message);
    }

    /**
     * Asserts that the first element matching the given selector contains the expected text.
     */
    public function assertSelectorTextContains(string $selector, string $text, string $message = ''): void
    {
        $this->assertThatCrawler(new CrawlerSelectorExists($selector), $message);
        $this->assertThatCrawler(new CrawlerSelectorTextContains($selector, $text), $message);
    }

    /**
     * Asserts that the first element matching the given selector does not contain the expected text.
     */
    public function assertSelectorTextNotContains(string $selector, string $text, string $message = ''): void
    {
        $this->assertThatCrawler(new CrawlerSelectorExists($selector), $message);
        $this->assertThatCrawler(new LogicalNot(new CrawlerSelectorTextContains($selector, $text)), $message);
    }

    /**
     * Asserts that the text of the first element matching the given selector equals the expected text.
     */
    public function assertSelectorTextSame(string $selector, string $text, string $message = ''): void
    {
        $this->assertThatCrawler(new CrawlerSelectorExists($selector), $message);
        $this->assertThatCrawler(new CrawlerSelectorTextSame($selector, $text), $message);
    }

    protected function assertThatCrawler(Constraint $constraint, string $message): void
    {
        $this->assertThat($this->getClient()->getCrawler(), $constraint, $message);
    }
}
