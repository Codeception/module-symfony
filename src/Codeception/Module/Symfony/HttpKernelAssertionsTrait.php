<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Assert;
use Symfony\Bridge\Twig\DataCollector\TwigDataCollector;
use Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector;
use Symfony\Component\Form\Extension\DataCollector\FormDataCollector;
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\EventDataCollector;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\Mailer\DataCollector\MessageDataCollector;
use Symfony\Component\Notifier\DataCollector\NotificationDataCollector;
use Symfony\Component\Translation\DataCollector\TranslationDataCollector;

use function sprintf;

/**
 * @internal
 */
trait HttpKernelAssertionsTrait
{
    abstract protected function getProfile(): ?Profile;

    /**
     * Grab a Symfony Data Collector from the current profile.
     *
     * @phpstan-return (
     *     $name is DataCollectorName::EVENTS ? EventDataCollector :
     *     ($name is DataCollectorName::FORM ? FormDataCollector :
     *     ($name is DataCollectorName::HTTP_CLIENT ? HttpClientDataCollector :
     *     ($name is DataCollectorName::LOGGER ? LoggerDataCollector :
     *     ($name is DataCollectorName::TIME ? TimeDataCollector :
     *     ($name is DataCollectorName::TRANSLATION ? TranslationDataCollector :
     *     ($name is DataCollectorName::TWIG ? TwigDataCollector :
     *     ($name is DataCollectorName::SECURITY ? SecurityDataCollector :
     *     ($name is DataCollectorName::MAILER ? MessageDataCollector :
     *     ($name is DataCollectorName::NOTIFIER ? NotificationDataCollector :
     *      DataCollectorInterface
     *     )))))))))
     * )
     */
    protected function grabCollector(DataCollectorName $name, string $function = '', ?string $message = null): DataCollectorInterface
    {
        $profile = $this->getProfile();

        if ($profile === null) {
            Assert::fail(sprintf("The Profile is needed to use the '%s' function.", $function));
        }

        if (!$profile->hasCollector($name->value)) {
            Assert::fail($message ?: sprintf("The '%s' collector is needed to use the '%s' function.", $name->value, $function));
        }

        return $profile->getCollector($name->value);
    }
}
