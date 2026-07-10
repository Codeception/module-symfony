<?php

declare(strict_types=1);

namespace Codeception\Lib\Connector;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelBrowser;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;

use function function_exists;

/**
 * @property KernelInterface $kernel
 */
class Symfony extends HttpKernelBrowser
{
    private ContainerInterface $container;
    private bool $hasPerformedRequest = false;

    public function __construct(
        HttpKernelInterface $kernel,
        /** @var array<non-empty-string, object> */
        public array $persistentServices = [],
        private bool $reboot = true
    ) {
        parent::__construct($kernel);
        $this->followRedirects();
        $this->container = $this->resolveContainer();
        $this->rebootKernel();
    }

    protected function doRequest(object $request): Response
    {
        if ($this->reboot) {
            $this->hasPerformedRequest ? $this->rebootKernel() : $this->hasPerformedRequest = true;
        }

        return parent::doRequest($request);
    }

    /**
     * Reboots the kernel.
     *
     * Services from the list of persistent services
     * are updated from service container before kernel shutdown
     * and injected into newly initialized container after kernel boot.
     */
    public function rebootKernel(): void
    {
        $this->updatePersistentServices();

        if ($this->kernel instanceof Kernel) {
            $this->ensureKernelShutdown();
            $this->kernel->boot();
        }

        $this->container = $this->resolveContainer();

        $this->injectPersistentServices();

        $this->getProfiler()?->enable();
    }

    protected function ensureKernelShutdown(): void
    {
        $this->kernel->boot();

        $kernel = $this->kernel;
        (function () use ($kernel): void {
            $parameters = property_exists($this, 'parameters') ? $this->parameters : null;
            if (!is_array($parameters) || !array_key_exists('doctrine.connections', $parameters)) {
                $kernel->shutdown();

                return;
            }

            $connections = $parameters['doctrine.connections'];
            unset($parameters['doctrine.connections']);
            $this->parameters = $parameters;

            try {
                $kernel->shutdown();
            } finally {
                $parameters = $this->parameters;
                $parameters['doctrine.connections'] = $connections;
                $this->parameters = $parameters;
            }
        })->call($kernel->getContainer());
    }

    private function resolveContainer(): ContainerInterface
    {
        $container = $this->kernel->getContainer();

        /** @var ContainerInterface $testContainer */
        $testContainer = $container->has('test.service_container') ? $container->get('test.service_container') : $container;

        return $testContainer;
    }

    private function getProfiler(): ?Profiler
    {
        foreach (['.container.private.profiler', 'profiler'] as $id) {
            if ($this->container->has($id)) {
                $profiler = $this->container->get($id);

                return $profiler instanceof Profiler ? $profiler : null;
            }
        }

        return null;
    }

    private function updatePersistentServices(): void
    {
        foreach ($this->persistentServices as $name => $_) {
            if ($this->container->has($name)) {
                $this->persistentServices[$name] = $this->container->get($name);
            }
        }
    }

    private function injectPersistentServices(): void
    {
        foreach ($this->persistentServices as $name => $service) {
            try {
                $this->container->set($name, $service);
            } catch (InvalidArgumentException $e) {
                if (function_exists('codecept_debug')) {
                    codecept_debug("[Symfony] Can't set persistent service {$name}: {$e->getMessage()}");
                }
            }
        }
    }
}
