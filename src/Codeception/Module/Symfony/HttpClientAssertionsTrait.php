<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;
use function array_change_key_case;
use function array_filter;
use function array_intersect_key;
use function array_key_exists;
use function in_array;
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
        $matchingRequests = array_filter(
            $this->getHttpClientTraces($httpClientId, __FUNCTION__),
            function (array $trace) use ($expectedUrl, $expectedMethod, $expectedBody, $expectedHeaders): bool {
                if (!$this->matchesUrlAndMethod($trace, $expectedUrl, $expectedMethod)) {
                    return false;
                }

                $options     = $trace['options'] ?? [];
                $actualBody  = $this->extractValue($options['body'] ?? $options['json'] ?? null);
                $bodyMatches = $expectedBody === null || $expectedBody === $actualBody;

                $headersMatch = $expectedHeaders === [] || (
                        is_array($headerValues = $this->extractValue($options['headers'] ?? []))
                        && ($normalizedExpected = array_change_key_case($expectedHeaders))
                        === array_intersect_key(array_change_key_case($headerValues), $normalizedExpected)
                    );

                return $bodyMatches && $headersMatch;
            },
        );

        $this->assertNotEmpty(
            $matchingRequests,
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
     * ```php
     * $I->assertNotHttpClientRequest('https://example.com/unexpected', 'GET');
     * ```
     */
    public function assertNotHttpClientRequest(
        string $unexpectedUrl,
        string $unexpectedMethod = 'GET',
        string $httpClientId = 'http_client',
    ): void {
        $matchingRequests = array_filter(
            $this->getHttpClientTraces($httpClientId, __FUNCTION__),
            fn(array $trace): bool => $this->matchesUrlAndMethod($trace, $unexpectedUrl, $unexpectedMethod)
        );

        $this->assertEmpty(
            $matchingRequests,
            sprintf('Unexpected URL was called: "%s" - "%s"', $unexpectedMethod, $unexpectedUrl)
        );
    }

    /**
     * @return list<array{
     *     info: array{url: string},
     *     url: string,
     *     method: string,
     *     options?: array{body?: mixed, json?: mixed, headers?: mixed}
     * }>
     */
    private function getHttpClientTraces(string $httpClientId, string $function): array
    {
        $httpClientCollector = $this->grabHttpClientCollector($function);

        /** @var array<string, array{traces: list<array{
         *     info: array{url: string},
         *     url: string,
         *     method: string,
         *     options?: array{body?: mixed, json?: mixed, headers?: mixed}
         * }>}> $clients
         */
        $clients = $httpClientCollector->getClients();

        if (!array_key_exists($httpClientId, $clients)) {
            $this->fail(sprintf('HttpClient "%s" is not registered.', $httpClientId));
        }

        return $clients[$httpClientId]['traces'];
    }

    /** @param array{info: array{url: string}, url: string, method: string} $trace */
    private function matchesUrlAndMethod(array $trace, string $expectedUrl, string $expectedMethod): bool
    {
        return in_array($expectedUrl, [$trace['info']['url'], $trace['url']], true)
            && $expectedMethod === $trace['method'];
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
