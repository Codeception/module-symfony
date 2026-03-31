<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\HttpClientAssertionsTrait;
use Symfony\Component\HttpKernel\Kernel;
use Tests\Support\CodeceptTestCase;

final class HttpClientAssertionsTest extends CodeceptTestCase
{
    use HttpClientAssertionsTrait;

    protected function setUp(): void
    {
        parent::setUp();
        if (Kernel::VERSION_ID < 60000) {
            $this->markTestSkipped('HttpClient data collection is not reliable in this test environment for Symfony 5.4');
        }
        $this->client->request('GET', '/http-client');
    }

    public function testAssertHttpClientRequest(): void
    {
        $this->assertHttpClientRequest('https://example.com/default', 'GET', null, ['X-Test' => 'yes'], 'app.http_client');
        $this->assertHttpClientRequest('https://example.com/body', 'POST', ['example' => 'payload'], [], 'app.http_client');
        $this->assertHttpClientRequest('https://api.example.com/resource', 'GET', null, [], 'app.http_client.json_client');
    }

    public function testAssertHttpClientRequestCount(): void
    {
        $this->assertHttpClientRequestCount(2, 'app.http_client');
        $this->assertHttpClientRequestCount(1, 'app.http_client.json_client');
    }

    public function testAssertNotHttpClientRequest(): void
    {
        $this->assertNotHttpClientRequest('https://example.com/missing', 'GET', 'app.http_client');
    }
}
