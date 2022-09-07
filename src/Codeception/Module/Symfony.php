<?php

declare(strict_types=1);

namespace Codeception\Module;

use BadMethodCallException;
use Codeception\Exception\ModuleRequireException;
use Codeception\Lib\Connector\Symfony as SymfonyConnector;
use Codeception\Lib\Framework;
use Codeception\Lib\Interfaces\DoctrineProvider;
use Codeception\Lib\Interfaces\PartedModule;
use Codeception\Module\Symfony\BrowserAssertionsTrait;
use Codeception\Module\Symfony\ConsoleAssertionsTrait;
use Codeception\Module\Symfony\DoctrineAssertionsTrait;
use Codeception\Module\Symfony\EventsAssertionsTrait;
use Codeception\Module\Symfony\FormAssertionsTrait;
use Codeception\Module\Symfony\MailerAssertionsTrait;
use Codeception\Module\Symfony\MimeAssertionsTrait;
use Codeception\Module\Symfony\ParameterAssertionsTrait;
use Codeception\Module\Symfony\RouterAssertionsTrait;
use Codeception\Module\Symfony\SecurityAssertionsTrait;
use Codeception\Module\Symfony\ServicesAssertionsTrait;
use Codeception\Module\Symfony\SessionAssertionsTrait;
use Codeception\Module\Symfony\TimeAssertionsTrait;
use Codeception\Module\Symfony\TwigAssertionsTrait;
use Codeception\TestInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use ReflectionClass;
use ReflectionException;
use Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Mailer\DataCollector\MessageDataCollector;
use Symfony\Component\Routing\Route;
use Symfony\Component\VarDumper\Cloner\Data;
use function array_keys;
use function array_map;
use function array_merge;
use function array_search;
use function array_unique;
use function class_exists;
use function codecept_root_dir;
use function count;
use function file_exists;
use function implode;
use function ini_get;
use function ini_set;
use function is_null;
use function iterator_to_array;
use function number_format;
use function sprintf;

/**
 * This module uses [Symfony's DomCrawler](https://symfony.com/doc/current/components/dom_crawler.html)
 * and [HttpKernel Component](https://symfony.com/doc/current/components/http_kernel.html) to emulate requests and test response.
 *
 * * Access Symfony services through the dependency injection container: [`$I->grabService(...)`](#grabService)
 * * Use Doctrine to test against the database: `$I->seeInRepository(...)` - see [Doctrine Module](https://codeception.com/docs/modules/Doctrine2)
 * * Assert that emails would have been sent: [`$I->seeEmailIsSent()`](#seeEmailIsSent)
 * * Tests are wrapped into Doctrine transaction to speed them up.
 * * Symfony Router can be cached between requests to speed up testing.
 *
 * ## Demo Project
 *
 * <https://github.com/Codeception/symfony-module-tests>
 *
 * ## Config
 *
 * ### Symfony 5.x or 4.4
 *
 * * app_path: 'src' - Specify custom path to your app dir, where the kernel interface is located.
 * * environment: 'local' - Environment used for load kernel
 * * kernel_class: 'App\Kernel' - Kernel class name
 * * em_service: 'doctrine.orm.entity_manager' - Use the stated EntityManager to pair with Doctrine Module.
 * * debug: true - Turn on/off debug mode
 * * cache_router: 'false' - Enable router caching between tests in order to [increase performance](http://lakion.com/blog/how-did-we-speed-up-sylius-behat-suite-with-blackfire)
 * * rebootable_client: 'true' - Reboot client's kernel before each request
 * * guard: 'false' - Enable custom authentication system with guard (only for 4.x and 5.x versions of the symfony)
 * * authenticator: 'false' - Reboot client's kernel before each request (only for 6.x versions of the symfony)
 *
 * #### Example (`functional.suite.yml`) - Symfony 4 Directory Structure
 *
 *     modules:
 *        enabled:
 *           - Symfony:
 *               app_path: 'src'
 *               environment: 'test'
 *
 *
 * ## Public Properties
 *
 * * kernel - HttpKernel instance
 * * client - current Crawler instance
 *
 * ## Parts
 *
 * * `services`: Includes methods related to the Symfony dependency injection container (DIC):
 *     * grabService
 *     * persistService
 *     * persistPermanentService
 *     * unpersistService
 *
 * See [WebDriver module](https://codeception.com/docs/modules/WebDriver#Loading-Parts-from-other-Modules)
 * for general information on how to load parts of a framework module.
 *
 * Usage example:
 *
 * ```yaml
 * actor: AcceptanceTester
 * modules:
 *     enabled:
 *         - Symfony:
 *             part: services
 *         - Doctrine2:
 *             depends: Symfony
 *         - WebDriver:
 *             url: http://example.com
 *             browser: firefox
 * ```
 *
 * If you're using Symfony with Eloquent ORM (instead of Doctrine), you can load the [`ORM` part of Laravel module](https://codeception.com/docs/modules/Laravel5#Parts)
 * in addition to Symfony module.
 *
 */
