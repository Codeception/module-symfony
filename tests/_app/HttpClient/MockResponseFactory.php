<?php

declare(strict_types=1);

namespace Tests\App\HttpClient;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class MockResponseFactory
{
    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        $statusCode = match ($url) {
            'https://example.com/body' => 201,
            'https://api.example.com/resource' => 202,
            default => 200,
        };

        return new MockResponse(
            json_encode([
                'method' => $method,
                'url' => $url,
                'options' => $options,
            ], JSON_THROW_ON_ERROR),
            [
                'http_code' => $statusCode,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]
        );
    }
}
