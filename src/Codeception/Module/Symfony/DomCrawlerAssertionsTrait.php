<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Constraint\LogicalNot;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerSelectorExists;

trait DomCrawlerAssertionsTrait
{
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

    protected function getCrawler(): Crawler
    {
        return $this->client->getCrawler();
    }
}
