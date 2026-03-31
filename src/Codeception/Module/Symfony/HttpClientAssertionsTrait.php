<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

use function array_change_key_case;
use function array_filter;
use function array_intersect_key;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
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
        $matchingTraces = array_filter(
            $this->getHttpClientTraces($httpClientId, __FUNCTION__),
            function ($trace) use ($expectedUrl, $expectedMethod, $expectedBody, $expectedHeaders): bool {
                if (!is_array($trace) || !$this->matchesUrlAndMethod($trace, $expectedUrl, $expectedMethod)) {
                    return false;
                }

                $options = $this->extractValue($trace['options'] ?? []);
                $options = is_array($options) ? $options : [];

                $expectedTraceBody = $this->extractValue($options['body'] ?? $options['json'] ?? null);
                if ($expectedBody !== null && $expectedBody !== $expectedTraceBody) {
                    return false;
                }

                if ($expectedHeaders === []) {
                    return true;
                }

                $actualHeaders = $this->extractValue($options['headers'] ?? []);
                $expected = array_change_key_case($expectedHeaders);

                return is_array($actualHeaders)
                    && $expected === array_intersect_key(array_change_key_case($actualHeaders), $expected);
            },
        );

        $this->assertNotEmpty(
            $matchingTraces,
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
        $matchingTraces = array_filter(
            $this->getHttpClientTraces($httpClientId, __FUNCTION__),
            fn($trace): bool => is_array($trace) && $this->matchesUrlAndMethod($trace, $unexpectedUrl, $unexpectedMethod),
        );

        $this->assertEmpty(
            $matchingTraces,
            sprintf('Unexpected URL was called: "%s" - "%s"', $unexpectedMethod, $unexpectedUrl)
        );
    }

    /** @return array<mixed> */
    private function getHttpClientTraces(string $httpClientId, string $function): array
    {
        $clients = $this->grabHttpClientCollector($function)->getClients();

        if (!isset($clients[$httpClientId])) {
            $this->fail(sprintf('HttpClient "%s" is not registered.', $httpClientId));
        }

        /** @var array{traces: array<mixed>} $clientData */
        $clientData = $clients[$httpClientId];
        return $clientData['traces'];
    }

    /** @param array<mixed> $trace */
    private function matchesUrlAndMethod(array $trace, string $expectedUrl, string $expectedMethod): bool
    {
        $method = $trace['method'] ?? null;
        $url = $trace['url'] ?? null;

        if (!is_string($method) || !is_string($url) || $expectedMethod !== $method) {
            return false;
        }

        $info = $this->extractValue($trace['info'] ?? []);
        $infoUrl = is_array($info) ? ($info['url'] ?? $info['original_url'] ?? null) : null;

        return in_array($expectedUrl, [$infoUrl, $url], true);
    }

    private function extractValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof Data => $value->getValue(true),
            is_object($value) && method_exists($value, 'getValue') => $value->getValue(true),
            is_object($value) && method_exists($value, '__toString') => (string) $value,
            default => $value,
        };
    }

    protected function grabHttpClientCollector(string $function): HttpClientDataCollector
    {
        return $this->grabCollector(DataCollectorName::HTTP_CLIENT, $function);
    }
}
