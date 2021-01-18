<?php

declare(strict_types=1);

namespace Codeception\Module;

use BadMethodCallException;
use Codeception\Configuration;
use Codeception\Exception\ModuleException;
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
use Codeception\Module\Symfony\ParameterAssertionsTrait;
use Codeception\Module\Symfony\RouterAssertionsTrait;
use Codeception\Module\Symfony\SecurityAssertionsTrait;
use Codeception\Module\Symfony\ServicesAssertionsTrait;
use Codeception\Module\Symfony\SessionAssertionsTrait;
use Codeception\TestInterface;
use Exception;
use ReflectionClass;
use ReflectionException;
use Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
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
use function is_string;
use function iterator_to_array;
use function sprintf;

/**
 * This module uses Symfony Crawler and HttpKernel to emulate requests and test response.
 *
 * ## Demo Project
 *
 * <https://github.com/Codeception/symfony-module-tests>
 *
 * ## Config
 *
 * ### Symfony 5.x or 4.x
 *
 * * app_path: 'src' - in Symfony 4 Kernel is located inside `src`
 * * environment: 'local' - environment used for load kernel
 * * kernel_class: 'App\Kernel' - kernel class name
 * * em_service: 'doctrine.orm.entity_manager' - use the stated EntityManager to pair with Doctrine Module.
 * * debug: true - turn on/off debug mode
 * * cache_router: 'false' - enable router caching between tests in order to [increase performance](http://lakion.com/blog/how-did-we-speed-up-sylius-behat-suite-with-blackfire)
 * * rebootable_client: 'true' - reboot client's kernel before each request
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
 * ### Symfony 3.x
 *
 * * app_path: 'app' - specify custom path to your app dir, where the kernel interface is located.
 * * var_path: 'var' - specify custom path to your var dir, where bootstrap cache is located.
 * * environment: 'local' - environment used for load kernel
 * * kernel_class: 'AppKernel' - kernel class name
 * * em_service: 'doctrine.orm.entity_manager' - use the stated EntityManager to pair with Doctrine Module.
 * * debug: true - turn on/off debug mode
 * * cache_router: 'false' - enable router caching between tests in order to [increase performance](http://lakion.com/blog/how-did-we-speed-up-sylius-behat-suite-with-blackfire)
 * * rebootable_client: 'true' - reboot client's kernel before each request
 *
 * #### Example (`functional.suite.yml`) - Symfony 3 Directory Structure
 *
 *     modules:
 *        enabled:
 *           - Symfony:
 *               app_path: 'app/front'
 *               var_path: 'var'
 *               environment: 'local_test'
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
 *             url: http://your-url.com
 *             browser: firefox
 * ```
 *
 * If you're using Symfony with Eloquent ORM (instead of Doctrine), you can load the [`ORM` part of Laravel module](https://codeception.com/docs/modules/Laravel5#Parts)
 * in addition to Symfony module.
 *
 */
class Symfony extends Framework implements DoctrineProvider, PartedModule
{
    use
        BrowserAssertionsTrait,
        ConsoleAssertionsTrait,
        DoctrineAssertionsTrait,
        EventsAssertionsTrait,
        FormAssertionsTrait,
        MailerAssertionsTrait,
        ParameterAssertionsTrait,
        RouterAssertionsTrait,
        SecurityAssertionsTrait,
        ServicesAssertionsTrait,
        SessionAssertionsTrait
    ;

    private static $possibleKernelClasses = [
        'AppKernel', // Symfony Standard
        'App\Kernel', // Symfony Flex
    ];

    /**
     * @var Kernel
     */
    public $kernel;

    public $config = [
        'app_path' => 'app',
        'var_path' => 'app',
        'kernel_class' => null,
        'environment' => 'test',
        'debug' => true,
        'cache_router' => false,
        'em_service' => 'doctrine.orm.entity_manager',
        'rebootable_client' => true,
        'guard' => false
    ];

    /**
     * @return string[]
     */
    public function _parts(): array
    {
        return ['services'];
    }

    /**
     * @var string|null
     */
    protected $kernelClass;

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
        $this->initializeSymfonyCache();
        $this->kernelClass = $this->getKernelClass();
        $maxNestingLevel = 200; // Symfony may have very long nesting level
        $xdebugMaxLevelKey = 'xdebug.max_nesting_level';
        if (ini_get($xdebugMaxLevelKey) < $maxNestingLevel) {
            ini_set($xdebugMaxLevelKey, (string) $maxNestingLevel);
        }

        $this->kernel = new $this->kernelClass($this->config['environment'], $this->config['debug']);
        $this->kernel->boot();

