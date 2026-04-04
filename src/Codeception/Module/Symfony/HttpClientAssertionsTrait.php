<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Assert;
use Stringable;
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

use function array_change_key_case;
use function array_intersect_key;
use function is_array;
use function is_object;
use function method_exists;
use function sprintf;

trait HttpClientAssertionsTrait
{
    /**
     * Asserts that the given URL has been called using, if specified, the given method, body and/or headers.
     * By default, it will inspect the default Symfony HttpClient; you may check a different one by passing its
     * service-id in $httpClientId.
     * It succeeds even if the request was executed multiple times.
     *
     * ```php
     * <?php
     * $I->assertHttpClientRequest(
     *     'https://example.com/api',
     *     'POST',
     *     '{"data": "value"}',
     *     ['Authorization' => 'Bearer token']
     * );
     * ```
     *
     * @param string|array<mixed>|null      $expectedBody
     * @param array<string,string|string[]> $expectedHeaders
     */
    public function assertHttpClientRequest(
        string            $expectedUrl,
        string            $expectedMethod = 'GET',
        string|array|null $expectedBody   = null,
        array             $expectedHeaders = [],
        string            $httpClientId = 'http_client',
    ): void {
        $this->assertTrue(
            $this->hasHttpClientRequest($httpClientId, __FUNCTION__, $expectedUrl, $expectedMethod, $expectedBody, $expectedHeaders),
            sprintf('The expected request has not been called: "%s" - "%s"', $expectedMethod, $expectedUrl)
        );
    }

    /**
     * Asserts that exactly $count requests have been executed by the given HttpClient.
     * By default, it will inspect the default Symfony HttpClient; you may check a different one by passing its
     * service-id in $httpClientId.
     *
     * ```php
     * $I->assertHttpClientRequestCount(3);
     * ```
     */
    public function assertHttpClientRequestCount(int $count, string $httpClientId = 'http_client'): void
    {
        $this->assertCount($count, $this->getHttpClientTraces($httpClientId, __FUNCTION__));
    }

    /**
     * Asserts that the given URL *has not* been requested with the supplied HTTP method.
     * By default, it will inspect the default Symfony HttpClient; you may check a different one by passing its
     * service-id in $httpClientId.
     *
     * ```php
     * $I->assertNotHttpClientRequest('https://example.com/unexpected', 'GET');
     * ```
     */
    public function assertNotHttpClientRequest(
        string $unexpectedUrl,
        string $unexpectedMethod = 'GET',
        string $httpClientId = 'http_client',
    ): void {
        $this->assertFalse(
            $this->hasHttpClientRequest($httpClientId, __FUNCTION__, $unexpectedUrl, $unexpectedMethod),
            sprintf('Unexpected URL was called: "%s" - "%s"', $unexpectedMethod, $unexpectedUrl)
        );
    }

    /**
     * @param string|array<mixed>|null $expectedBody
     * @param array<string,string|string[]> $expectedHeaders
     */
    private function hasHttpClientRequest(
        string $httpClientId,
        string $function,
        string $expectedUrl,
        string $expectedMethod,
        string|array|null $expectedBody = null,
        array $expectedHeaders = []
    ): bool {
        $expectedHeadersLower = $expectedHeaders === [] ? [] : array_change_key_case($expectedHeaders);

        foreach ($this->getHttpClientTraces($httpClientId, $function) as $trace) {
            if (!is_array($trace) || ($trace['method'] ?? null) !== $expectedMethod) {
                continue;
            }

            $info = $this->extractValue($trace['info'] ?? []);
            $infoUrl = is_array($info) ? ($info['url'] ?? $info['original_url'] ?? null) : null;
            if ($expectedUrl !== $infoUrl && $expectedUrl !== ($trace['url'] ?? null)) {
                continue;
            }

            if ($expectedBody === null && $expectedHeadersLower === []) {
                return true;
            }

            $options = $this->extractValue($trace['options'] ?? []);
            $options = is_array($options) ? $options : [];
            if ($expectedBody !== null && $expectedBody !== $this->extractValue($options['body'] ?? $options['json'] ?? null)) {
                continue;
            }

            if ($expectedHeadersLower === []) {
                return true;
            }

            $actualHeaders = $this->extractValue($options['headers'] ?? []);
            if (is_array($actualHeaders) && $expectedHeadersLower === array_intersect_key(array_change_key_case($actualHeaders), $expectedHeadersLower)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<mixed> */
    private function getHttpClientTraces(string $httpClientId, string $function): array
    {
        $clients = $this->grabHttpClientCollector($function)->getClients();
        if (!isset($clients[$httpClientId]) || !is_array($clients[$httpClientId])) {
            Assert::fail(sprintf('HttpClient "%s" is not registered.', $httpClientId));
        }

        /** @var array{traces: array<mixed>} $clientData */
        $clientData = $clients[$httpClientId];
        return $clientData['traces'];
    }

    private function extractValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof Data => $value->getValue(true),
            is_object($value) && method_exists($value, 'getValue') => $value->getValue(true),
            $value instanceof Stringable => (string) $value,
            default => $value,
        };
    }

    protected function grabHttpClientCollector(string $function): HttpClientDataCollector
    {
        return $this->grabCollector(DataCollectorName::HTTP_CLIENT, $function);
    }
}