class Symfony extends Framework implements DoctrineProvider, PartedModule
{
    use BrowserAssertionsTrait;
    use ConsoleAssertionsTrait;
    use DoctrineAssertionsTrait;
    use EventsAssertionsTrait;
    use FormAssertionsTrait;
    use MailerAssertionsTrait;
    use MimeAssertionsTrait;
    use ParameterAssertionsTrait;
    use RouterAssertionsTrait;
    use SecurityAssertionsTrait;
    use ServicesAssertionsTrait;
    use SessionAssertionsTrait;
    use TimeAssertionsTrait;
    use TwigAssertionsTrait;

    public Kernel $kernel;

    /**
     * @var SymfonyConnector
     */
    public ?AbstractBrowser $client = null;

    /**
     * @var array<string, mixed>
     */
    public array $config = [
        'app_path' => 'app',
        'kernel_class' => 'App\Kernel',
        'environment' => 'test',
        'debug' => true,
        'cache_router' => false,
        'em_service' => 'doctrine.orm.entity_manager',
        'rebootable_client' => true,
        'authenticator' => false,
        'guard' => false
    ];

    /**
     * @return string[]
     */
    public function _parts(): array
    {
        return ['services'];
    }

    protected ?string $kernelClass = null;

    /**
     * Services that should be persistent permanently for all tests
     *
     * @var array
     */
    protected $permanentServices = [];

    /**
     * Services that should be persistent during test execution between kernel reboots
     *
     * @var array
     */
    protected $persistentServices = [];

    public function _initialize(): void
    {
        $this->kernelClass = $this->getKernelClass();
        $maxNestingLevel = 200; // Symfony may have very long nesting level
        $xdebugMaxLevelKey = 'xdebug.max_nesting_level';
        if (ini_get($xdebugMaxLevelKey) < $maxNestingLevel) {
            ini_set($xdebugMaxLevelKey, (string)$maxNestingLevel);
        }

        $this->kernel = new $this->kernelClass($this->config['environment'], $this->config['debug']);
        $this->kernel->boot();

        if ($this->config['cache_router'] === true) {
            $this->persistPermanentService('router');
        }
    }

    /**
     * Initialize new client instance before each test
     */
    public function _before(TestInterface $test): void
    {
        $this->persistentServices = array_merge($this->persistentServices, $this->permanentServices);
        $this->client = new SymfonyConnector($this->kernel, $this->persistentServices, $this->config['rebootable_client']);
    }

    /**
     * Update permanent services after each test
     */
    public function _after(TestInterface $test): void
    {
        foreach (array_keys($this->permanentServices) as $serviceName) {
            $this->permanentServices[$serviceName] = $this->grabService($serviceName);
        }

        parent::_after($test);
    }

    protected function onReconfigure(array $settings = []): void
    {
        parent::_beforeSuite($settings);
        $this->_initialize();
    }

    /**
     * Retrieve Entity Manager.
     *
     * EM service is retrieved once and then that instance returned on each call
     */
    public function _getEntityManager(): EntityManagerInterface
    {
        if ($this->kernel === null) {
            $this->fail('Symfony module is not loaded');
        }

        $emService = $this->config['em_service'];
        if (!isset($this->permanentServices[$emService])) {
            // Try to persist configured entity manager
            $this->persistPermanentService($emService);
            $container = $this->_getContainer();
            if ($container->has('doctrine')) {
                $this->persistPermanentService('doctrine');
            }

            if ($container->has('doctrine.orm.default_entity_manager')) {
                $this->persistPermanentService('doctrine.orm.default_entity_manager');
            }

            if ($container->has('doctrine.dbal.default_connection')) {
                $this->persistPermanentService('doctrine.dbal.default_connection');
            }
        }

        return $this->permanentServices[$emService];
    }

    /**
     * Return container.
     */
    public function _getContainer(): ContainerInterface
    {
        $container = $this->kernel->getContainer();
        if (!$container instanceof ContainerInterface) {
            $this->fail('Could not get Symfony container');
        }

        if ($container->has('test.service_container')) {
            $container = $container->get('test.service_container');
        }

        return $container;
    }

    protected function getClient(): SymfonyConnector
    {
        return $this->client ?: $this->fail('Client is not initialized');
    }

