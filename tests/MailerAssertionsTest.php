<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\MailerAssertionsTrait;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\Support\CodeceptTestCase;

final class MailerAssertionsTest extends CodeceptTestCase
{
    use MailerAssertionsTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getService('mailer.message_logger_listener')->reset();
    }

    public function testAssertEmailCount(): void
    {
        $this->client->request('GET', '/send-email');
        $this->assertEmailCount(1);
    }

    public function testAssertEmailIsNotQueued(): void
    {
        $this->client->request('GET', '/send-email');
        $this->assertEmailIsNotQueued($this->getMailerEvent());
    }

    public function testAssertEmailIsQueued(): void
    {
        $queuedEvent = $this->createQueuedEvent();
        $this->getService('mailer.message_logger_listener')->onMessage($queuedEvent);
        $this->assertEmailIsQueued($queuedEvent);
    }

    public function testAssertQueuedEmailCount(): void
    {
        $this->getService('mailer.message_logger_listener')->onMessage($this->createQueuedEvent());
        $this->assertQueuedEmailCount(1);
        $this->assertQueuedEmailCount(1, 'smtp');
    }

    public function testDontSeeEmailIsSent(): void
    {
        $this->dontSeeEmailIsSent();
    }

    public function testGetMailerEvent(): void
    {
        $this->client->request('GET', '/send-email');
        $this->assertInstanceOf(MessageEvent::class, $this->getMailerEvent());
    }

    public function testGetMailerEvents(): void
    {
        $this->client->request('GET', '/send-email');
        $this->assertCount(1, $this->getMailerEvents());
    }

    public function testGetMailerMessages(): void
    {
        $this->client->request('GET', '/send-email');
        $messages = $this->getMailerMessages();
        $this->assertCount(1, $messages);
        $this->assertInstanceOf(Email::class, $messages[0]);
    }

    public function testGetMailerMessage(): void
    {
        $this->client->request('GET', '/send-email');
        $this->assertInstanceOf(Email::class, $this->getMailerMessage());
    }

    public function testGrabLastSentEmail(): void
    {
        $this->client->request('GET', '/send-email');
        $email = $this->grabLastSentEmail();
        $this->assertInstanceOf(Email::class, $email);
        $this->assertSame('jane_doe@example.com', $email->getTo()[0]->getAddress());
    }

    public function testGrabSentEmails(): void
    {
        $this->client->request('GET', '/send-email');
        $this->assertCount(1, $this->grabSentEmails());
    }

    public function testSeeEmailIsSent(): void
    {
        $this->client->request('GET', '/send-email');
        $this->seeEmailIsSent();
    }

    public function testEdgeCases(): void
    {
        // No emails sent
        $this->assertNull($this->grabLastSentEmail());

        // Out of range index
        $this->client->request('GET', '/send-email');
        $this->assertNull($this->getMailerEvent(999));
        $this->assertNull($this->getMailerMessage(999));
    }

    private function createQueuedEvent(): MessageEvent
    {
        return new MessageEvent((new Email())->from('queued@example.com')->to('queued@example.com'), new Envelope(new Address('queued@example.com'), [new Address('queued@example.com')]), 'smtp', true);
    }
}
