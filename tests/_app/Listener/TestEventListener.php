<?php

declare(strict_types=1);

namespace Tests\App\Listener;

use Tests\App\Event\TestEvent;

final class TestEventListener
{
    public function onTestEvent(TestEvent $event): void {}

    public function onNamedEvent(TestEvent $event): void {}
}
