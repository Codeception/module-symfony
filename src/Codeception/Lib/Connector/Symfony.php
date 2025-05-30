<?php

declare(strict_types=1);

namespace Codeception\Lib\Connector;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\TestContainer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelBrowser;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use function array_keys;
use function codecept_debug;

class Symfony extends HttpKernelBrowser
{
    private bool $hasPerformedRequest = false;
    private ?ContainerInterface $container;

    public function __construct(
        Kernel $kernel,
        public array $persistentServices = [],
        private readonly bool $rebootable = true
    ) {
        parent::__construct($kernel);
        $this->followRedirects();
        $this->container = $this->getContainer();
        $this->rebootKernel();
    }

    /** @param Request $request */
    protected function doRequest(object $request): Response
    {
        if ($this->rebootable) {
            if ($this->hasPerformedRequest) {
                $this->rebootKernel();
            } else {
                $this->hasPerformedRequest = true;
            }
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
        if ($this->container) {
            foreach (array_keys($this->persistentServices) as $serviceName) {
                if ($service = $this->getService($serviceName)) {
                    $this->persistentServices[$serviceName] = $service;
                }
            }
        }

        $this->persistDoctrineConnections();
        $this->ensureKernelShutdown();
        $this->kernel->boot();
        $this->container = $this->getContainer();

        foreach ($this->persistentServices as $serviceName => $service) {
            try {
                $this->container->set($serviceName, $service);
            } catch (InvalidArgumentException $e) {
                codecept_debug("[Symfony] Can't set persistent service {$serviceName}: " . $e->getMessage());
            }
        }

        if ($profiler = $this->getProfiler()) {
            $profiler->enable();
        }
    }

    protected function ensureKernelShutdown(): void
    {
        $this->kernel->boot();
        $this->kernel->shutdown();
    }

    private function getContainer(): ?ContainerInterface
    {
        /** @var ContainerInterface $container */
        $container = $this->kernel->getContainer();
        return $container->has('test.service_container')
            ? $container->get('test.service_container')
            : $container;
    }

    private function getProfiler(): ?Profiler
    {
        return $this->container->has('profiler')
            ? $this->container->get('profiler')
            : null;
    }

    private function getService(string $serviceName): ?object
    {
        return $this->container->has($serviceName)
            ? $this->container->get($serviceName)
            : null;
    }

    private function persistDoctrineConnections(): void
    {
        if (!$this->container->hasParameter('doctrine.connections')) {
            return;
        }

        if ($this->container instanceof TestContainer) {
            $reflectedTestContainer = new ReflectionMethod($this->container, 'getPublicContainer');
            $reflectedTestContainer->setAccessible(true);
            $publicContainer = $reflectedTestContainer->invoke($this->container);
        } else {
            $publicContainer = $this->container;
        }

        $reflectedContainer = new ReflectionClass($publicContainer);
        $reflectionTarget = $reflectedContainer->hasProperty('parameters') ? $publicContainer : $publicContainer->getParameterBag();

        $reflectedParameters = new ReflectionProperty($reflectionTarget, 'parameters');
        $reflectedParameters->setAccessible(true);
        $parameters = $reflectedParameters->getValue($reflectionTarget);
        unset($parameters['doctrine.connections']);
        $reflectedParameters->setValue($reflectionTarget, $parameters);
    }
}
