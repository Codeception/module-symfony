<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;
use function count;
use function sprintf;

trait MailerAssertionsTrait
{
    /**
     * Checks that no email was sent. This is an alias for seeEmailIsSent(0).
     *
     * @part email
     */
    public function dontSeeEmailIsSent(): void
    {
        $this->seeEmailIsSent(0);
    }

    /**
     * Checks if the desired number of emails was sent.
     * If no argument is provided then at least one email must be sent to satisfy the check.
     * The email is checked using Symfony's profiler, which means:
     * * If your app performs a redirect after sending the email, you need to suppress this using REST Module's [stopFollowingRedirects](https://codeception.com/docs/modules/REST#stopFollowingRedirects)
     * * If the email is sent by a Symfony Console Command, Codeception cannot detect it yet.
     *
     * ``` php
     * <?php
     * $I->seeEmailIsSent(2);
     * ```
     *
     * @param int|null $expectedCount
     */
    public function seeEmailIsSent(?int $expectedCount = null): void
    {
        $realCount = 0;
        $mailer = $this->config['mailer'];
        if ($mailer === self::SWIFTMAILER) {
            $mailCollector = $this->grabCollector('swiftmailer', __FUNCTION__);
            $realCount = $mailCollector->getMessageCount();
        } elseif ($mailer === self::SYMFONY_MAILER) {
            $mailCollector = $this->grabCollector('mailer', __FUNCTION__);
            $realCount = count($mailCollector->getEvents()->getMessages());
        } else {
            $this->fail(
                "Emails can't be tested without Mailer service connector.
                Set your mailer service in `functional.suite.yml`: `mailer: swiftmailer`
                (Or `mailer: symfony_mailer` for Symfony Mailer)."
            );
        }

        if ($expectedCount !== null) {
            $this->assertEquals($expectedCount, $realCount, sprintf(
                'Expected number of sent emails was %d, but in reality %d %s sent.',
                $expectedCount, $realCount, $realCount === 1 ? 'was' : 'were'
            ));
            return;
        }
        $this->assertGreaterThan(0, $realCount);
    }
}