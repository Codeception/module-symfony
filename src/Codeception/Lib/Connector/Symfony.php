<?php

namespace Codeception\Lib\Connector;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Client;
use Symfony\Component\HttpKernel\HttpKernelBrowser;
use Symfony\Component\HttpKernel\Kernel;

if (Kernel::VERSION_ID < 40300) {
    class_alias(Client::class, HttpKernelBrowser::class);
}

class Symfony extends HttpKernelBrowser
{
    /**
     * @var ContainerInterface
     */
    private $container = null;

    /**
     * @var boolean
     */
    private $hasPerformedRequest = false;

    /**
     * @var array
     */
    public $persistentServices = [];

    /**
     * @var boolean
     */
    private $rebootable = true;

    /**
     * Constructor.
     *
     * @param Kernel                                $kernel     A booted HttpKernel instance
     * @param array                                 $services   An injected services
     * @param boolean                               $rebootable
     */
    public function __construct(Kernel $kernel, array $services = [], $rebootable = true)
    {
        parent::__construct($kernel);
        $this->followRedirects(true);
        $this->rebootable = (boolean)$rebootable;
        $this->persistentServices = $services;
        $this->container = $this->kernel->getContainer();
        $this->rebootKernel();
    }

    /**
     * @param Request $request
     * @return Response
     */
    protected function doRequest($request)
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
    public function rebootKernel()
    {
        if ($this->container) {
            foreach ($this->persistentServices as $serviceName => $service) {
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
