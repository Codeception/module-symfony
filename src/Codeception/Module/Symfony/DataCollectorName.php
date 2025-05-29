<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

/**
 * @internal
 */
enum DataCollectorName: string
{
    case EVENTS = 'events';
    case FORM = 'form';
    case HTTP_CLIENT = 'http_client';
    case LOGGER = 'logger';
    case TIME = 'time';
    case TRANSLATION = 'translation';
    case TWIG = 'twig';
    case SECURITY = 'security';
    case MAILER = 'mailer';
}
