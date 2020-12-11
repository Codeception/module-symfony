<?php

declare(strict_types=1);

namespace Codeception\Lib\Connector;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelBrowser;
use Symfony\Component\HttpKernel\Kernel;
use function array_keys;
use function class_alias;
use function codecept_debug;

if (Kernel::VERSION_ID < 40300) {
    class_alias('Symfony\Component\HttpKernel\Client', 'Symfony\Component\HttpKernel\HttpKernelBrowser');
}

class Symfony extends HttpKernelBrowser
{
    /**
     * @var bool
     */
    private $rebootable = true;

    /**
     * @var bool
     */
    private $hasPerformedRequest = false;

    /**
     * @var ContainerInterface
     */
    private $container = null;

    /**
     * @var array
     */
    public $persistentServices = [];

    /**
     * Constructor.
     *
     * @param Kernel            $kernel     A booted HttpKernel instance
     * @param array             $services   An injected services
     * @param bool              $rebootable
     */
    public function __construct(Kernel $kernel, array $services = [], bool $rebootable = true)
    {
        parent::__construct($kernel);
        $this->followRedirects(true);
        $this->rebootable = $rebootable;
        $this->persistentServices = $services;
        $this->container = $this->kernel->getContainer();
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
                if ($this->container->has($serviceName)) {
                    $this->persistentServices[$serviceName] = $this->container->get($serviceName);
                }
            }
        }

        $this->kernel->shutdown();
        $this->kernel->boot();

        $this->container = $this->kernel->getContainer();

        foreach ($this->persistentServices as $serviceName => $service) {
            try {
                $this->container->set($serviceName, $service);
            } catch (InvalidArgumentException $e) {
                //Private services can't be set in Symfony 4
                codecept_debug("[Symfony] Can't set persistent service $serviceName: " . $e->getMessage());
            }
        }

        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->enable();
        }
    }
}
