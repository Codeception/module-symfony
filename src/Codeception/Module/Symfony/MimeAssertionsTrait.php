<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Constraint\LogicalNot;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Test\Constraint as MimeConstraint;

trait MimeAssertionsTrait
{
    /**
     * Verify that an email contains addresses with a [header](https://datatracker.ietf.org/doc/html/rfc4021)
     * `$headerName` and its expected value `$expectedValue`.
     * If the Email object is not specified, the last email sent is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailAddressContains('To', 'jane_doe@example.com');
     * ```
     */
    public function assertEmailAddressContains(string $headerName, string $expectedValue, Email $email = null): void
    {
        $email = $this->verifyEmailObject($email, __FUNCTION__);
        $this->assertThat($email, new MimeConstraint\EmailAddressContains($headerName, $expectedValue));
    }

    /**
     * Verify that an email has sent the specified number `$count` of attachments.
     * If the Email object is not specified, the last email sent is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailAttachmentCount(1);
     * ```
     */
    public function assertEmailAttachmentCount(int $count, Email $email = null): void
    {
        $email = $this->verifyEmailObject($email, __FUNCTION__);
        $this->assertThat($email, new MimeConstraint\EmailAttachmentCount($count));
    }

    /**
     * Verify that an email has a [header](https://datatracker.ietf.org/doc/html/rfc4021) `$headerName`.
     * If the Email object is not specified, the last email sent is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailHasHeader('Bcc');
     * ```
     */
    public function assertEmailHasHeader(string $headerName, Email $email = null): void
    {
        $email = $this->verifyEmailObject($email, __FUNCTION__);
        $this->assertThat($email, new MimeConstraint\EmailHasHeader($headerName));
    }

    /**
     * Verify that the [header](https://datatracker.ietf.org/doc/html/rfc4021)
     * `$headerName` of an email is not the expected one `$expectedValue`.
     * If the Email object is not specified, the last email sent is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailHeaderNotSame('To', 'john_doe@gmail.com');
     * ```
     */
    public function assertEmailHeaderNotSame(string $headerName, string $expectedValue, Email $email = null): void
    {
        $email = $this->verifyEmailObject($email, __FUNCTION__);
        $this->assertThat($email, new LogicalNot(new MimeConstraint\EmailHeaderSame($headerName, $expectedValue)));
    }

    /**
     * Verify that the [header](https://datatracker.ietf.org/doc/html/rfc4021)
     * `$headerName` of an email is the same as expected `$expectedValue`.
     * If the Email object is not specified, the last email sent is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailHeaderSame('To', 'jane_doe@gmail.com');
     * ```
     */
    public function assertEmailHeaderSame(string $headerName, string $expectedValue, Email $email = null): void
    {
        $email = $this->verifyEmailObject($email, __FUNCTION__);
        $this->assertThat($email, new MimeConstraint\EmailHeaderSame($headerName, $expectedValue));
    }

    /**
     * Verify that the HTML body of an email contains `$text`.
     * If the Email object is not specified, the last email sent is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailHtmlBodyContains('Successful registration');
     * ```
     */
    public function assertEmailHtmlBodyContains(string $text, Email $email = null): void
    {
        $email = $this->verifyEmailObject($email, __FUNCTION__);
        $this->assertThat($email, new MimeConstraint\EmailHtmlBodyContains($text));
    }

    /**
     * Verify that the HTML body of an email does not contain a text `$text`.
     * If the Email object is not specified, the last email sent is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailHtmlBodyNotContains('userpassword');
     * ```
     */
    public function assertEmailHtmlBodyNotContains(string $text, Email $email = null): void
    {
        $email = $this->verifyEmailObject($email, __FUNCTION__);
        $this->assertThat($email, new LogicalNot(new MimeConstraint\EmailHtmlBodyContains($text)));
    }

    /**
     * Verify that an email does not have a [header](https://datatracker.ietf.org/doc/html/rfc4021) `$headerName`.
     * If the Email object is not specified, the last email sent is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailNotHasHeader('Bcc');
     * ```
     */
    public function assertEmailNotHasHeader(string $headerName, Email $email = null): void
    {
        $email = $this->verifyEmailObject($email, __FUNCTION__);
        $this->assertThat($email, new LogicalNot(new MimeConstraint\EmailHasHeader($headerName)));
    }

    /**
     * Verify the text body of an email contains a `$text`.
     * If the Email object is not specified, the last email sent is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailTextBodyContains('Example text body');
     * ```
     */
    public function assertEmailTextBodyContains(string $text, Email $email = null): void
    {
        $email = $this->verifyEmailObject($email, __FUNCTION__);
        $this->assertThat($email, new MimeConstraint\EmailTextBodyContains($text));
    }

    /**
     * Verify that the text body of an email does not contain a `$text`.
     * If the Email object is not specified, the last email sent is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailTextBodyNotContains('My secret text body');
     * ```
     */
    public function assertEmailTextBodyNotContains(string $text, Email $email = null): void
    {
        $email = $this->verifyEmailObject($email, __FUNCTION__);
        $this->assertThat($email, new LogicalNot(new MimeConstraint\EmailTextBodyContains($text)));
    }

    /**
     * Returns the last email sent if $email is null. If no email has been sent it fails.
     */
    private function verifyEmailObject(?Email $email, string $function): Email
    {
        $email = $email ?: $this->grabLastSentEmail();
        $errorMsgFormat = "There is no email to verify. An Email object was not specified when invoking '%s' and the application has not sent one.";
        return $email ?: $this->fail(
            sprintf($errorMsgFormat, $function)
        );
    }
}