    /**
     * Attempts to guess the kernel location.
     * When the Kernel is located, the file is required.
     *
     * @return string The Kernel class name
     * @throws ModuleRequireException|ReflectionException
     */
    protected function getKernelClass(): string
    {
        $path = codecept_root_dir() . $this->config['app_path'];
        if (!file_exists($path)) {
            throw new ModuleRequireException(
                self::class,
                "Can't load Kernel from {$path}.\n"
                . 'Directory does not exist. Set `app_path` in your suite configuration to a valid application path.'
            );
        }

        $finder = new Finder();
        $finder->name('*Kernel.php')->depth('0')->in($path);
        $results = iterator_to_array($finder);
        if ($results === []) {
            throw new ModuleRequireException(
                self::class,
                "File with Kernel class was not found at {$path}.\n"
                . 'Specify directory where file with Kernel class for your application is located with `app_path` parameter.'
            );
        }

        $this->requireAdditionalAutoloader();

        $filesRealPath = array_map(function ($file) {
            require_once $file;
            return $file->getRealPath();
        }, $results);

        $kernelClass = $this->config['kernel_class'];

        if (class_exists($kernelClass)) {
            $reflectionClass = new ReflectionClass($kernelClass);
            if ($file = array_search($reflectionClass->getFileName(), $filesRealPath)) {
                return $kernelClass;
            }

            throw new ModuleRequireException(self::class, "Kernel class was not found in {$file}.");
        }

        throw new ModuleRequireException(
            self::class,
            "Kernel class was not found.\n"
            . 'Specify directory where file with Kernel class for your application is located with `app_path` parameter.'
        );
    }

    protected function getProfile(): ?Profile
    {
        /** @var Profiler $profiler */
        if (!$profiler = $this->getService('profiler')) {
            return null;
        }

        try {
            $response = $this->getClient()->getResponse();
            return $profiler->loadProfileFromResponse($response);
        } catch (BadMethodCallException $e) {
            $this->fail('You must perform a request before using this method.');
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        return null;
    }

    /**
     * Grabs a Symfony Data Collector
     */
    protected function grabCollector(string $collector, string $function, string $message = null): DataCollectorInterface
    {
        if (($profile = $this->getProfile()) === null) {
            $this->fail(
                sprintf("The Profile is needed to use the '%s' function.", $function)
            );
        }

        if (!$profile->hasCollector($collector)) {
            if ($message) {
                $this->fail($message);
            }

            $this->fail(
                sprintf("The '%s' collector is needed to use the '%s' function.", $collector, $function)
            );
        }

        return $profile->getCollector($collector);
    }

    /**
     * Set the data that will be displayed when running a test with the `--debug` flag
     *
     * @param $url
     */
    protected function debugResponse($url): void
    {
        parent::debugResponse($url);

        if (($profile = $this->getProfile()) === null) {
            return;
        }

        if ($profile->hasCollector('security')) {
            /** @var SecurityDataCollector $security */
            $security = $profile->getCollector('security');
            if ($security->isAuthenticated()) {
                $roles = $security->getRoles();

                if ($roles instanceof Data) {
                    $roles = $roles->getValue();
                }

                $this->debugSection(
                    'User',
                    $security->getUser()
                    . ' [' . implode(',', $roles) . ']'
                );
            } else {
                $this->debugSection('User', 'Anonymous');
            }
        }

        if ($profile->hasCollector('mailer')) {
            /** @var MessageDataCollector $mailerCollector */
            $mailerCollector = $profile->getCollector('mailer');
            $emails = count($mailerCollector->getEvents()->getMessages());
            $this->debugSection('Emails', $emails . ' sent');
        }

        if ($profile->hasCollector('time')) {
            /** @var TimeDataCollector $timeCollector */
            $timeCollector = $profile->getCollector('time');
            $duration = number_format($timeCollector->getDuration(), 2) . ' ms';
            $this->debugSection('Time', $duration);
        }
    }

    /**
     * Returns a list of recognized domain names.
     */
    protected function getInternalDomains(): array
    {
        $internalDomains = [];

        $router = $this->grabRouterService();
        $routes = $router->getRouteCollection();
        /* @var Route $route */
        foreach ($routes as $route) {
            if (!is_null($route->getHost())) {
                $compiled = $route->compile();
                if (!is_null($compiled->getHostRegex())) {
                    $internalDomains[] = $compiled->getHostRegex();
                }
            }
        }

        return array_unique($internalDomains);
    }

    /**
     * Ensures autoloader loading of additional directories.
     * It is only required for CI jobs to run correctly.
     */
    private function requireAdditionalAutoloader(): void
    {
        $autoLoader = codecept_root_dir() . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (file_exists($autoLoader)) {
            require_once $autoLoader;
        }
    }
}
