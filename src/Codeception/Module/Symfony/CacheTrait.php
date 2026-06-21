<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Profiler\Profile;

use function array_key_exists;
use function array_unique;
use function array_values;
use function is_string;

trait CacheTrait
{
    private ?object $cachedResponse = null;
    private ?Profile $cachedProfile = null;
    /** @var array<string, string>|null */
    protected ?array $cachedRoutes = null;

    /** @var array<string, mixed> */
    protected array $state = [];

    public function _getContainer(): ContainerInterface
    {
        $container = $this->kernel->getContainer();

        /** @var ContainerInterface $testContainer */
        $testContainer = $container->has('test.service_container') ? $container->get('test.service_container') : $container;

        return $testContainer;
    }

    /** @return list<non-empty-string> */
    protected function getInternalDomains(): array
    {
        if (isset($this->state['internalDomains'])) {
            /** @var list<non-empty-string> */
            return $this->state['internalDomains'];
        }

        $domains = [];
        foreach ($this->grabRouterService()->getRouteCollection() as $route) {
            if ($route->getHost() === '') {
                continue;
            }

            $hostRegex = $route->compile()->getHostRegex();
            if ($hostRegex !== null && $hostRegex !== '') {
                $domains[] = $hostRegex;
            }
        }

        /** @var list<non-empty-string> $domains */
        $domains = array_values(array_unique($domains));
        return $this->state['internalDomains'] = $domains;
    }

    protected function clearRouterCache(): void
    {
        unset($this->state['internalDomains']);
        $this->cachedRoutes = null;
    }

    /**
     * @template T of object
     * @param class-string<T> $expectedClass
     * @param string[] $serviceIds
     * @return T|null
     */
    protected function grabCachedService(string $expectedClass, array $serviceIds): ?object
    {
        if (!array_key_exists($expectedClass, $this->state)) {
            $this->state[$expectedClass] = null;
            foreach ($serviceIds as $id) {
                $service = $this->getService($id);
                if ($service instanceof $expectedClass) {
                    $this->state[$expectedClass] = $id;
                    break;
                }
            }
        }

        $serviceId = $this->state[$expectedClass];
        if (!is_string($serviceId)) {
            return null;
        }

        $service = $this->getService($serviceId);
        return $service instanceof $expectedClass ? $service : null;
    }
}
