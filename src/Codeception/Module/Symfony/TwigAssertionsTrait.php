<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Bridge\Twig\DataCollector\TwigDataCollector;
use function array_key_first;

trait TwigAssertionsTrait
{
    /**
     * Asserts that a template was not rendered in the response.
     *
     * ```php
     * <?php
     * $I->dontSeeRenderedTemplate('home.html.twig');
     * ```
     *
     * @param string $template
     */
    public function dontSeeRenderedTemplate(string $template): void
    {
        $twigCollector = $this->grabTwigCollector(__FUNCTION__);

        $templates = (array)$twigCollector->getTemplates();

        $this->assertArrayNotHasKey(
            $template,
            $templates,
            "Template {$template} was rendered."
        );
    }

    /**
     * Asserts that the current template matches the expected template.
     *
     * ```php
     * <?php
     * $I->seeCurrentTemplateIs('home.html.twig');
     * ```
     *
     * @param string $expectedTemplate
     */
    public function seeCurrentTemplateIs(string $expectedTemplate): void
    {
        $twigCollector = $this->grabTwigCollector(__FUNCTION__);

        $templates = (array)$twigCollector->getTemplates();
        $actualTemplate = (string)array_key_first($templates);

        $this->assertSame(
            $expectedTemplate,
            $actualTemplate,
            "Actual template {$actualTemplate} does not match expected template {$expectedTemplate}."
        );
    }

    /**
     * Asserts that a template was rendered in the response.
     * That includes templates built with [inheritance](https://twig.symfony.com/doc/3.x/templates.html#template-inheritance).
     *
     * ```php
     * <?php
     * $I->seeRenderedTemplate('home.html.twig');
     * $I->seeRenderedTemplate('layout.html.twig');
     * ```
     *
     * @param string $template
     */
    public function seeRenderedTemplate(string $template): void
    {
        $twigCollector = $this->grabTwigCollector(__FUNCTION__);

        $templates = (array)$twigCollector->getTemplates();

        $this->assertArrayHasKey(
            $template,
            $templates,
            "Template {$template} was not rendered."
        );
    }

    protected function grabTwigCollector(string $function): TwigDataCollector
    {
        return $this->grabCollector('twig', $function);
    }
}