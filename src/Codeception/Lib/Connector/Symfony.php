<?php

declare(strict_types=1);

namespace Codeception\Lib\Connector;

use InvalidArgumentException;
use LogicException;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\TestContainer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelBrowser;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;

use function codecept_debug;

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
        foreach (array_keys($this->persistentServices) as $service) {
            if ($this->container->has($service)) {
                $this->persistentServices[$service] = $this->container->get($service);
            }
        }

        $this->persistDoctrineConnections();
        if ($this->kernel instanceof Kernel) {
            $this->ensureKernelShutdown();
            $this->kernel->boot();
        }
        $this->container = $this->resolveContainer();
        foreach ($this->persistentServices as $name => $service) {
            try {
                $this->container->set($name, $service);
            } catch (InvalidArgumentException $e) {
                codecept_debug("[Symfony] Can't set persistent service {$name}: {$e->getMessage()}");
            }
        }

        $this->getProfiler()?->enable();
    }

    protected function ensureKernelShutdown(): void
    {
        $this->kernel->boot();
        $this->kernel->shutdown();
    }

    private function resolveContainer(): ContainerInterface
    {
        $container = $this->kernel->getContainer();

        if ($container->has('test.service_container')) {
            $testContainer = $container->get('test.service_container');
            if (!$testContainer instanceof ContainerInterface) {
                throw new LogicException('Service "test.service_container" must implement ' . ContainerInterface::class);
            }
            $container = $testContainer;
        }

        return $container;
    }

    private function getProfiler(): ?Profiler
    {
        $profiler = $this->container->get('profiler');
        return $profiler instanceof Profiler ? $profiler : null;
    }

    private function persistDoctrineConnections(): void
    {
        if (!$this->container->hasParameter('doctrine.connections')) {
            return;
        }

        if ($this->container instanceof TestContainer) {
            $method = new ReflectionMethod($this->container, 'getPublicContainer');
            $publicContainer = $method->invoke($this->container);
        } else {
            $publicContainer = $this->container;
        }

        if (!is_object($publicContainer) || !method_exists($publicContainer, 'getParameterBag')) {
            return;
        }

        $target = property_exists($publicContainer, 'parameters')
            ? $publicContainer
            : $publicContainer->getParameterBag();

        if (!is_object($target) || !property_exists($target, 'parameters')) {
            return;
        }
        $prop = new ReflectionProperty($target, 'parameters');

        $params = (array) $prop->getValue($target);
        unset($params['doctrine.connections']);
        $prop->setValue($target, $params);
    }
}
