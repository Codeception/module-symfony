<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\MailerAssertionsTrait;
use Codeception\Module\Symfony\MimeAssertionsTrait;
use Symfony\Component\Mime\Email;
use Tests\Support\CodeceptTestCase;

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

    public function testAssertionsWorkWithProvidedEmail(): void
    {
        $email = (new Email())->from('custom@example.com')->to('custom@example.com')->text('Custom body text');

        $this->assertEmailAddressContains('To', 'custom@example.com', $email);
        $this->assertEmailTextBodyContains('Custom body text', $email);
        $this->assertEmailNotHasHeader('Cc', $email);
    }
}
