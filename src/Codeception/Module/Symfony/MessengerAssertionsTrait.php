<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Assert;
use Symfony\Component\Messenger\DataCollector\MessengerDataCollector;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\VarDumper\Cloner\Data;

use function class_exists;
use function count;
use function is_scalar;
use function is_string;
use function sprintf;

trait MessengerAssertionsTrait
{
    /**
     * Processes messages waiting on an in-memory transport by dispatching them
     * back through the message bus to their handlers, then acknowledging them.
     *
     * Use it to assert side effects that happen during handling (an email is sent,
     * a row is written, another message is dispatched).
     *
     * Requires the bus to be routed to a Symfony in-memory transport
     * (`MESSENGER_TRANSPORT_DSN=in-memory://`) in the test environment.
     *
     * ```php
     * <?php
     * $I->consumeMessengerMessages('async');           // process one message
     * $I->consumeMessengerMessages('async', limit: 5); // process up to five
     * ```
     */
    public function consumeMessengerMessages(string $transportName, int $limit = 1): void
    {
        $transport = $this->grabMessengerTransport($transportName);
        $bus = $this->grabMessageBus();

        $consumed = 0;
        foreach ($this->getQueuedEnvelopes($transportName) as $envelope) {
            if ($consumed >= $limit) {
                break;
            }

            $bus->dispatch($envelope->with(new ReceivedStamp($transportName), new ConsumedByWorkerStamp()));
            $transport->ack($envelope);
            ++$consumed;
        }
    }

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
     * Grabs a Symfony in-memory transport so you can inspect the real message
     * objects it holds via `getSent()`, `getAcknowledged()` and `getRejected()`.
     *
     * Unlike the profiler-based assertions, this exposes the actual envelopes
     * (with their payload and stamps), not a lossy class-name snapshot.
     *
     * Requires the bus to be routed to a Symfony in-memory transport
     * (`MESSENGER_TRANSPORT_DSN=in-memory://`) in the test environment.
     *
     * ```php
     * <?php
     * $message = $I->grabMessengerTransport('async')->getSent()[0]->getMessage();
     * ```
     */
    public function grabMessengerTransport(string $transportName): InMemoryTransport
    {
        $this->assertTrue(
            class_exists(InMemoryTransport::class),
            'The Messenger transport inspection methods require symfony/messenger >= 6.3.'
        );

        $transport = $this->grabService(sprintf('messenger.transport.%s', $transportName));

        if (!$transport instanceof InMemoryTransport) {
            Assert::fail(sprintf(
                "The 'messenger.transport.%s' transport is not a Symfony in-memory transport. "
                . "Route the bus to 'in-memory://' in your test environment to use this method.",
                $transportName,
            ));
        }

        return $transport;
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
     * Asserts how many messages are still waiting on an in-memory transport
     * (sent but not yet acknowledged or rejected).
     *
     * ```php
     * <?php
     * $I->seeMessengerQueueCount(1, 'async');
     * ```
     */
    public function seeMessengerQueueCount(int $expectedCount, string $transportName): void
    {
        $queued = $this->getQueuedEnvelopes($transportName);

        $this->assertCount(
            $expectedCount,
            $queued,
            sprintf(
                "Expected %d message(s) queued on the '%s' transport, but found %d.",
                $expectedCount,
                $transportName,
                count($queued),
            ),
        );
    }

    /**
     * Asserts that a message of the given class is waiting on an in-memory transport.
     *
     * ```php
     * <?php
     * $I->seeMessengerTransportContains(SendInvoice::class, 'async');
     * ```
     *
     * @param class-string $messageClass
     */
    public function seeMessengerTransportContains(string $messageClass, string $transportName): void
    {
        $found = false;
        foreach ($this->getQueuedEnvelopes($transportName) as $envelope) {
            if ($envelope->getMessage() instanceof $messageClass) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            sprintf("No '%s' message is queued on the '%s' transport.", $messageClass, $transportName),
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

    /**
     * Returns the envelopes still waiting on the transport, i.e. sent but not yet
     * acknowledged or rejected.
     *
     * @return list<Envelope>
     */
    private function getQueuedEnvelopes(string $transportName): array
    {
        $transport = $this->grabMessengerTransport($transportName);

        $processedIds = [];
        foreach ([...$transport->getAcknowledged(), ...$transport->getRejected()] as $envelope) {
            $id = $this->envelopeId($envelope);
            if ($id !== null) {
                $processedIds[$id] = true;
            }
        }

        $queued = [];
        foreach ($transport->getSent() as $envelope) {
            $id = $this->envelopeId($envelope);
            if ($id === null || !isset($processedIds[$id])) {
                $queued[] = $envelope;
            }
        }

        return $queued;
    }

    private function envelopeId(Envelope $envelope): ?string
    {
        $stamp = $envelope->last(TransportMessageIdStamp::class);
        if (!$stamp instanceof TransportMessageIdStamp) {
            return null;
        }

        $id = $stamp->getId();
        return is_scalar($id) ? (string) $id : null;
    }

    private function grabMessageBus(): MessageBusInterface
    {
        $bus = $this->grabService('messenger.routable_message_bus');
        if (!$bus instanceof MessageBusInterface) {
            Assert::fail("The 'messenger.routable_message_bus' service is not a message bus.");
        }

        return $bus;
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
