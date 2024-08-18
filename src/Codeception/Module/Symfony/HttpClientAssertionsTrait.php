<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use function array_key_exists;
use function is_string;

trait HttpClientAssertionsTrait
{
    /**
     * Asserts that the given URL has been called using, if specified, the given method body and headers.
     * By default, it will check on the HttpClient, but you can also pass a specific HttpClient ID. (It will succeed if the request has been called multiple times.)
     */
    public function assertHttpClientRequest(string $expectedUrl, string $expectedMethod = 'GET', string|array|null $expectedBody = null, array $expectedHeaders = [], string $httpClientId = 'http_client'): void
    {
        $httpClientCollector = $this->grabHttpClientCollector(__FUNCTION__);
        $expectedRequestHasBeenFound = false;

        if (!array_key_exists($httpClientId, $httpClientCollector->getClients())) {
            $this->fail(sprintf('HttpClient "%s" is not registered.', $httpClientId));
        }

        foreach ($httpClientCollector->getClients()[$httpClientId]['traces'] as $trace) {
            if (($expectedUrl !== $trace['info']['url'] && $expectedUrl !== $trace['url'])
                || $expectedMethod !== $trace['method']
            ) {
                continue;
            }

            if (null !== $expectedBody) {
                $actualBody = null;

                if (null !== $trace['options']['body'] && null === $trace['options']['json']) {
                    $actualBody = is_string($trace['options']['body']) ? $trace['options']['body'] : $trace['options']['body']->getValue(true);
                }

                if (null === $trace['options']['body'] && null !== $trace['options']['json']) {
                    $actualBody = $trace['options']['json']->getValue(true);
                }

                if (!$actualBody) {
                    continue;
                }

                if ($expectedBody === $actualBody) {
                    $expectedRequestHasBeenFound = true;

                    if (!$expectedHeaders) {
                        break;
                    }
                }
            }

            if ($expectedHeaders) {
                $actualHeaders = $trace['options']['headers'] ?? [];

                foreach ($actualHeaders as $headerKey => $actualHeader) {
                    if (array_key_exists($headerKey, $expectedHeaders)
                        && $expectedHeaders[$headerKey] === $actualHeader->getValue(true)
                    ) {
                        $expectedRequestHasBeenFound = true;
                        break 2;
                    }
                }
            }

            $expectedRequestHasBeenFound = true;
            break;
        }

        $this->assertTrue($expectedRequestHasBeenFound, 'The expected request has not been called: "' . $expectedMethod . '" - "' . $expectedUrl . '"');
    }

    /**
     * Asserts that the given number of requests has been made on the HttpClient.
     * By default, it will check on the HttpClient, but you can also pass a specific HttpClient ID.
     */
    public function assertHttpClientRequestCount(int $count, string $httpClientId = 'http_client'): void
    {
        $httpClientCollector = $this->grabHttpClientCollector(__FUNCTION__);

        $this->assertCount($count, $httpClientCollector->getClients()[$httpClientId]['traces']);
    }

    /**
     * Asserts that the given URL has not been called using GET or the specified method.
     * By default, it will check on the HttpClient, but a HttpClient id can be specified.
     */
    public function assertNotHttpClientRequest(string $unexpectedUrl, string $expectedMethod = 'GET', string $httpClientId = 'http_client'): void
    {
        $httpClientCollector = $this->grabHttpClientCollector(__FUNCTION__);
        $unexpectedUrlHasBeenFound = false;

        if (!array_key_exists($httpClientId, $httpClientCollector->getClients())) {
            $this->fail(sprintf('HttpClient "%s" is not registered.', $httpClientId));
        }

        foreach ($httpClientCollector->getClients()[$httpClientId]['traces'] as $trace) {
            if (($unexpectedUrl === $trace['info']['url'] || $unexpectedUrl === $trace['url'])
                && $expectedMethod === $trace['method']
            ) {
                $unexpectedUrlHasBeenFound = true;
                break;
            }
        }

        $this->assertFalse($unexpectedUrlHasBeenFound, sprintf('Unexpected URL called: "%s" - "%s"', $expectedMethod, $unexpectedUrl));
    }

    protected function grabHttpClientCollector(string $function): HttpClientDataCollector
    {
        return $this->grabCollector('http_client', $function);
    }
}
