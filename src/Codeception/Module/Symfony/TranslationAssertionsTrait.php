<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\Translation\DataCollector\TranslationDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

trait TranslationAssertionsTrait
{
    /**
     * Asserts that no fallback translations were found.
     *
     * ```php
     * <?php
     * $I->dontSeeFallbackTranslations();
     * ```
     */
    public function dontSeeFallbackTranslations(): void
    {
        $translationCollector = $this->grabTranslationCollector(__FUNCTION__);
        $fallbacks = $translationCollector->getCountFallbacks();

        $this->assertSame(
            $fallbacks,
            0,
            "Expected no fallback translations, but found {$fallbacks}."
        );
    }

    /**
     * Asserts that no missing translations were found.
     *
     * ```php
     * <?php
     * $I->dontSeeMissingTranslations();
     * ```
     */
    public function dontSeeMissingTranslations(): void
    {
        $translationCollector = $this->grabTranslationCollector(__FUNCTION__);
        $missings = $translationCollector->getCountMissings();

        $this->assertSame(
            $missings,
            0,
            "Expected no missing translations, but found {$missings}."
        );
    }

    /**
     * Grabs the count of defined translations.
     *
     * ```php
     * <?php
     * $count = $I->grabDefinedTranslations();
     * ```
     *
     * @return int The count of defined translations.
     */
    public function grabDefinedTranslationsCount(): int
    {
        $translationCollector = $this->grabTranslationCollector(__FUNCTION__);
        return $translationCollector->getCountDefines();
    }

    /**
     * Asserts that there are no missing translations and no fallback translations.
     *
     * ```php
     * <?php
     * $I->seeAllTranslationsDefined();
     * ```
     */
    public function seeAllTranslationsDefined(): void
    {
        $this->dontSeeMissingTranslations();
        $this->dontSeeFallbackTranslations();
    }

    /**
     * Asserts that the default locale is the expected one.
     *
     * ```php
     * <?php
     * $I->seeDefaultLocaleIs('en');
     * ```
     *
     * @param string $expectedLocale The expected default locale
     */
    public function seeDefaultLocaleIs(string $expectedLocale): void
    {
        $translationCollector = $this->grabTranslationCollector(__FUNCTION__);
        $locale = $translationCollector->getLocale();

        $this->assertSame(
            $expectedLocale,
            $locale,
            "Expected default locale '{$expectedLocale}', but found '{$locale}'."
        );
    }

    /**
     * Asserts that the fallback locales match the expected ones.
     *
     * ```php
     * <?php
     * $I->seeFallbackLocalesAre(['es', 'fr']);
     * ```
     *
     * @param string[] $expectedLocales The expected fallback locales
     */
    public function seeFallbackLocalesAre(array $expectedLocales): void
    {
        $translationCollector = $this->grabTranslationCollector(__FUNCTION__);
        $fallbackLocales = $translationCollector->getFallbackLocales();

        if ($fallbackLocales instanceof Data) {
            $fallbackLocales = $fallbackLocales->getValue(true);
        }

        $this->assertSame(
            $expectedLocales,
            $fallbackLocales,
            "Fallback locales do not match expected."
        );
    }

    /**
     * Asserts that the count of fallback translations is less than the given limit.
     *
     * ```php
     * <?php
     * $I->seeFallbackTranslationsCountLessThan(10);
     * ```
     *
     * @param int $limit Maximum count of fallback translations
     */
    public function seeFallbackTranslationsCountLessThan(int $limit): void
    {
        $translationCollector = $this->grabTranslationCollector(__FUNCTION__);
        $fallbacks = $translationCollector->getCountFallbacks();

        $this->assertLessThan(
            $limit,
            $fallbacks,
            "Expected fewer than {$limit} fallback translations, but found {$fallbacks}."
        );
    }

    /**
     * Asserts that the count of missing translations is less than the given limit.
     *
     * ```php
     * <?php
     * $I->seeMissingTranslationsCountLessThan(5);
     * ```
     *
     * @param int $limit Maximum count of missing translations
     */
    public function seeMissingTranslationsCountLessThan(int $limit): void
    {
        $translationCollector = $this->grabTranslationCollector(__FUNCTION__);
        $missings = $translationCollector->getCountMissings();

        $this->assertLessThan(
            $limit,
            $missings,
            "Expected fewer than {$limit} missing translations, but found {$missings}."
        );
    }

    protected function grabTranslationCollector(string $function): TranslationDataCollector
    {
        return $this->grabCollector('translation', $function);
    }
}
