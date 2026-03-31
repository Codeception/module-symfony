<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\TranslationAssertionsTrait;
use Tests\Support\CodeceptTestCase;

final class TranslationAssertionsTest extends CodeceptTestCase
{
    use TranslationAssertionsTrait;

    public function testDontSeeFallbackTranslations(): void
    {
        $this->client->request('GET', '/register');
        $this->dontSeeFallbackTranslations();
    }

    public function testDontSeeMissingTranslations(): void
    {
        $this->client->request('GET', '/');
        $this->dontSeeMissingTranslations();
    }

    public function testGrabDefinedTranslationsCount(): void
    {
        $this->client->request('GET', '/register');
        $this->assertSame(6, $this->grabDefinedTranslationsCount());
    }

    public function testSeeAllTranslationsDefined(): void
    {
        $this->client->request('GET', '/register');
        $this->seeAllTranslationsDefined();
    }

    public function testSeeDefaultLocaleIs(): void
    {
        $this->client->request('GET', '/register');
        $this->seeDefaultLocaleIs('en');
    }

    public function testSeeFallbackLocalesAre(): void
    {
        $this->client->request('GET', '/register');
        $this->seeFallbackLocalesAre(['es']);
    }

    public function testSeeFallbackTranslationsCountLessThan(): void
    {
        $this->client->request('GET', '/register');
        $this->seeFallbackTranslationsCountLessThan(1);
    }

    public function testSeeMissingTranslationsCountLessThan(): void
    {
        $this->client->request('GET', '/');
        $this->seeMissingTranslationsCountLessThan(1);
    }
}
