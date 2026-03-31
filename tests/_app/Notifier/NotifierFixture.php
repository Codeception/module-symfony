<?php

declare(strict_types=1);

namespace Tests\App\Notifier;

use Symfony\Component\Notifier\Event\MessageEvent;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class NotifierFixture
{
    public function __construct(private EventDispatcherInterface $dispatcher) {}

    public function sendNotification(string $subject, ?string $transport = null, bool $queued = false): MessageEvent
    {
        $message = (new ChatMessage($subject))->transport($transport);
        $event = new MessageEvent($message, $queued);
        $this->dispatcher->dispatch($event);

        return $event;
    }
}
