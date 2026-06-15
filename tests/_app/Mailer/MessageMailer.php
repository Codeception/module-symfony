<?php

declare(strict_types=1);

namespace Tests\App\Mailer;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\TextPart;

/**
 * Sends a plain Message (not an Email).
 *
 * This simulates scenarios where the mailer sends a Message object
 * instead of an Email, such as when using S/MIME encryption.
 */
final readonly class MessageMailer
{
    public function __construct(private MailerInterface $mailer) {}

    public function send(string $recipient): void
    {
        $headers = new Headers();
        $headers->addMailboxListHeader('From', ['no-reply@example.com']);
        $headers->addMailboxListHeader('To', [$recipient]);
        $headers->addTextHeader('Subject', 'Test message');

        $this->mailer->send(new Message($headers, new TextPart('Message body content')));
    }
}
