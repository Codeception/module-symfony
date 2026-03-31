<?php

declare(strict_types=1);

namespace Tests\App\Mailer;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class RegistrationMailer
{
    public function __construct(private MailerInterface $mailer) {}

    public function sendConfirmationEmail(string $recipient): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address('jeison_doe@gmail.com', 'No Reply'))
            ->to(new Address($recipient))
            ->subject('Account created successfully')
            ->attach('Example attachment')
            ->text('Example text body')
            ->htmlTemplate('emails/registration.html.twig');

        $this->mailer->send($email);
    }
}
