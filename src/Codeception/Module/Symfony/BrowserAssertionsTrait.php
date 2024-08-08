<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseFormatSame;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseIsSuccessful;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseStatusCodeSame;
use function sprintf;

trait BrowserAssertionsTrait
{
    /**
     * Asserts the response format returned by the `Response::getFormat()` method is the same as the expected value.
     */
    public function assertResponseFormatSame(?string $expectedFormat, string $message = ''): void
    {
        $this->assertThatForResponse(new ResponseFormatSame($this->getClient()->getRequest(), $expectedFormat), $message);
    }

    /**
     * Asserts that the response was successful (HTTP status is 2xx).
     */
    public function assertResponseIsSuccessful(string $message = '', bool $verbose = true): void
    {
        $this->assertThatForResponse(new ResponseIsSuccessful($verbose), $message);
    }

    /**
     * Asserts a specific HTTP status code.
     */
    public function assertResponseStatusCodeSame(int $expectedCode, string $message = '', bool $verbose = true): void
    {
        $this->assertThatForResponse(new ResponseStatusCodeSame($expectedCode, $verbose), $message);
    }

    /**
     * Reboot client's kernel.
     * Can be used to manually reboot kernel when 'rebootable_client' => false
     *
     * ```php
     * <?php
     *
     * // Perform some requests
     *
     * $I->rebootClientKernel();
     *
     * // Perform other requests
     *
     * ```
     */
    public function rebootClientKernel(): void
    {
        $this->getClient()->rebootKernel();
    }

    /**
     * Verifies that a page is available.
     * By default, it checks the current page, specify the `$url` parameter to change it.
     *
     * ```php
     * <?php
     * $I->amOnPage('/dashboard');
     * $I->seePageIsAvailable();
     *
     * $I->seePageIsAvailable('/dashboard'); // Same as above
     * ```
     *
     * @param string|null $url
     */
    public function seePageIsAvailable(?string $url = null): void
    {
        if ($url !== null) {
            $this->amOnPage($url);
            $this->seeInCurrentUrl($url);
        }

        $this->assertResponseIsSuccessful();
    }

    /**
     * Goes to a page and check that it redirects to another.
     *
     * ```php
     * <?php
     * $I->seePageRedirectsTo('/admin', '/login');
     * ```
     */
    public function seePageRedirectsTo(string $page, string $redirectsTo): void
    {
        $this->getClient()->followRedirects(false);
        $this->amOnPage($page);
        $response = $this->getClient()->getResponse();
        $this->assertTrue(
            $response->isRedirection()
        );
        $this->getClient()->followRedirect();
        $this->seeInCurrentUrl($redirectsTo);
    }

    /**
     * Submit a form specifying the form name only once.
     *
     * Use this function instead of [`$I->submitForm()`](#submitForm) to avoid repeating the form name in the field selectors.
     * If you customized the names of the field selectors use `$I->submitForm()` for full control.
     *
     * ```php
     * <?php
     * $I->submitSymfonyForm('login_form', [
     *     '[email]'    => 'john_doe@example.com',
     *     '[password]' => 'secretForest'
     * ]);
     * ```
     *
     * @param string $name The `name` attribute of the `<form>` (you cannot use an array as selector here)
     * @param string[] $fields
     */
    public function submitSymfonyForm(string $name, array $fields): void
    {
        $selector = sprintf('form[name=%s]', $name);

        $params = [];
        foreach ($fields as $key => $value) {
            $fixedKey = sprintf('%s%s', $name, $key);
            $params[$fixedKey] = $value;
        }

        $button = sprintf('%s_submit', $name);

        $this->submitForm($selector, $params, $button);
    }

    protected function assertThatForResponse(Constraint $constraint, string $message = ''): void
    {
        $this->assertThat($this->getClient()->getResponse(), $constraint, $message);
    }
}
