<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\Messenger\DataCollector\MessengerDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

use function class_exists;
use function count;
use function is_string;
use function sprintf;

trait MessengerAssertionsTrait
{
    /**
     * Asserts no message of the given class was dispatched (optionally on a single bus).
     *
     * ```php
     * <?php
     * $I->dontSeeMessageDispatched(SendWelcomeEmail::class);
     * $I->dontSeeMessageDispatched(SendWelcomeEmail::class, 'messenger.bus.default');
     * ```
     *
     * @param class-string $messageClass
     */
    public function dontSeeMessageDispatched(string $messageClass, ?string $bus = null): void
    {
        $this->assertNotContains(
            $messageClass,
            $this->getDispatchedMessageClasses(__FUNCTION__, $bus),
            sprintf("The '%s' message was dispatched%s.", $messageClass, $this->busSuffix($bus)),
        );
    }

    /**
     * Returns the dispatched message class names, in dispatch order (optionally for a single bus).
     *
     * The profiler stores cloned snapshots, so this yields class names, not the message objects.
     *
     * ```php
     * <?php
     * $classes = $I->grabDispatchedMessageClasses();
     * $classes = $I->grabDispatchedMessageClasses('messenger.bus.default');
     * ```
     *
     * @return list<class-string>
     */
    public function grabDispatchedMessageClasses(?string $bus = null): array
    {
        return $this->getDispatchedMessageClasses(__FUNCTION__, $bus);
    }

    /**
     * Asserts how many messages were dispatched (optionally on a single bus).
     *
     * ```php
     * <?php
     * $I->seeDispatchedMessageCount(1);
     * $I->seeDispatchedMessageCount(2, 'messenger.bus.default');
     * ```
     */
    public function seeDispatchedMessageCount(int $expectedCount, ?string $bus = null): void
    {
        $messages = $this->grabMessengerCollector(__FUNCTION__)->getMessages($bus);

        $this->assertCount(
            $expectedCount,
            $messages,
            sprintf(
                'Expected %d message(s) to be dispatched%s, but %d were.',
                $expectedCount,
                $this->busSuffix($bus),
                count($messages),
            ),
        );
    }

    /**
     * Asserts at least one message of the given class was dispatched (optionally on a single bus).
     *
     * ```php
     * <?php
     * $I->seeMessageDispatched(SendWelcomeEmail::class);
     * $I->seeMessageDispatched(SendWelcomeEmail::class, 'messenger.bus.default');
     * ```
     *
     * @param class-string $messageClass
     */
    public function seeMessageDispatched(string $messageClass, ?string $bus = null): void
    {
        $this->assertContains(
            $messageClass,
            $this->getDispatchedMessageClasses(__FUNCTION__, $bus),
            sprintf("The '%s' message was not dispatched%s.", $messageClass, $this->busSuffix($bus)),
        );
    }

    /**
     * @return list<class-string>
     */
    private function getDispatchedMessageClasses(string $callingFunction, ?string $bus): array
    {
        $classes = [];
        foreach ($this->grabMessengerCollector($callingFunction)->getMessages($bus) as $entry) {
            if (!$entry instanceof Data) {
                continue;
            }

            $message = $entry['message'];
            $type = $message instanceof Data ? ($message['type'] ?? null) : null;
            if ($type instanceof Data) {
                $type = $type->getValue();
            }

            if (is_string($type) && class_exists($type)) {
                $classes[] = $type;
            }
        }

        return $classes;
    }

    private function busSuffix(?string $bus): string
    {
        return $bus !== null ? sprintf(" on bus '%s'", $bus) : '';
    }

    protected function grabMessengerCollector(string $callingFunction): MessengerDataCollector
    {
        return $this->grabCollector(DataCollectorName::MESSENGER, $callingFunction);
    }
}
