<?php

declare(strict_types=1);

namespace Tests\Support;

use BadMethodCallException;
use Codeception\Module\Symfony\CacheTrait;
use Codeception\Module\Symfony\HttpKernelAssertionsTrait;
use Codeception\Module\Symfony\ServicesAssertionsTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Tests\App\Doctrine\TestDatabaseSetup;
use Tests\App\TestKernel;

abstract class CodeceptTestCase extends TestCase
{
    use CacheTrait;
    use HttpKernelAssertionsTrait;
    use ServicesAssertionsTrait;

    protected KernelBrowser $client;
    protected KernelInterface $kernel;
    protected bool $profilerEnabled = true;

    /** @var array<string, bool> */
    protected array $config = ['guard' => false, 'authenticator' => false];

    protected function setUp(): void
    {
        parent::setUp();

        $this->state = [];
        $this->kernel = $this->createKernel();
        $this->kernel->boot();

        $container = $this->_getContainer();

        if ($container->has('doctrine.orm.entity_manager')) {
            /** @var EntityManagerInterface $em */
            $em = $container->get('doctrine.orm.entity_manager');
            $this->setUpDatabase($em);
        }

        $testClient = $container->has('test.client') ? $container->get('test.client') : null;
        $this->client = $testClient instanceof KernelBrowser ? $testClient : new KernelBrowser($this->kernel);

        if ($this->profilerEnabled) {
            $this->client->enableProfiler();
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->kernel)) {
            $this->kernel->shutdown();
        }

        $this->cachedResponse = null;
        $this->cachedProfile  = null;

        $this->restoreErrorHandler();
        parent::tearDown();
    }

    private function restoreErrorHandler(): void
    {
        if (!class_exists(ErrorHandler::class)) {
            return;
        }

        $exceptionHandler = set_exception_handler(null);
        restore_exception_handler();
        if (is_array($exceptionHandler) && $exceptionHandler[0] instanceof ErrorHandler) {
            restore_exception_handler();
        }

        $errorHandler = set_error_handler(null);
        restore_error_handler();
        if (is_array($errorHandler) && $errorHandler[0] instanceof ErrorHandler) {
            restore_error_handler();
        }
    }

    protected function createKernel(): KernelInterface
    {
        $kernelClass = $this->getKernelClass();

        $environment = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'test';
        if (!is_scalar($environment)) {
            $environment = 'test';
        }

        $debug = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? true;
        if (!is_bool($debug)) {
            $debug = is_scalar($debug)
                ? filter_var((string) $debug, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true
                : true;
        }

        /** @var KernelInterface $kernel */
        $kernel = new $kernelClass((string) $environment, $debug);

        return $kernel;
    }

    protected function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUpDatabase(EntityManagerInterface $em): void
    {
        TestDatabaseSetup::init($em);
    }

    protected function getClient(): KernelBrowser
    {
        return $this->client;
    }

    protected function _getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = $this->grabService('doctrine.orm.entity_manager');
        return $em;
    }

    protected function getProfile(): ?Profile
    {
        $client = $this->getClient();
        $profile = $client->getProfile();

        if ($profile instanceof Profile) {
            return $profile;
        }

        try {
            $response = $client->getResponse();
            $request = $client->getRequest();
        } catch (BadMethodCallException) {
            return null;
        }

        if ($this->cachedResponse === $response) {
            return $this->cachedProfile;
        }

        $container = $this->_getContainer();
        if (!$container->has('profiler')) {
            return null;
        }

        /** @var Profiler $profiler */
        $profiler = $container->get('profiler');
        $profile = $profiler->collect($request, $response);

        if ($profile instanceof Profile) {
            $this->cachedResponse = $response;
            $this->cachedProfile  = $profile;

            return $profile;
        }

        return null;
    }
}
