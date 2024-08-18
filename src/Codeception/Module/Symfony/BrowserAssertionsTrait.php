<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\Constraint\LogicalAnd;
use PHPUnit\Framework\Constraint\LogicalNot;
use Symfony\Component\BrowserKit\Test\Constraint\BrowserCookieValueSame;
use Symfony\Component\BrowserKit\Test\Constraint\BrowserHasCookie;
use Symfony\Component\HttpFoundation\Test\Constraint\RequestAttributeValueSame;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseCookieValueSame;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseFormatSame;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseHasCookie;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseHasHeader;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseHeaderLocationSame;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseHeaderSame;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseIsRedirected;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseIsSuccessful;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseIsUnprocessable;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseStatusCodeSame;
use function sprintf;

trait BrowserAssertionsTrait
{
    /**
     * Asserts the given cookie in the test Client is set to the expected value.
     */
    public function assertBrowserCookieValueSame(string $name, string $expectedValue, bool $raw = false, string $path = '/', ?string $domain = null, string $message = ''): void
    {
        $this->assertThatForClient(LogicalAnd::fromConstraints(
            new BrowserHasCookie($name, $path, $domain),
            new BrowserCookieValueSame($name, $expectedValue, $raw, $path, $domain)
        ), $message);
    }

    /**
     * Asserts that the test Client does have the given cookie set (meaning, the cookie was set by any response in the test).
     */
    public function assertBrowserHasCookie(string $name, string $path = '/', ?string $domain = null, string $message = ''): void
    {
        $this->assertThatForClient(new BrowserHasCookie($name, $path, $domain), $message);
    }

    /**
     * Asserts that the test Client does not have the given cookie set (meaning, the cookie was set by any response in the test).
     */
    public function assertBrowserNotHasCookie(string $name, string $path = '/', ?string $domain = null, string $message = ''): void
    {
        $this->assertThatForClient(new LogicalNot(new BrowserHasCookie($name, $path, $domain)), $message);
    }

    /**
     * Asserts the given request attribute is set to the expected value.
     */
    public function assertRequestAttributeValueSame(string $name, string $expectedValue, string $message = ''): void
    {
        $this->assertThat($this->getClient()->getRequest(), new RequestAttributeValueSame($name, $expectedValue), $message);
    }

    /**
     * Asserts the given cookie is present and set to the expected value.
     */
    public function assertResponseCookieValueSame(string $name, string $expectedValue, string $path = '/', ?string $domain = null, string $message = ''): void
    {
        $this->assertThatForResponse(LogicalAnd::fromConstraints(
            new ResponseHasCookie($name, $path, $domain),
            new ResponseCookieValueSame($name, $expectedValue, $path, $domain)
        ), $message);
    }

    /**
     * Asserts the response format returned by the `Response::getFormat()` method is the same as the expected value.
     */
    public function assertResponseFormatSame(?string $expectedFormat, string $message = ''): void
    {
        $this->assertThatForResponse(new ResponseFormatSame($this->getClient()->getRequest(), $expectedFormat), $message);
    }

    /**
     * Asserts the given cookie is present in the response (optionally checking for a specific cookie path or domain).
     */
    public function assertResponseHasCookie(string $name, string $path = '/', ?string $domain = null, string $message = ''): void
    {
        $this->assertThatForResponse(new ResponseHasCookie($name, $path, $domain), $message);
    }

    /**
     * Asserts the given header is available on the response, e.g. assertResponseHasHeader('content-type');.
     */
    public function assertResponseHasHeader(string $headerName, string $message = ''): void
    {
        $this->assertThatForResponse(new ResponseHasHeader($headerName), $message);
    }

    /**
     * Asserts the given header does not contain the expected value on the response,
     * e.g. assertResponseHeaderNotSame('content-type', 'application/octet-stream');.
     */
    public function assertResponseHeaderNotSame(string $headerName, string $expectedValue, string $message = ''): void
    {
        $this->assertThatForResponse(new LogicalNot(new ResponseHeaderSame($headerName, $expectedValue)), $message);
    }

