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
use Codeception\Module\Symfony\DomCrawlerAssertionsTrait;
use Codeception\Module\Symfony\EventsAssertionsTrait;
use Codeception\Module\Symfony\FormAssertionsTrait;
use Codeception\Module\Symfony\HttpClientAssertionsTrait;
use Codeception\Module\Symfony\MailerAssertionsTrait;
use Codeception\Module\Symfony\MimeAssertionsTrait;
use Codeception\Module\Symfony\NotificationAssertionsTrait;
use Codeception\Module\Symfony\ParameterAssertionsTrait;
use Codeception\Module\Symfony\RouterAssertionsTrait;
use Codeception\Module\Symfony\SecurityAssertionsTrait;
use Codeception\Module\Symfony\ServicesAssertionsTrait;
use Codeception\Module\Symfony\SessionAssertionsTrait;
use Codeception\Module\Symfony\TimeAssertionsTrait;
use Codeception\Module\Symfony\TwigAssertionsTrait;
use Codeception\Module\Symfony\ValidatorAssertionsTrait;
use Codeception\TestInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use LogicException;
use ReflectionClass;
use ReflectionException;
use Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Mailer\DataCollector\MessageDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;
use function array_keys;
use function array_map;
use function array_unique;
use function class_exists;
use function codecept_root_dir;
use function count;
use function file_exists;
use function implode;
use function ini_get;
use function ini_set;
use function iterator_to_array;
use function number_format;
use function sprintf;

/**
 * This module uses [Symfony's DomCrawler](https://symfony.com/doc/current/components/dom_crawler.html)
 * and [HttpKernel Component](https://symfony.com/doc/current/components/http_kernel.html) to emulate requests and test response.
 *
 * * Access Symfony services through the dependency injection container: [`$I->grabService(...)`](#grabService)
 * * Use Doctrine to test against the database: `$I->seeInRepository(...)` - see [Doctrine Module](https://codeception.com/docs/modules/Doctrine)
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
 * ### Symfony 5.4 or higher
 *
 * * `app_path`: 'src' - Specify custom path to your app dir, where the kernel interface is located.
 * * `environment`: 'local' - Environment used for load kernel
 * * `kernel_class`: 'App\Kernel' - Kernel class name
 * * `em_service`: 'doctrine.orm.entity_manager' - Use the stated EntityManager to pair with Doctrine Module.
 * * `debug`: true - Turn on/off [debug mode](https://codeception.com/docs/Debugging)
 * * `cache_router`: 'false' - Enable router caching between tests in order to [increase performance](http://lakion.com/blog/how-did-we-speed-up-sylius-behat-suite-with-blackfire) (can have an impact on ajax requests sending via '$I->sendAjaxPostRequest()')
 * * `rebootable_client`: 'true' - Reboot client's kernel before each request
 * * `guard`: 'false' - Enable custom authentication system with guard (only for Symfony 5.4)
 * * `bootstrap`: 'false' - Enable the test environment setup with the tests/bootstrap.php file if it exists or with Symfony DotEnv otherwise. If false, it does nothing.
 * * `authenticator`: 'false' - Reboot client's kernel before each request (only for Symfony 6.0 or higher)
 *
 * #### Sample `Functional.suite.yml`
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
 *         - Doctrine:
 *             depends: Symfony
 *         - WebDriver:
 *             url: http://example.com
 *             browser: firefox
 * ```
 *
 * If you're using Symfony with Eloquent ORM (instead of Doctrine), you can load the [`ORM` part of Laravel module](https://codeception.com/docs/modules/Laravel#Parts)
 * in addition to Symfony module.
 *
 */
