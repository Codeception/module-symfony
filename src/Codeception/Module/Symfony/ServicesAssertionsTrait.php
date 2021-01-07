<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Codeception\Lib\Connector\Symfony as SymfonyConnector;

trait ServicesAssertionsTrait
{
    /**
     * Grabs a service from the Symfony dependency injection container (DIC).
     * In "test" environment, Symfony uses a special `test.service_container`, see https://symfony.com/doc/current/testing.html#accessing-the-container
     * Services that aren't injected somewhere into your app, need to be defined as `public` to be accessible by Codeception.
     *
     * ``` php
     * <?php
     * $em = $I->grabService('doctrine');
     * ```
     *
     * @param string $service
     * @return mixed
     * @part services
     */
    public function grabService(string $service)
    {
        $container = $this->_getContainer();
        if (!$container->has($service)) {
            $this->fail("Service $service is not available in container.
            If the service isn't injected anywhere in your app, you need to set it to `public` in your `config/services_test.php`/`.yaml`,
            see https://symfony.com/doc/current/testing.html#accessing-the-container");
        }
        return $container->get($service);
    }

    /**
     * Get service $serviceName and add it to the lists of persistent services.
     *
     * @param string $serviceName
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
     * @param string $serviceName
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
     * @param string $serviceName
     */
    public function unpersistService(string $serviceName): void
    {
        if (isset($this->persistentServices[$serviceName])) {
            unset($this->persistentServices[$serviceName]);
        }
        if (isset($this->permanentServices[$serviceName])) {
            unset($this->permanentServices[$serviceName]);
        }
        if ($this->client instanceof SymfonyConnector && isset($this->client->persistentServices[$serviceName])) {
            unset($this->client->persistentServices[$serviceName]);
        }
    }
}