<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Profiler\Profile;

use function array_unique;
use function array_values;

trait CacheTrait
{
    private ?object $cachedResponse = null;
    private ?Profile $cachedProfile = null;

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
            /** @var list<non-empty-string> $domains */
            $domains = $this->state['internalDomains'];

            return $domains;
        }

        $domains = [];
        foreach ($this->grabRouterService()->getRouteCollection() as $route) {
            if ($route->getHost() !== '') {
                $regex = $route->compile()->getHostRegex();
                if ($regex !== null && $regex !== '') {
                    $domains[] = $regex;
                }
            }
        }

        return $this->state['internalDomains'] = array_values(array_unique($domains));
    }

    protected function clearInternalDomainsCache(): void
    {
        unset($this->state['internalDomains']);
    }

    /**
     * @template T of object
     * @param class-string<T> $expectedClass
     * @param string[] $serviceIds
     * @return T|null
     */
    protected function grabCachedService(string $expectedClass, array $serviceIds): ?object
    {
        /** @var ?string $serviceId */
        $serviceId = $this->state[$expectedClass] ??= (function () use ($serviceIds, $expectedClass): ?string {
            foreach ($serviceIds as $id) {
                if ($this->getService($id) instanceof $expectedClass) {
                    return $id;
                }
            }

            return null;
        })();

        if ($serviceId === null) {
            return null;
        }

        $service = $this->getService($serviceId);

        return $service instanceof $expectedClass ? $service : null;
    }
}
