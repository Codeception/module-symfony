<?php

declare(strict_types=1);

namespace Tests\App\MessageHandler;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Tests\App\Message\TestMessage;

#[AsMessageHandler]
final class TestMessageHandler
{
    /** @var list<string> */
    public array $handled = [];

    public function __invoke(TestMessage $message): void
    {
        $this->handled[] = $message->getContent();
    }
}