    /**
     * Asserts the given header does contain the expected value on the response,
     * e.g. assertResponseHeaderSame('content-type', 'application/octet-stream');.
     */
    public function assertResponseHeaderSame(string $headerName, string $expectedValue, string $message = ''): void
    {
        $this->assertThatForResponse(new ResponseHeaderSame($headerName, $expectedValue), $message);
    }

    /**
     * Asserts that the response was successful (HTTP status is 2xx).
     */
    public function assertResponseIsSuccessful(string $message = '', bool $verbose = true): void
    {
        $this->assertThatForResponse(new ResponseIsSuccessful($verbose), $message);
    }

    /**
     * Asserts the response is unprocessable (HTTP status is 422)
     */
    public function assertResponseIsUnprocessable(string $message = '', bool $verbose = true): void
    {
        $this->assertThatForResponse(new ResponseIsUnprocessable($verbose), $message);
    }

    /**
     * Asserts the given cookie is not present in the response (optionally checking for a specific cookie path or domain).
     */
    public function assertResponseNotHasCookie(string $name, string $path = '/', ?string $domain = null, string $message = ''): void
    {
        $this->assertThatForResponse(new LogicalNot(new ResponseHasCookie($name, $path, $domain)), $message);
    }

    /**
     * Asserts the given header is not available on the response, e.g. assertResponseNotHasHeader('content-type');.
     */
    public function assertResponseNotHasHeader(string $headerName, string $message = ''): void
    {
        $this->assertThatForResponse(new LogicalNot(new ResponseHasHeader($headerName)), $message);
    }

    /**
     * Asserts the response is a redirect response (optionally, you can check the target location and status code).
     * The excepted location can be either an absolute or a relative path.
     */
    public function assertResponseRedirects(?string $expectedLocation = null, ?int $expectedCode = null, string $message = '', bool $verbose = true): void
    {
        $constraint = new ResponseIsRedirected($verbose);
        if ($expectedLocation) {
            if (class_exists(ResponseHeaderLocationSame::class)) {
                $locationConstraint = new ResponseHeaderLocationSame($this->getClient()->getRequest(), $expectedLocation);
            } else {
                $locationConstraint = new ResponseHeaderSame('Location', $expectedLocation);
            }

            $constraint = LogicalAnd::fromConstraints($constraint, $locationConstraint);
        }
        if ($expectedCode) {
            $constraint = LogicalAnd::fromConstraints($constraint, new ResponseStatusCodeSame($expectedCode));
        }

        $this->assertThatForResponse($constraint, $message);
    }

    /**
     * Asserts a specific HTTP status code.
     */
    public function assertResponseStatusCodeSame(int $expectedCode, string $message = '', bool $verbose = true): void
    {
        $this->assertThatForResponse(new ResponseStatusCodeSame($expectedCode, $verbose), $message);
    }

    /**
     * Asserts the request matches the given route and optionally route parameters.
     */
    public function assertRouteSame(string $expectedRoute, array $parameters = [], string $message = ''): void
    {
        $constraint = new RequestAttributeValueSame('_route', $expectedRoute);
        $constraints = [];
        foreach ($parameters as $key => $value) {
            $constraints[] = new RequestAttributeValueSame($key, $value);
        }
        if ($constraints) {
            $constraint = LogicalAnd::fromConstraints($constraint, ...$constraints);
        }

        $this->assertThat($this->getClient()->getRequest(), $constraint, $message);
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

    protected function assertThatForClient(Constraint $constraint, string $message = ''): void
    {
        $this->assertThat($this->getClient(), $constraint, $message);
    }

    protected function assertThatForResponse(Constraint $constraint, string $message = ''): void
    {
        $this->assertThat($this->getClient()->getResponse(), $constraint, $message);
    }
}
