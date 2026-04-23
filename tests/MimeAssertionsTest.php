<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\MailerAssertionsTrait;
use Codeception\Module\Symfony\MimeAssertionsTrait;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Test\Constraint\EmailAddressContains;
use Symfony\Component\Mime\Test\Constraint\EmailSubjectContains;
use Tests\Support\CodeceptTestCase;
use function class_exists;

final class MimeAssertionsTest extends CodeceptTestCase
{
    use MailerAssertionsTrait;
    use MimeAssertionsTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getService('mailer.message_logger_listener')->reset();
        $this->client->request('GET', '/send-email');
    }

    public function testAssertEmailAddressContains(): void
    {
        $this->assertEmailAddressContains('To', 'jane_doe@example.com');
    }

    public function testAssertEmailAddressNotContains(): void
    {
        if (!class_exists(EmailAddressContains::class)) {
            $this->markTestSkipped('assertEmailAddressNotContains requires EmailAddressContains support in symfony/mime.');
        }

        $this->assertEmailAddressNotContains('To', 'john_doe@example.com');
    }

    public function testAssertEmailAttachmentCount(): void
    {
        $this->assertEmailAttachmentCount(1);
    }

    public function testAssertEmailHasHeader(): void
    {
        $this->assertEmailHasHeader('To');
    }

    public function testAssertEmailHeaderSame(): void
    {
        $this->assertEmailHeaderSame('To', 'jane_doe@example.com');
    }

    public function testAssertEmailHeaderNotSame(): void
    {
        $this->assertEmailHeaderNotSame('To', 'john_doe@example.com');
    }

    public function testAssertEmailHtmlBodyContains(): void
    {
        $this->assertEmailHtmlBodyContains('Example Email');
    }

    public function testAssertEmailHtmlBodyNotContains(): void
    {
        $this->assertEmailHtmlBodyNotContains('userpassword');
    }

    public function testAssertEmailNotHasHeader(): void
    {
        $this->assertEmailNotHasHeader('Bcc');
    }

    public function testAssertEmailTextBodyContains(): void
    {
        $this->assertEmailTextBodyContains('Example text body');
    }

    public function testAssertEmailTextBodyNotContains(): void
    {
        $this->assertEmailTextBodyNotContains('My secret text body');
    }

    public function testAssertEmailSubjectContains(): void
    {
        if (!class_exists(EmailSubjectContains::class)) {
            $this->markTestSkipped('assertEmailSubjectContains requires EmailSubjectContains support in symfony/mime.');
        }

        $this->assertEmailSubjectContains('Account created successfully');
    }

    public function testAssertEmailSubjectNotContains(): void
    {
        if (!class_exists(EmailSubjectContains::class)) {
            $this->markTestSkipped('assertEmailSubjectNotContains requires EmailSubjectContains support in symfony/mime.');
        }

        $this->assertEmailSubjectNotContains('Password reset');
    }

    public function testAssertionsWorkWithProvidedEmail(): void
    {
        if (!class_exists(EmailAddressContains::class)) {
            $this->markTestSkipped('assertEmailAddressNotContains requires EmailAddressContains support in symfony/mime.');
        }

        if (!class_exists(EmailSubjectContains::class)) {
            $this->markTestSkipped('assertEmailSubjectContains/assertEmailSubjectNotContains require EmailSubjectContains support in symfony/mime.');
        }

        $email = (new Email())
            ->from('custom@example.com')
            ->to('custom@example.com')
            ->subject('Custom subject')
            ->text('Custom body text');

        $this->assertEmailAddressContains('To', 'custom@example.com', $email);
        $this->assertEmailAddressNotContains('To', 'other@example.com', $email);
        $this->assertEmailTextBodyContains('Custom body text', $email);
        $this->assertEmailSubjectContains('Custom subject', $email);
        $this->assertEmailSubjectNotContains('Other subject', $email);
        $this->assertEmailNotHasHeader('Cc', $email);
    }
}
