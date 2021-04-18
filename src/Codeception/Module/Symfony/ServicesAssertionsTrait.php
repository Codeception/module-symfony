<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Codeception\Lib\Connector\Symfony as SymfonyConnector;

trait ServicesAssertionsTrait
{
    /**
     * Grabs a service from the Symfony dependency injection container (DIC).
     * In "test" environment, Symfony uses a special `test.service_container`.
     * See the "[Accessing the Container](https://symfony.com/doc/current/testing.html#accessing-the-container)" documentation.
     * Services that aren't injected somewhere into your app, need to be defined as `public` to be accessible by Codeception.
     *
     * ```php
     * <?php
     * $em = $I->grabService('doctrine');
     * ```
     *
     * @part services
     * @param string $serviceId
     * @return object
     */
    public function grabService(string $serviceId): object
    {
        if (!$service = $this->getService($serviceId)) {
            $this->fail("Service {$serviceId} is not available in container.
            If the service isn't injected anywhere in your app, you need to set it to `public` in your `config/services_test.php`/`.yaml`,
            see https://symfony.com/doc/current/testing.html#accessing-the-container");
        }
        return $service;
    }

    /**
     * Get service $serviceName and add it to the lists of persistent services.
     *
     * @part services
     * @param string $serviceName
     * @return self
     */
    public function persistService(string $serviceName)
    {
        $service = $this->grabService($serviceName);
        $this->persistentServices[$serviceName] = $service;
        if ($this->client instanceof SymfonyConnector) {
            $this->client->persistentServices[$serviceName] = $service;
        }

        return $this;
    }

    /**
     * Get service $serviceName and add it to the lists of persistent services,
     * making that service persistent between tests.
     *
     * @part services
     * @param string $serviceName
     * @return self
     */
    public function persistPermanentService(string $serviceName)
    {
        $service = $this->grabService($serviceName);
        $this->persistentServices[$serviceName] = $service;
        $this->permanentServices[$serviceName] = $service;
        if ($this->client instanceof SymfonyConnector) {
            $this->client->persistentServices[$serviceName] = $service;
        }

        return $this;
    }

    /**
     * Remove service $serviceName from the lists of persistent services.
     *
     * @part services
     * @param string $serviceName
     * @return self
     */
    public function unpersistService(string $serviceName)
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

        return $this;
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