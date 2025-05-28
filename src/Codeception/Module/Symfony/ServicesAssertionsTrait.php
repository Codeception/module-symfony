<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Codeception\Lib\Connector\Symfony as SymfonyConnector;
use PHPUnit\Framework\Assert;

trait ServicesAssertionsTrait
{
    /**
     * Grabs a service from the Symfony dependency injection container (DIC).
     * In the "test" environment, Symfony uses a special `test.service_container`.
     * See the "[Public Versus Private Services](https://symfony.com/doc/current/service_container/alias_private.html#marking-services-as-public-private)" documentation.
     * Services that aren't injected anywhere in your app, need to be defined as `public` to be accessible by Codeception.
     *
     * ```php
     * <?php
     * $em = $I->grabService('doctrine');
     * ```
     *
     * @part services
     */
    public function grabService(string $serviceId): object
    {
        if (!$service = $this->getService($serviceId)) {
            Assert::fail("Service `{$serviceId}` is required by Codeception, but not loaded by Symfony. Possible solutions:\n
            In your `config/packages/framework.php`/`.yaml`, set `test` to `true` (when in test environment), see https://symfony.com/doc/current/reference/configuration/framework.html#test\n
            If you're still getting this message, you're not using that service in your app, so Symfony isn't loading it at all.\n
            Solution: Set it to `public` in your `config/services.php`/`.yaml`, see https://symfony.com/doc/current/service_container/alias_private.html#marking-services-as-public-private\n");
        }

        return $service;
    }

    /**
     * Get service $serviceName and add it to the lists of persistent services.
     *
     * @part services
     */
    public function persistService(string $serviceName): void
    {
        $service = $this->grabService($serviceName);
        $this->persistentServices[$serviceName] = $service;
        if ($this->client instanceof SymfonyConnector) {
            $this->client->persistentServices[$serviceName] = $service;
        }
    }

    /**
     * Get service $serviceName and add it to the lists of persistent services,
     * making that service persistent between tests.
     *
     * @part services
     */
    public function persistPermanentService(string $serviceName): void
    {
        $service = $this->grabService($serviceName);
        $this->persistentServices[$serviceName] = $service;
        $this->permanentServices[$serviceName] = $service;
        if ($this->client instanceof SymfonyConnector) {
            $this->client->persistentServices[$serviceName] = $service;
        }
    }

    /**
     * Remove service $serviceName from the lists of persistent services.
     *
     * @part services
     */
    public function unpersistService(string $serviceName): void
    {
        unset($this->persistentServices[$serviceName]);
        unset($this->permanentServices[$serviceName]);

        if ($this->client instanceof SymfonyConnector) {
            unset($this->client->persistentServices[$serviceName]);
        }
    }

    protected function getService(string $serviceId): ?object
    {
        $container = $this->_getContainer();
        if ($container->has($serviceId)) {
            return $container->get($serviceId);
        }

        return null;
    }
}
