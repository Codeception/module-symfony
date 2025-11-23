<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Constraint\Constraint;
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
     * Asserts that the given cookie in the test client is set to the expected value.
     *
     * ```php
     * <?php
     * $I->assertBrowserCookieValueSame('cookie_name', 'expected_value');
     * ```
     */
    public function assertBrowserCookieValueSame(string $name, string $expectedValue, bool $raw = false, string $path = '/', ?string $domain = null, string $message = ''): void
    {
        $this->assertThatForClient(new BrowserHasCookie($name, $path, $domain), $message);
        $this->assertThatForClient(new BrowserCookieValueSame($name, $expectedValue, $raw, $path, $domain), $message);
    }

    /**
     * Asserts that the test client has the specified cookie set.
     * This indicates that the cookie was set by any response during the test.
     *
     * ```
     * <?php
     * $I->assertBrowserHasCookie('cookie_name');
     * ```
     */
    public function assertBrowserHasCookie(string $name, string $path = '/', ?string $domain = null, string $message = ''): void
    {
        $this->assertThatForClient(new BrowserHasCookie($name, $path, $domain), $message);
    }

    /**
     * Asserts that the test client does not have the specified cookie set.
     * This indicates that the cookie was not set by any response during the test.
     *
     * ```php
     * <?php
     * $I->assertBrowserNotHasCookie('cookie_name');
     * ```
     */
    public function assertBrowserNotHasCookie(string $name, string $path = '/', ?string $domain = null, string $message = ''): void
    {
        $this->assertThatForClient(new LogicalNot(new BrowserHasCookie($name, $path, $domain)), $message);
    }

    /**
     * Asserts that the specified request attribute matches the expected value.
     *
     * ```php
     * <?php
     * $I->assertRequestAttributeValueSame('attribute_name', 'expected_value');
     * ```
     */
    public function assertRequestAttributeValueSame(string $name, string $expectedValue, string $message = ''): void
    {
        $this->assertThat($this->getClient()->getRequest(), new RequestAttributeValueSame($name, $expectedValue), $message);
    }

    /**
     * Asserts that the specified response cookie is present and matches the expected value.
     *
     * ```php
     * <?php
     * $I->assertResponseCookieValueSame('cookie_name', 'expected_value');
     * ```
     */
    public function assertResponseCookieValueSame(string $name, string $expectedValue, string $path = '/', ?string $domain = null, string $message = ''): void
    {
        $this->assertThatForResponse(new ResponseHasCookie($name, $path, $domain), $message);
        $this->assertThatForResponse(new ResponseCookieValueSame($name, $expectedValue, $path, $domain), $message);
    }

    /**
     * Asserts that the response format matches the expected format. This checks the format returned by the `Response::getFormat()` method.
     *
     * ```php
     * <?php
     * $I->assertResponseFormatSame('json');
     * ```
     */
    public function assertResponseFormatSame(?string $expectedFormat, string $message = ''): void
    {
        $this->assertThatForResponse(new ResponseFormatSame($this->getClient()->getRequest(), $expectedFormat), $message);
    }

    /**
     * Asserts that the specified cookie is present in the response. Optionally, it can check for a specific cookie path or domain.
     *
     * ```php
     * <?php
     * $I->assertResponseHasCookie('cookie_name');
     * ```
     */
    public function assertResponseHasCookie(string $name, string $path = '/', ?string $domain = null, string $message = ''): void
    {
        $this->assertThatForResponse(new ResponseHasCookie($name, $path, $domain), $message);
    }

    /**
     * Asserts that the specified header is available in the response.
     * For example, use `assertResponseHasHeader('content-type');`.
     *
     * ```php
     * <?php
     * $I->assertResponseHasHeader('content-type');
     * ```
     */
    public function assertResponseHasHeader(string $headerName, string $message = ''): void
    {
        $this->assertThatForResponse(new ResponseHasHeader($headerName), $message);
    }

    /**
     * Asserts that the specified header does not contain the expected value in the response.
     * For example, use `assertResponseHeaderNotSame('content-type', 'application/octet-stream');`.
     *
     * ```php
     * <?php
     * $I->assertResponseHeaderNotSame('content-type', 'application/json');
     * ```
     */
    public function assertResponseHeaderNotSame(string $headerName, string $expectedValue, string $message = ''): void
    {
        $this->assertThatForResponse(new LogicalNot(new ResponseHeaderSame($headerName, $expectedValue)), $message);
    }

    /**
     * Asserts that the specified header contains the expected value in the response.
     * For example, use `assertResponseHeaderSame('content-type', 'application/octet-stream');`.
     *
     * ```php
     * <?php
     * $I->assertResponseHeaderSame('content-type', 'application/json');
     * ```
     */
    public function assertResponseHeaderSame(string $headerName, string $expectedValue, string $message = ''): void
    {
        $this->assertThatForResponse(new ResponseHeaderSame($headerName, $expectedValue), $message);
    }

    /**
     * Asserts that the response was successful (HTTP status code is in the 2xx range).
     *
     * ```php
     * <?php
     * $I->assertResponseIsSuccessful();
     * ```
     */
    public function assertResponseIsSuccessful(string $message = '', bool $verbose = true): void
    {
        $this->assertThatForResponse(new ResponseIsSuccessful($verbose), $message);
    }

    /**
     * Asserts that the response is unprocessable (HTTP status code is 422).
     *
     * ```php
     * <?php
     * $I->assertResponseIsUnprocessable();
     * ```
     */
    public function assertResponseIsUnprocessable(string $message = '', bool $verbose = true): void
    {
        $this->assertThatForResponse(new ResponseIsUnprocessable($verbose), $message);
    }

    /**
     * Asserts that the specified cookie is not present in the response. Optionally, it can check for a specific cookie path or domain.
     *
     * ```php
     * <?php
     * $I->assertResponseNotHasCookie('cookie_name');
     * ```
     */
    public function assertResponseNotHasCookie(string $name, string $path = '/', ?string $domain = null, string $message = ''): void
    {
        $this->assertThatForResponse(new LogicalNot(new ResponseHasCookie($name, $path, $domain)), $message);
    }

    /**
     * Asserts that the specified header is not available in the response.
     *
     * ```php
     * <?php
     * $I->assertResponseNotHasHeader('content-type');
     * ```
     */
    public function assertResponseNotHasHeader(string $headerName, string $message = ''): void
    {
        $this->assertThatForResponse(new LogicalNot(new ResponseHasHeader($headerName)), $message);
    }

    /**
     * Asserts that the response is a redirect. Optionally, you can check the target location and status code.
     * The expected location can be either an absolute or a relative path.
     *
     * ```php
     * <?php
     * // Check that '/admin' redirects to '/login' with status code 302
     * $I->assertResponseRedirects('/login', 302);
     * ```
     */
    public function assertResponseRedirects(?string $expectedLocation = null, ?int $expectedCode = null, string $message = '', bool $verbose = true): void
    {
        $this->assertThatForResponse(new ResponseIsRedirected($verbose), $message);

        if ($expectedLocation) {
            $constraint = class_exists(ResponseHeaderLocationSame::class)
                ? new ResponseHeaderLocationSame($this->getClient()->getRequest(), $expectedLocation)
                : new ResponseHeaderSame('Location', $expectedLocation);
            $this->assertThatForResponse($constraint, $message);
        }

        if ($expectedCode) {
            $this->assertThatForResponse(new ResponseStatusCodeSame($expectedCode), $message);
        }
    }

    /**
     * Asserts that the response status code matches the expected code.
     *
     * ```php
     * <?php
     * $I->assertResponseStatusCodeSame(200);
     * ```
     */
    public function assertResponseStatusCodeSame(int $expectedCode, string $message = '', bool $verbose = true): void
    {
        $this->assertThatForResponse(new ResponseStatusCodeSame($expectedCode, $verbose), $message);
    }

    /**
     * Asserts the request matches the given route and optionally route parameters.
     *
     * ```php
     * <?php
     * $I->assertRouteSame('profile', ['id' => 123]);
     * ```
     *
     * @param array<string, bool|float|int|null|string> $parameters
     */
    public function assertRouteSame(string $expectedRoute, array $parameters = [], string $message = ''): void
    {
        $request = $this->getClient()->getRequest();
        $this->assertThat($request, new RequestAttributeValueSame('_route', $expectedRoute));

        foreach ($parameters as $key => $value) {
            $this->assertThat($request, new RequestAttributeValueSame($key, (string)$value), $message);
        }
    }

    /**
     * Reboots the client's kernel.
     * Can be used to manually reboot the kernel when 'rebootable_client' is set to false.
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
     * By default, it checks the current page. Specify the `$url` parameter to change the page being checked.
     *
     * ```php
     * <?php
     * $I->amOnPage('/dashboard');
     * $I->seePageIsAvailable();
     *
     * $I->seePageIsAvailable('/dashboard'); // Same as above
     * ```
     *
     * @param string|null $url The URL of the page to check. If null, the current page is checked.
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
     * Navigates to a page and verifies that it redirects to another page.
     *
     * ```php
     * <?php
     * $I->seePageRedirectsTo('/admin', '/login');
     * ```
     */
    public function seePageRedirectsTo(string $page, string $redirectsTo): void
    {
        $client = $this->getClient();
        $client->followRedirects(false);
        $this->amOnPage($page);

        $this->assertThatForResponse(new ResponseIsRedirected(), 'The response is not a redirection.');

        $client->followRedirect();
        $this->seeInCurrentUrl($redirectsTo);
    }

    /**
     * Submits a form by specifying the form name only once.
     *
     * Use this function instead of [`$I->submitForm()`](#submitForm) to avoid repeating the form name in the field selectors.
     * If you have customized the names of the field selectors, use `$I->submitForm()` for full control.
     *
     * ```php
     * <?php
     * $I->submitSymfonyForm('login_form', [
     *     '[email]'    => 'john_doe@example.com',
     *     '[password]' => 'secretForest'
     * ]);
     * ```
     *
     * @param string               $name   The `name` attribute of the `<form>`. You cannot use an array as a selector here.
     * @param array<string, mixed> $fields The form fields to submit.
     */
    public function submitSymfonyForm(string $name, array $fields): void
    {
        $selector = sprintf('form[name=%s]', $name);

        $params = [];
        foreach ($fields as $key => $value) {
            $params[$name . $key] = $value;
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
