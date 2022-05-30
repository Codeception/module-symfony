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
    private bool $rebootable;

    private bool $hasPerformedRequest = false;

    private ?ContainerInterface $container;

    public array $persistentServices = [];

    /**
     * Constructor.
     *
     * @param Kernel $kernel     A booted HttpKernel instance
     * @param array  $services   An injected services
     * @param bool   $rebootable
     */
    public function __construct(Kernel $kernel, array $services = [], bool $rebootable = true)
    {
        parent::__construct($kernel);
        $this->followRedirects();
        $this->rebootable = $rebootable;
        $this->persistentServices = $services;
        $this->container = $this->getContainer();
        $this->rebootKernel();
    }

    /**
     * @param Request $request
     * @return Response
     */
    protected function doRequest($request): Response
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
     * Reboot kernel
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
        $this->kernel->reboot(null);

        $this->container = $this->getContainer();

        foreach ($this->persistentServices as $serviceName => $service) {
            try {
                $this->container->set($serviceName, $service);
            } catch (InvalidArgumentException $e) {
                //Private services can't be set in Symfony 4
                codecept_debug("[Symfony] Can't set persistent service {$serviceName}: " . $e->getMessage());
            }
        }

        if ($profiler = $this->getProfiler()) {
            $profiler->enable();
        }
    }

    private function getContainer(): ?ContainerInterface
    {
        /** @var ContainerInterface $container */
        $container = $this->kernel->getContainer();
        if ($container->has('test.service_container')) {
            $container = $container->get('test.service_container');
        }

        return $container;
    }

    private function getProfiler(): ?Profiler
    {
        if ($this->container->has('profiler')) {
            /** @var Profiler $profiler */
            $profiler = $this->container->get('profiler');
            return $profiler;
        }

        return null;
    }

    private function getService(string $serviceName): ?object
    {
        if ($this->container->has($serviceName)) {
            return $this->container->get($serviceName);
        }

        return null;
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