class Symfony extends Framework implements DoctrineProvider, PartedModule
{
    use BrowserAssertionsTrait;
    use ConsoleAssertionsTrait;
    use DoctrineAssertionsTrait;
    use DomCrawlerAssertionsTrait;
    use EventsAssertionsTrait;
    use FormAssertionsTrait;
    use HttpClientAssertionsTrait;
    use MailerAssertionsTrait;
    use MimeAssertionsTrait;
    use NotificationAssertionsTrait;
    use ParameterAssertionsTrait;
    use RouterAssertionsTrait;
    use SecurityAssertionsTrait;
    use ServicesAssertionsTrait;
    use SessionAssertionsTrait;
    use TimeAssertionsTrait;
    use TwigAssertionsTrait;
    use ValidatorAssertionsTrait;

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
        'bootstrap' => false,
        'guard' => false
    ];

    protected ?string $kernelClass = null;
    /**
     * Services that should be persistent permanently for all tests
     */
    protected array $permanentServices = [];
    /**
     * Services that should be persistent during test execution between kernel reboots
     */
    protected array $persistentServices = [];

    /**
     * @return string[]
     */
    public function _parts(): array
    {
        return ['services'];
    }

    public function _initialize(): void
    {
        $this->kernelClass = $this->getKernelClass();
        $this->setXdebugMaxNestingLevel(200);
        $this->kernel = new $this->kernelClass($this->config['environment'], $this->config['debug']);
        if ($this->config['bootstrap']) {
            $this->bootstrapEnvironment();
        }
        $this->kernel->boot();
        if ($this->config['cache_router']) {
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
            $this->persistPermanentService($emService);
            $container = $this->_getContainer();
            $services = ['doctrine', 'doctrine.orm.default_entity_manager', 'doctrine.dbal.default_connection'];
            foreach ($services as $service) {
                if ($container->has($service)) {
                    $this->persistPermanentService($service);
                }
            }
        }

        return $this->permanentServices[$emService];
    }

    public function _getContainer(): ContainerInterface
    {
        $container = $this->kernel->getContainer();

        return $container->has('test.service_container') ? $container->get('test.service_container') : $container;
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

        $this->requireAdditionalAutoloader();

        $finder = new Finder();
        $results = iterator_to_array($finder->name('*Kernel.php')->depth('0')->in($path));
        if ($results === []) {
            throw new ModuleRequireException(
                self::class,
                "File with Kernel class was not found at {$path}.\n"
                . 'Specify directory where file with Kernel class for your application is located with `app_path` parameter.'
            );
        }

        $kernelClass = $this->config['kernel_class'];
        $filesRealPath = array_map(static function ($file) {
            require_once $file;
            return $file->getRealPath();
        }, $results);

        if (class_exists($kernelClass)) {
            $reflectionClass = new ReflectionClass($kernelClass);
            if (in_array($reflectionClass->getFileName(), $filesRealPath, true)) {
                return $kernelClass;
            }
        }

        throw new ModuleRequireException(
            self::class,
            "Kernel class was not found.\n"
            . 'Specify directory where file with Kernel class for your application is located with `kernel_class` parameter.'
        );
    }

    protected function getProfile(): ?Profile
    {
        /** @var Profiler $profiler */
        $profiler = $this->getService('profiler');
        try {
            return $profiler?->loadProfileFromResponse($this->getClient()->getResponse());
        } catch (BadMethodCallException) {
            $this->fail('You must perform a request before using this method.');
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        return null;
    }

    /**
     * Grabs a Symfony Data Collector
     */
    protected function grabCollector(string $collector, string $function, ?string $message = null): DataCollectorInterface
    {
        $profile = $this->getProfile();
        if ($profile === null) {
            $this->fail(sprintf("The Profile is needed to use the '%s' function.", $function));
        }
        if (!$profile->hasCollector($collector)) {
            $this->fail($message ?: "The '{$collector}' collector is needed to use the '{$function}' function.");
        }

        return $profile->getCollector($collector);
    }

    /**
     * Set the data that will be displayed when running a test with the `--debug` flag
     *
     * @param mixed $url
     */
    protected function debugResponse($url): void
    {
        parent::debugResponse($url);
        if ($profile = $this->getProfile()) {
            $collectors = [
                'security' => 'debugSecurityData',
                'mailer'   => 'debugMailerData',
                'time'     => 'debugTimeData',
            ];
            foreach ($collectors as $collector => $method) {
                if ($profile->hasCollector($collector)) {
                    $this->$method($profile->getCollector($collector));
                }
            }
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

        foreach ($routes as $route) {
            if ($route->getHost() !== null) {
                $compiledRoute = $route->compile();
                if ($compiledRoute->getHostRegex() !== null) {
                    $internalDomains[] = $compiledRoute->getHostRegex();
                }
            }
        }

        return array_unique($internalDomains);
    }

    private function setXdebugMaxNestingLevel(int $maxNestingLevel): void
    {
        if (ini_get('xdebug.max_nesting_level') < $maxNestingLevel) {
            ini_set('xdebug.max_nesting_level', (string)$maxNestingLevel);
        }
    }

    private function bootstrapEnvironment(): void
    {
        $bootstrapFile = $this->kernel->getProjectDir() . '/tests/bootstrap.php';
        if (file_exists($bootstrapFile)) {
            require_once $bootstrapFile;
        } else {
            if (!method_exists(Dotenv::class, 'bootEnv')) {
                throw new LogicException(
                    "Symfony DotEnv is missing. Try running 'composer require symfony/dotenv'\n" .
                    "If you can't install DotEnv add your env files to the 'params' key in codeception.yml\n" .
                    "or update your symfony/framework-bundle recipe by running:\n" .
                    'composer recipes:install symfony/framework-bundle --force'
                );
            }
            $_ENV['APP_ENV'] = $this->config['environment'];
            (new Dotenv())->bootEnv('.env');
        }
    }

    private function debugSecurityData(SecurityDataCollector $security): void
    {
        if ($security->isAuthenticated()) {
            $roles = $security->getRoles();
            $rolesString = implode(',', $roles instanceof Data ? $roles->getValue() : $roles);
            $userInfo = $security->getUser() . ' [' . $rolesString . ']';
        } else {
            $userInfo = 'Anonymous';
        }
        $this->debugSection('User', $userInfo);
    }

    private function debugMailerData(MessageDataCollector $mailerCollector): void
    {
        $this->debugSection('Emails', count($mailerCollector->getEvents()->getMessages()) . ' sent');
    }

    private function debugTimeData(TimeDataCollector $timeCollector): void
    {
        $this->debugSection('Time', number_format($timeCollector->getDuration(), 2) . ' ms');
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