        if ($this->config['cache_router'] === true) {
            $this->persistPermanentService('router');
        }
    }

    /**
     * Require Symfony's bootstrap.php.cache
     */
    private function initializeSymfonyCache(): void
    {
        $cache = Configuration::projectDir() . $this->config['var_path'] . DIRECTORY_SEPARATOR . 'bootstrap.php.cache';

        if (file_exists($cache)) {
            require_once $cache;
        }
    }

    /**
     * Initialize new client instance before each test
     *
     * @param TestInterface $test
     */
    public function _before(TestInterface $test): void
    {
        $this->persistentServices = array_merge($this->persistentServices, $this->permanentServices);
        $this->client = new SymfonyConnector($this->kernel, $this->persistentServices, $this->config['rebootable_client']);
    }

    /**
     * Update permanent services after each test
     *
     * @param TestInterface $test
     */
    public function _after(TestInterface $test): void
    {
        foreach (array_keys($this->permanentServices) as $serviceName) {
            $this->permanentServices[$serviceName] = $this->grabService($serviceName);
        }
        parent::_after($test);
    }

    protected function onReconfigure($settings = []): void
    {

        parent::_beforeSuite($settings);
        $this->_initialize();
    }

    /**
     * Retrieve Entity Manager.
     *
     * EM service is retrieved once and then that instance returned on each call
     */
    public function _getEntityManager()
    {
        if ($this->kernel === null) {
            $this->fail('Symfony module is not loaded');
        }
        if (!isset($this->permanentServices[$this->config['em_service']])) {
            // try to persist configured EM
            $this->persistPermanentService($this->config['em_service']);
            $container = $this->_getContainer();
            if ($container->has('doctrine')) {
                $this->persistPermanentService('doctrine');
            }
            if ($container->has('doctrine.orm.default_entity_manager')) {
                $this->persistPermanentService('doctrine.orm.default_entity_manager');
            }
            if ($container->has('doctrine.dbal.backend_connection')) {
                $this->persistPermanentService('doctrine.dbal.backend_connection');
            }
        }
        return $this->permanentServices[$this->config['em_service']];
    }

    /**
     * Return container.
     *
     * @return ContainerInterface|mixed
     */
    public function _getContainer(): ContainerInterface
    {
        $container = $this->kernel->getContainer();

        if (!($container instanceof ContainerInterface)) {
            $this->fail('Could not get Symfony container');
        }

        if ($container->has('test.service_container')) {
            return $container->get('test.service_container');
        }

        return $container;
    }

    /**
     * Attempts to guess the kernel location.
     *
     * When the Kernel is located, the file is required.
     *
     * @return string The Kernel class name
     * @throws ModuleRequireException|ReflectionException|ModuleException
     */
    protected function getKernelClass(): string
    {
        $path = codecept_root_dir() . $this->config['app_path'];
        if (!file_exists(codecept_root_dir() . $this->config['app_path'])) {
            throw new ModuleRequireException(
                self::class,
                "Can't load Kernel from $path.\n"
                . 'Directory does not exists. Use `app_path` parameter to provide valid application path'
            );
        }

        $finder = new Finder();
        $finder->name('*Kernel.php')->depth('0')->in($path);
        $results = iterator_to_array($finder);
        if ($results === []) {
            throw new ModuleRequireException(
                self::class,
                "File with Kernel class was not found at $path. "
                . 'Specify directory where file with Kernel class for your application is located with `app_path` parameter.'
            );
        }

        if (file_exists(codecept_root_dir() . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
            // ensure autoloader from this dir is loaded
            require_once codecept_root_dir() . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        }

        $filesRealPath = array_map(function ($file) {
            require_once $file;
            return $file->getRealPath();
        }, $results);

        $possibleKernelClasses = $this->getPossibleKernelClasses();

        foreach ($possibleKernelClasses as $class) {
            if (class_exists($class)) {
                $refClass = new ReflectionClass($class);
                if ($file = array_search($refClass->getFileName(), $filesRealPath)) {
                    return $class;
                }
            }
        }

        throw new ModuleRequireException(
            self::class,
            "Kernel class was not found in $file. "
            . 'Specify directory where file with Kernel class for your application is located with `app_path` parameter.'
        );
    }

    /**
     * @return Profile|null
     */
    protected function getProfile(): ?Profile
    {
        /** @var Profiler $profiler */
        if (!$profiler = $this->getService('profiler')) {
            return null;
        }
        try {
            /** @var Response $response */
            $response = $this->client->getResponse();
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
     *
     * @param string $collector
     * @param string $function
     * @param string|null $message
     * @return DataCollectorInterface
     */
    protected function grabCollector(string $collector, string $function, ?string $message = null): DataCollectorInterface
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
     *
     * @return mixed[]
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
     * Returns list of the possible kernel classes based on the module configuration
     *
     * @return array
     * @throws ModuleException
     */
    private function getPossibleKernelClasses(): array
    {
        if (empty($this->config['kernel_class'])) {
            return self::$possibleKernelClasses;
        }

        if (!is_string($this->config['kernel_class'])) {
            throw new ModuleException(
                self::class,
                "Parameter 'kernel_class' must have 'string' type.\n"
            );
        }

        return [$this->config['kernel_class']];
    }
}
