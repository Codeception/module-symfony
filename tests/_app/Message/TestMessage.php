<?php

declare(strict_types=1);

namespace Tests\App\Message;

final class TestMessage
{
    public function __construct(
        private readonly string $content = '',
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
