<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Constraint\LogicalNot;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Test\Constraint as MimeConstraint;

trait MimeAssertionsTrait
{
    public function assertEmailAddressContains(string $headerName, string $expectedValue, Email $email = null): void
    {
        $email = $email ?: $this->grabLastSentEmail();
        $this->assertThat($email, new MimeConstraint\EmailAddressContains($headerName, $expectedValue));
    }

    public function assertEmailAttachmentCount(int $count, Email $email = null): void
    {
        $email = $email ?: $this->grabLastSentEmail();
        $this->assertThat($email, new MimeConstraint\EmailAttachmentCount($count));
    }

    public function assertEmailHasHeader(string $headerName, Email $email = null): void
    {
        $email = $email ?: $this->grabLastSentEmail();
        $this->assertThat($email, new MimeConstraint\EmailHasHeader($headerName));
    }

    public function assertEmailHeaderNotSame(string $headerName, string $expectedValue, Email $email = null): void
    {
        $email = $email ?: $this->grabLastSentEmail();
        $this->assertThat($email, new LogicalNot(new MimeConstraint\EmailHeaderSame($headerName, $expectedValue)));
    }

    public function assertEmailHeaderSame(string $headerName, string $expectedValue, Email $email = null): void
    {
        $email = $email ?: $this->grabLastSentEmail();
        $this->assertThat($email, new MimeConstraint\EmailHeaderSame($headerName, $expectedValue));
    }

    public function assertEmailHtmlBodyContains(string $text, Email $email = null): void
    {
        $email = $email ?: $this->grabLastSentEmail();
        $this->assertThat($email, new MimeConstraint\EmailHtmlBodyContains($text));
    }

    public function assertEmailHtmlBodyNotContains(string $text, Email $email = null): void
    {
        $email = $email ?: $this->grabLastSentEmail();
        $this->assertThat($email, new LogicalNot(new MimeConstraint\EmailHtmlBodyContains($text)));
    }

    public function assertEmailNotHasHeader(string $headerName, Email $email = null): void
    {
        $email = $email ?: $this->grabLastSentEmail();
        $this->assertThat($email, new LogicalNot(new MimeConstraint\EmailHasHeader($headerName)));
    }

    public function assertEmailTextBodyContains(string $text, Email $email = null): void
    {
        $email = $email ?: $this->grabLastSentEmail();
        $this->assertThat($email, new MimeConstraint\EmailTextBodyContains($text));
    }

    public function assertEmailTextBodyNotContains(string $text, Email $email = null): void
    {
        $email = $email ?: $this->grabLastSentEmail();
        $this->assertThat($email, new LogicalNot(new MimeConstraint\EmailTextBodyContains($text)));
    }
}