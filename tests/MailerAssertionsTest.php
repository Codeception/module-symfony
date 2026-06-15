<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\MailerAssertionsTrait;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
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

    public function testAssertEmailCountWithMessage(): void
    {
        $this->client->request('GET', '/send-message');
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

    public function testGrabLastSentEmailReturnsEmailInstance(): void
    {
        $this->client->request('GET', '/send-email');
        $email = $this->grabLastSentEmail();
        $this->assertInstanceOf(Email::class, $email);
    }

    public function testGrabLastSentEmailReturnsMessageInstance(): void
    {
        $this->client->request('GET', '/send-message');
        $message = $this->grabLastSentEmail();
        $this->assertInstanceOf(Message::class, $message);
        $this->assertNotInstanceOf(Email::class, $message);
    }

    public function testGrabLastSentEmailReturnsNullWhenNoMessagesSent(): void
    {
        $this->assertNull($this->grabLastSentEmail());
    }

    public function testGrabSentEmailsWithEmailType(): void
    {
        $this->client->request('GET', '/send-email');
        $emails = $this->grabSentEmails();
        $this->assertCount(1, $emails);
        $this->assertInstanceOf(Email::class, $emails[0]);
    }

    public function testGrabSentEmailsWithMessageType(): void
    {
        $this->client->request('GET', '/send-message');
        $messages = $this->grabSentEmails();
        $this->assertCount(1, $messages);
        $this->assertInstanceOf(Message::class, $messages[0]);
    }

    public function testSeeEmailIsSent(): void
    {
        $this->client->request('GET', '/send-email');
        $this->seeEmailIsSent();
    }

    public function testEdgeCases(): void
    {
        $this->assertNull($this->grabLastSentEmail());

        $this->client->request('GET', '/send-email');
        $this->assertNull($this->getMailerEvent(999));
    }

    private function createQueuedEvent(): MessageEvent
    {
        return new MessageEvent((new Email())->from('queued@example.com')->to('queued@example.com'), new Envelope(new Address('queued@example.com'), [new Address('queued@example.com')]), 'smtp', true);
    }
}
