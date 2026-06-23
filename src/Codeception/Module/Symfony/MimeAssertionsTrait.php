<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\LogicalNot;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Mime\Test\Constraint as MimeConstraint;

use function class_exists;
use function sprintf;

trait MimeAssertionsTrait
{
    /**
     * Verify that a message contains addresses with a [header](https://datatracker.ietf.org/doc/html/rfc4021)
     * `$headerName` and its expected value `$expectedValue`.
     * If no Message is specified, the last sent message is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailAddressContains('To', 'jane_doe@example.com');
     * ```
     */
    public function assertEmailAddressContains(string $headerName, string $expectedValue, ?Message $email = null): void
    {
        $this->assertThat($this->getMessageOrFail($email, __FUNCTION__), new MimeConstraint\EmailAddressContains($headerName, $expectedValue));
    }

    /**
     * Verify that an email does not contain the given address in the specified header.
     * If no Message is specified, the last sent message is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailAddressNotContains('To', 'john_doe@example.com');
     * ```
     */
    public function assertEmailAddressNotContains(string $headerName, string $expectedValue, ?Message $email = null): void
    {
        $this->assertThat($this->getMessageOrFail($email, __FUNCTION__), new LogicalNot(new MimeConstraint\EmailAddressContains($headerName, $expectedValue)));
    }

    /**
     * Verify that an email has the specified number `$count` of attachments.
     * If no Email is specified, the last sent email is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailAttachmentCount(1);
     * ```
     */
    public function assertEmailAttachmentCount(int $count, ?Email $email = null): void
    {
        $this->assertThat($this->getMessageOrFail($email, __FUNCTION__), new MimeConstraint\EmailAttachmentCount($count));
    }

    /**
     * Verify that a message has a [header](https://datatracker.ietf.org/doc/html/rfc4021) `$headerName`.
     * If no Message is specified, the last sent message is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailHasHeader('Bcc');
     * ```
     */
    public function assertEmailHasHeader(string $headerName, ?Message $email = null): void
    {
        $this->assertThat($this->getMessageOrFail($email, __FUNCTION__), new MimeConstraint\EmailHasHeader($headerName));
    }

    /**
     * Verify that the [header](https://datatracker.ietf.org/doc/html/rfc4021)
     * `$headerName` of a message is not the expected one `$expectedValue`.
     * If no Message is specified, the last sent message is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailHeaderNotSame('To', 'john_doe@gmail.com');
     * ```
     */
    public function assertEmailHeaderNotSame(string $headerName, string $expectedValue, ?Message $email = null): void
    {
        $this->assertThat($this->getMessageOrFail($email, __FUNCTION__), new LogicalNot(new MimeConstraint\EmailHeaderSame($headerName, $expectedValue)));
    }

    /**
     * Verify that the [header](https://datatracker.ietf.org/doc/html/rfc4021)
     * `$headerName` of a message is the same as expected `$expectedValue`.
     * If no Message is specified, the last sent message is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailHeaderSame('To', 'jane_doe@gmail.com');
     * ```
     */
    public function assertEmailHeaderSame(string $headerName, string $expectedValue, ?Message $email = null): void
    {
        $this->assertThat($this->getMessageOrFail($email, __FUNCTION__), new MimeConstraint\EmailHeaderSame($headerName, $expectedValue));
    }

    /**
     * Verify that the HTML body of an email contains `$text`.
     * If no Email is specified, the last sent email is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailHtmlBodyContains('Successful registration');
     * ```
     */
    public function assertEmailHtmlBodyContains(string $text, ?Email $email = null): void
    {
        $this->assertThat($this->getMessageOrFail($email, __FUNCTION__), new MimeConstraint\EmailHtmlBodyContains($text));
    }

    /**
     * Verify that the HTML body of an email does not contain a text `$text`.
     * If no Email is specified, the last sent email is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailHtmlBodyNotContains('userpassword');
     * ```
     */
    public function assertEmailHtmlBodyNotContains(string $text, ?Email $email = null): void
    {
        $this->assertThat($this->getMessageOrFail($email, __FUNCTION__), new LogicalNot(new MimeConstraint\EmailHtmlBodyContains($text)));
    }

    /**
     * Verify that a message does not have a [header](https://datatracker.ietf.org/doc/html/rfc4021) `$headerName`.
     * If no Message is specified, the last sent message is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailNotHasHeader('Bcc');
     * ```
     */
    public function assertEmailNotHasHeader(string $headerName, ?Message $email = null): void
    {
        $this->assertThat($this->getMessageOrFail($email, __FUNCTION__), new LogicalNot(new MimeConstraint\EmailHasHeader($headerName)));
    }

    /**
     * Verify the text body of an email contains a `$text`.
     * If no Email is specified, the last sent email is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailTextBodyContains('Example text body');
     * ```
     */
    public function assertEmailTextBodyContains(string $text, ?Email $email = null): void
    {
        $this->assertThat($this->getMessageOrFail($email, __FUNCTION__), new MimeConstraint\EmailTextBodyContains($text));
    }

    /**
     * Verify that the text body of an email does not contain a `$text`.
     * If no Email is specified, the last sent email is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailTextBodyNotContains('My secret text body');
     * ```
     */
    public function assertEmailTextBodyNotContains(string $text, ?Email $email = null): void
    {
        $this->assertThat($this->getMessageOrFail($email, __FUNCTION__), new LogicalNot(new MimeConstraint\EmailTextBodyContains($text)));
    }

    /**
     * Verify that an email subject contains `$expectedValue`.
     * If no Email is specified, the last sent email is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailSubjectContains('Account created successfully');
     * ```
     */
    public function assertEmailSubjectContains(string $expectedValue, ?Email $email = null): void
    {
        $this->assertMimeConstraintAvailable(MimeConstraint\EmailSubjectContains::class, __FUNCTION__);
        $this->assertThat($this->getMessageOrFail($email, __FUNCTION__), new MimeConstraint\EmailSubjectContains($expectedValue));
    }

    /**
     * Verify that an email subject does not contain `$expectedValue`.
     * If no Email is specified, the last sent email is used instead.
     *
     * ```php
     * <?php
     * $I->assertEmailSubjectNotContains('Password reset');
     * ```
     */
    public function assertEmailSubjectNotContains(string $expectedValue, ?Email $email = null): void
    {
        $this->assertMimeConstraintAvailable(MimeConstraint\EmailSubjectContains::class, __FUNCTION__);
        $this->assertThat($this->getMessageOrFail($email, __FUNCTION__), new LogicalNot(new MimeConstraint\EmailSubjectContains($expectedValue)));
    }

    /**
     * Resolves a Message for assertion or fails the test.
     *
     * Uses the provided `$message` or retrieves the last sent message.
     * Fails if no message is found, or if it is a plain RawMessage lacking the headers and structure required by Mime constraints.
     */
    private function getMessageOrFail(?Message $message, string $caller): Message
    {
        $message ??= $this->grabLastSentRawMessage();

        if (!$message instanceof Message) {
            Assert::fail(sprintf("No message to verify for '%s'. None was provided or sent by the application.", $caller));
        }

        return $message;
    }

    private function assertMimeConstraintAvailable(string $constraintClass, string $function): void
    {
        $this->assertTrue(
            class_exists($constraintClass),
            sprintf(
                "The '%s' assertion is not available with your installed symfony/mime version. Missing constraint: %s",
                $function,
                $constraintClass
            )
        );
    }
}
