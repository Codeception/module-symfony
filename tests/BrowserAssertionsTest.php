<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\BrowserAssertionsTrait;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\Test\Constraint\BrowserHistoryIsOnFirstPage;
use Symfony\Component\BrowserKit\Test\Constraint\BrowserHistoryIsOnLastPage;
use Tests\Support\CodeceptTestCase;

use function class_exists;

final class BrowserAssertionsTest extends CodeceptTestCase
{
    use BrowserAssertionsTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->followRedirects(false);
        $this->client->getCookieJar()->set(new Cookie('browser_cookie', 'value'));
    }

    public function testAssertBrowserCookieValueSame(): void
    {
        $this->assertBrowserCookieValueSame('browser_cookie', 'value');
    }

    public function testAssertBrowserHasCookie(): void
    {
        $this->assertBrowserHasCookie('browser_cookie');
    }

    public function testAssertBrowserNotHasCookie(): void
    {
        $this->client->getCookieJar()->expire('browser_cookie');
        $this->assertBrowserNotHasCookie('browser_cookie');
    }

    public function testAssertBrowserHistoryIsOnFirstPage(): void
    {
        if (!class_exists(BrowserHistoryIsOnFirstPage::class)) {
            $this->markTestSkipped('Browser history assertions require symfony/browser-kit with BrowserHistoryIsOnFirstPage support.');
        }

        $this->client->request('GET', '/');
        $this->assertBrowserHistoryIsOnFirstPage();
    }

    public function testAssertBrowserHistoryIsNotOnFirstPage(): void
    {
        if (!class_exists(BrowserHistoryIsOnFirstPage::class)) {
            $this->markTestSkipped('Browser history assertions require symfony/browser-kit with BrowserHistoryIsOnFirstPage support.');
        }

        $this->client->request('GET', '/');
        $this->client->request('GET', '/login');
        $this->assertBrowserHistoryIsNotOnFirstPage();
    }

    public function testAssertBrowserHistoryIsOnLastPage(): void
    {
        if (!class_exists(BrowserHistoryIsOnLastPage::class)) {
            $this->markTestSkipped('Browser history assertions require symfony/browser-kit with BrowserHistoryIsOnLastPage support.');
        }

        $this->client->request('GET', '/');
        $this->client->request('GET', '/login');
        $this->assertBrowserHistoryIsOnLastPage();
    }

    public function testAssertBrowserHistoryIsNotOnLastPage(): void
    {
        if (!class_exists(BrowserHistoryIsOnLastPage::class)) {
            $this->markTestSkipped('Browser history assertions require symfony/browser-kit with BrowserHistoryIsOnLastPage support.');
        }

        $this->client->request('GET', '/');
        $this->client->request('GET', '/login');
        $this->client->back();
        $this->assertBrowserHistoryIsNotOnLastPage();
    }

    public function testAssertRequestAttributeValueSame(): void
    {
        $this->client->request('GET', '/request_attr');
        $this->assertRequestAttributeValueSame('page', 'register');
    }

    public function testAssertResponseCookieValueSame(): void
    {
        $this->client->request('GET', '/response_cookie');
        $this->assertResponseCookieValueSame('TESTCOOKIE', 'codecept');
    }

    public function testAssertResponseFormatSame(): void
    {
        $this->client->request('GET', '/response_json');
        $this->assertResponseFormatSame('json');
    }

    public function testAssertResponseHasCookie(): void
    {
        $this->client->request('GET', '/response_cookie');
        $this->assertResponseHasCookie('TESTCOOKIE');
    }

    public function testAssertResponseHasHeader(): void
    {
        $this->client->request('GET', '/response_json');
        $this->assertResponseHasHeader('content-type');
    }

    public function testAssertResponseHeaderNotSame(): void
    {
        $this->client->request('GET', '/response_json');
        $this->assertResponseHeaderNotSame('content-type', 'application/octet-stream');
    }

    public function testAssertResponseHeaderSame(): void
    {
        $this->client->request('GET', '/response_json');
        $this->assertResponseHeaderSame('content-type', 'application/json');
    }

    public function testAssertResponseIsSuccessful(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
    }

    public function testAssertResponseIsUnprocessable(): void
    {
        $this->client->request('GET', '/unprocessable_entity');
        $this->assertResponseIsUnprocessable();
    }

    public function testAssertResponseNotHasCookie(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseNotHasCookie('TESTCOOKIE');
    }

    public function testAssertResponseNotHasHeader(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseNotHasHeader('accept-charset');
    }

    public function testAssertResponseRedirects(): void
    {
        $this->client->followRedirects(false);
        $this->client->request('GET', '/redirect_home');
        $this->assertResponseRedirects();
        $this->assertResponseRedirects('/');
    }

    public function testAssertResponseStatusCodeSame(): void
    {
        $this->client->followRedirects(false);
        $this->client->request('GET', '/redirect_home');
        $this->assertResponseStatusCodeSame(302);
    }

    public function testAssertRouteSame(): void
    {
        $this->client->request('GET', '/');
        $this->assertRouteSame('index');
        $this->client->request('GET', '/login');
        $this->assertRouteSame('app_login');
    }

    public function testRebootClientKernel(): void
    {
        $this->markTestSkipped('This method relies on Codeception\Lib\Connector\Symfony::rebootKernel(), which is not available in KernelBrowser.');
    }

    public function testSeePageIsAvailable(): void
    {
        $this->seePageIsAvailable('/login');
        $this->client->request('GET', '/register');
        $this->seePageIsAvailable();
    }

    public function testSeePageRedirectsTo(): void
    {
        $this->seePageRedirectsTo('/dashboard', '/login');
    }

    public function testSubmitSymfonyForm(): void
    {
        $this->client->request('GET', '/register');
        $this->submitSymfonyForm('registration_form', [
            '[email]' => 'jane_doe@gmail.com',
            '[password]' => '123456',
            '[agreeTerms]' => true,
        ]);
        $this->assertResponseRedirects('/dashboard');
    }
}
