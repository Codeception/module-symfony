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
use Codeception\TestInterface;
use Exception;
use ReflectionClass;
use ReflectionException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\Extension\DataCollector\FormDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\EventDataCollector;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;
use Symfony\Component\VarDumper\Cloner\Data;
use function array_intersect_assoc;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_search;
use function array_unique;
use function class_exists;
use function codecept_root_dir;
use function count;
use function explode;
use function file_exists;
use function get_class;
use function implode;
use function in_array;
use function ini_get;
use function ini_set;
use function interface_exists;
use function is_array;
use function is_int;
use function is_null;
use function is_object;
use function is_string;
use function is_subclass_of;
use function iterator_to_array;
use function json_encode;
use function serialize;
use function sprintf;
use function strlen;
use function strpos;
use function substr_compare;

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
 * * mailer: 'symfony_mailer' - choose the mailer used by your application
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
 * * mailer: 'swiftmailer' - choose the mailer used by your application
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
 * * `services`: Symfony dependency injection container (DIC)
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
    private const SWIFTMAILER = 'swiftmailer';

    private const SYMFONY_MAILER = 'symfony_mailer';

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
        'mailer' => self::SWIFTMAILER,
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

            if ($this->_getContainer()->has('doctrine')) {
                $this->persistPermanentService('doctrine');
            }
            if ($this->_getContainer()->has('doctrine.orm.default_entity_manager')) {
                $this->persistPermanentService('doctrine.orm.default_entity_manager');
            }
            if ($this->_getContainer()->has('doctrine.dbal.backend_connection')) {
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
     * Get service $serviceName and add it to the lists of persistent services.
     *
     * @param string $serviceName
     */
    public function persistService(string $serviceName): void
    {
        $service = $this->grabService($serviceName);
        $this->persistentServices[$serviceName] = $service;
        if ($this->client instanceof SymfonyConnector) {
            $this->client->persistentServices[$serviceName] = $service;
        }
    }

    /**
     * Get service $serviceName and add it to the lists of persistent services,
     * making that service persistent between tests.
     *
     * @param string $serviceName
     */
    public function persistPermanentService(string $serviceName): void
    {
        $service = $this->grabService($serviceName);
        $this->persistentServices[$serviceName] = $service;
        $this->permanentServices[$serviceName] = $service;
        if ($this->client instanceof SymfonyConnector) {
            $this->client->persistentServices[$serviceName] = $service;
        }
    }

    /**
     * Remove service $serviceName from the lists of persistent services.
     *
     * @param string $serviceName
     */
    public function unpersistService(string $serviceName): void
    {
        if (isset($this->persistentServices[$serviceName])) {
            unset($this->persistentServices[$serviceName]);
        }
        if (isset($this->permanentServices[$serviceName])) {
            unset($this->permanentServices[$serviceName]);
        }
        if ($this->client instanceof SymfonyConnector && isset($this->client->persistentServices[$serviceName])) {
            unset($this->client->persistentServices[$serviceName]);
        }
    }

    /**
     * Invalidate previously cached routes.
     */
    public function invalidateCachedRouter(): void
    {
        $this->unpersistService('router');
    }

    /**
     * Opens web page using route name and parameters.
     *
     * ``` php
     * <?php
     * $I->amOnRoute('posts.create');
     * $I->amOnRoute('posts.show', array('id' => 34));
     * ```
     *
     * @param string $routeName
     * @param array $params
     */
    public function amOnRoute(string $routeName, array $params = []): void
    {
        /** @var RouterInterface $router */
        $router = $this->grabService('router');
        if ($router->getRouteCollection()->get($routeName) === null) {
            $this->fail(sprintf('Route with name "%s" does not exists.', $routeName));
        }
        $url = $router->generate($routeName, $params);
        $this->amOnPage($url);
    }

    /**
     * Checks that current url matches route.
     *
     * ``` php
     * <?php
     * $I->seeCurrentRouteIs('posts.index');
     * $I->seeCurrentRouteIs('posts.show', array('id' => 8));
     * ```
     *
     * @param string $routeName
     * @param array $params
     */
    public function seeCurrentRouteIs(string $routeName, array $params = []): void
    {
        /** @var RouterInterface $router */
        $router = $this->grabService('router');
        if ($router->getRouteCollection()->get($routeName) === null) {
            $this->fail(sprintf('Route with name "%s" does not exists.', $routeName));
        }

        $uri = explode('?', $this->grabFromCurrentUrl())[0];
        try {
            $match = $router->match($uri);
        } catch (ResourceNotFoundException $e) {
            $this->fail(sprintf('The "%s" url does not match with any route', $uri));
        }
        $expected = array_merge(['_route' => $routeName], $params);
        $intersection = array_intersect_assoc($expected, $match);

        $this->assertEquals($expected, $intersection);
    }

    /**
     * Checks that current url matches route.
     * Unlike seeCurrentRouteIs, this can matches without exact route parameters
     *
     * ``` php
     * <?php
     * $I->seeInCurrentRoute('my_blog_pages');
     * ```
     *
     * @param string $routeName
     */
    public function seeInCurrentRoute(string $routeName): void
    {
        /** @var RouterInterface $router */
        $router = $this->grabService('router');
        if ($router->getRouteCollection()->get($routeName) === null) {
            $this->fail(sprintf('Route with name "%s" does not exists.', $routeName));
        }

        $uri = explode('?', $this->grabFromCurrentUrl())[0];
        try {
            $matchedRouteName = $router->match($uri)['_route'];
        } catch (ResourceNotFoundException $e) {
            $this->fail(sprintf('The "%s" url does not match with any route', $uri));
        }

        $this->assertEquals($matchedRouteName, $routeName);
    }

    /**
     * Goes to a page and check that it can be accessed.
     *
     * ```php
     * <?php
     * $I->seePageIsAvailable('/dashboard');
     * ```
     *
     * @param string $url
     */
    public function seePageIsAvailable(string $url): void
    {
        $this->amOnPage($url);
        $this->seeResponseCodeIsSuccessful();
        $this->seeInCurrentUrl($url);
    }

    /**
     * Goes to a page and check that it redirects to another.
     *
     * ```php
     * <?php
     * $I->seePageRedirectsTo('/admin', '/login');
     * ```
     *
     * @param string $page
     * @param string $redirectsTo
     */
    public function seePageRedirectsTo(string $page, string $redirectsTo): void
    {
        $this->client->followRedirects(false);
        $this->amOnPage($page);
        /** @var Response $response */
        $response = $this->client->getResponse();
        $this->assertTrue(
            $response->isRedirection()
        );
        $this->client->followRedirect();
        $this->seeInCurrentUrl($redirectsTo);
    }

    /**
     * Checks if the desired number of emails was sent.
     * If no argument is provided then at least one email must be sent to satisfy the check.
     * The email is checked using Symfony's profiler, which means:
     * * If your app performs a redirect after sending the email, you need to suppress this using REST Module's [stopFollowingRedirects](https://codeception.com/docs/modules/REST#stopFollowingRedirects)
     * * If the email is sent by a Symfony Console Command, Codeception cannot detect it yet.
     *
     * ``` php
     * <?php
     * $I->seeEmailIsSent(2);
     * ```
     *
     * @param int|null $expectedCount
     */
    public function seeEmailIsSent(?int $expectedCount = null): void
    {
        $realCount = 0;
        $mailer = $this->config['mailer'];
        if ($mailer === self::SWIFTMAILER) {
            $mailCollector = $this->grabCollector('swiftmailer', __FUNCTION__);
            $realCount = $mailCollector->getMessageCount();
        } elseif ($mailer === self::SYMFONY_MAILER) {
            $mailCollector = $this->grabCollector('mailer', __FUNCTION__);
            $realCount = count($mailCollector->getEvents()->getMessages());
        } else {
            $this->fail(
                "Emails can't be tested without Mailer service connector.
                Set your mailer service in `functional.suite.yml`: `mailer: swiftmailer`
                (Or `mailer: symfony_mailer` for Symfony Mailer)."
            );
        }

        if ($expectedCount !== null) {
            $this->assertEquals($expectedCount, $realCount, sprintf(
                'Expected number of sent emails was %d, but in reality %d %s sent.',
                $expectedCount, $realCount, $realCount === 1 ? 'was' : 'were'
            ));
            return;
        }
        $this->assertGreaterThan(0, $realCount);
    }

    /**
     * Checks that no email was sent. This is an alias for seeEmailIsSent(0).
     *
     * @part email
     */
    public function dontSeeEmailIsSent(): void
    {
        $this->seeEmailIsSent(0);
    }

    /**
     * Grabs a service from the Symfony dependency injection container (DIC).
     * In "test" environment, Symfony uses a special `test.service_container`, see https://symfony.com/doc/current/testing.html#accessing-the-container
     * Services that aren't injected somewhere into your app, need to be defined as `public` to be accessible by Codeception.
     *
     * ``` php
     * <?php
     * $em = $I->grabService('doctrine');
     * ```
     *
     * @param string $service
     * @return mixed
     * @part services
     */
    public function grabService(string $service)
    {
        $container = $this->_getContainer();
        if (!$container->has($service)) {
            $this->fail("Service $service is not available in container.
            If the service isn't injected anywhere in your app, you need to set it to `public` in your `config/services_test.php`/`.yaml`,
            see https://symfony.com/doc/current/testing.html#accessing-the-container");
        }
        return $container->get($service);
    }

    /**
     * Run Symfony console command, grab response and return as string.
     * Recommended to use for integration or functional testing.
     *
     * ``` php
     * <?php
     * $result = $I->runSymfonyConsoleCommand('hello:world', ['arg' => 'argValue', 'opt1' => 'optValue'], ['input']);
     * ```
     *
     * @param string $command          The console command to execute
     * @param array  $parameters       Parameters (arguments and options) to pass to the command
     * @param array  $consoleInputs    Console inputs (e.g. used for interactive questions)
     * @param int    $expectedExitCode The expected exit code of the command
     *
     * @return string Returns the console output of the command
     */
    public function runSymfonyConsoleCommand(string $command, array $parameters = [], array $consoleInputs = [], int $expectedExitCode = 0): string
    {
        $kernel = $this->grabService('kernel');
        $application = new Application($kernel);
        $consoleCommand = $application->find($command);
        $commandTester = new CommandTester($consoleCommand);
        $commandTester->setInputs($consoleInputs);

        $parameters = ['command' => $command] + $parameters;
        $exitCode = $commandTester->execute($parameters);
        $output = $commandTester->getDisplay();

        $this->assertEquals(
            $expectedExitCode,
            $exitCode,
            'Command did not exit with code '.$expectedExitCode
            .' but with '.$exitCode.': '.$output
        );

        return $output;
    }

    /**
     * @return Profile|null
     */
    protected function getProfile(): ?Profile
    {
        $container = $this->_getContainer();
        if (!$container->has('profiler')) {
            return null;
        }

        /** @var Profiler $profiler */
        $profiler = $this->grabService('profiler');
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
        if ($profile->hasCollector('swiftmailer')) {
            $emails = $profile->getCollector('swiftmailer')->getMessageCount();
        } elseif ($profile->hasCollector('mailer')) {
            $emails = count($profile->getCollector('mailer')->getEvents()->getMessages());
        }
        if (isset($emails)) {
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

        /** @var RouterInterface $router */
        $router = $this->grabService('router');
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
     * Reboot client's kernel.
     * Can be used to manually reboot kernel when 'rebootable_client' => false
     *
     * ``` php
     * <?php
     *
     * // Perform some requests
     *
     * $I->rebootClientKernel();
     *
     * // Perform other requests
     *
     * ```
     *
     */
    public function rebootClientKernel(): void
    {
        if ($this->client instanceof SymfonyConnector) {
            $this->client->rebootKernel();
        }
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

    /**
     * Checks that number of given records were found in database.
     * 'id' is the default search parameter.
     *
     * ```php
     * <?php
     * $I->seeNumRecords(1, User::class, ['name' => 'davert']);
     * $I->seeNumRecords(80, User::class);
     * ```
     *
     * @param int $expectedNum Expected number of records
     * @param string $className A doctrine entity
     * @param array $criteria Optional query criteria
     */
    public function seeNumRecords(int $expectedNum, string $className, array $criteria = []): void
    {
        $currentNum = $this->grabNumRecords($className, $criteria);

        $this->assertEquals(
            $expectedNum,
            $currentNum,
            sprintf(
                'The number of found %s (%d) does not match expected number %d with %s',
                $className, $currentNum, $expectedNum, json_encode($criteria)
            )
        );
    }

    /**
     * Retrieves number of records from database
     * 'id' is the default search parameter.
     *
     * ```php
     * <?php
     * $I->grabNumRecords('User::class', ['name' => 'davert']);
     * ```
     *
     * @param string $entityClass  The entity class
     * @param array $criteria      Optional query criteria
     * @return int
     */
    public function grabNumRecords(string $entityClass, array $criteria = []): int
    {
        $em         = $this->_getEntityManager();
        $repository = $em->getRepository($entityClass);

        if (empty($criteria)) {
            return (int)$repository->createQueryBuilder('a')
                ->select('count(a.id)')
                ->getQuery()
                ->getSingleScalarResult();
        }
        return $repository->count($criteria);
    }

    /**
     * Invalidate the current session.
     * ```php
     * <?php
     * $I->logout();
     * ```
     */
    public function logout(): void
    {
        $container = $this->_getContainer();
        if ($container->has('security.token_storage')) {
            /** @var TokenStorageInterface $tokenStorage */
            $tokenStorage = $this->grabService('security.token_storage');
            $tokenStorage->setToken();
        }

        /** @var SessionInterface $session */
        $session = $this->grabService('session');

        $sessionName = $session->getName();
        $session->invalidate();

        $cookieJar = $this->client->getCookieJar();
        foreach ($cookieJar->all() as $cookie) {
            $cookieName = $cookie->getName();
            if ($cookieName === 'MOCKSESSID' ||
                $cookieName === 'REMEMBERME' ||
                $cookieName === $sessionName
            ) {
                $cookieJar->expire($cookieName);
            }
        }
        $cookieJar->flushExpiredCookies();
    }

    /**
     * Assert that the session has a given list of values.
     *
     * ``` php
     * <?php
     * $I->seeSessionHasValues(['key1', 'key2']);
     * $I->seeSessionHasValues(['key1' => 'value1', 'key2' => 'value2']);
     * ```
     *
     * @param  array $bindings
     */
    public function seeSessionHasValues(array $bindings): void
    {
        foreach ($bindings as $key => $value) {
            if (is_int($key)) {
                $this->seeInSession($value);
            } else {
                $this->seeInSession($key, $value);
            }
        }
    }

    /**
     * Assert that a session attribute exists.
     *
     * ```php
     * <?php
     * $I->seeInSession('attribute');
     * $I->seeInSession('attribute', 'value');
     * ```
     *
     * @param string $attribute
     * @param mixed|null $value
     */

    public function seeInSession(string $attribute, $value = null): void
    {
        /** @var SessionInterface $session */
        $session = $this->grabService('session');

        if (!$session->has($attribute)) {
            $this->fail("No session attribute with name '$attribute'");
        }

        if (null !== $value) {
            $this->assertEquals($value, $session->get($attribute));
        }
    }

    /**
     * Assert that a session attribute does not exist, or is not equal to the passed value.
     *
     * ```php
     * <?php
     * $I->dontSeeInSession('attribute');
     * $I->dontSeeInSession('attribute', 'value');
     * ```
     *
     * @param string $attribute
     * @param mixed|null $value
     */
    public function dontSeeInSession(string $attribute, $value = null): void
    {
        /** @var SessionInterface $session */
        $session = $this->grabService('session');

        if (null === $value) {
            if ($session->has($attribute)) {
                $this->fail("Session attribute with name '$attribute' does exist");
            }
        }
        else {
            $this->assertNotEquals($value, $session->get($attribute));
        }
    }

    /**
     * Opens web page by action name
     *
     * ``` php
     * <?php
     * $I->amOnAction('PostController::index');
     * $I->amOnAction('HomeController');
     * $I->amOnAction('ArticleController', ['slug' => 'lorem-ipsum']);
     * ```
     *
     * @param string $action
     * @param array $params
     */
    public function amOnAction(string $action, array $params = []): void
    {
        /** @var RouterInterface $router */
        $router = $this->grabService('router');

        $routes = $router->getRouteCollection()->getIterator();

        foreach ($routes as $route) {
            $controller = $route->getDefault('_controller');
            if (substr_compare($controller, $action, -strlen($action)) === 0) {
                $resource = $router->match($route->getPath());
                $url      = $router->generate(
                    $resource['_route'],
                    $params,
                    UrlGeneratorInterface::ABSOLUTE_PATH
                );
                $this->amOnPage($url);
                return;
            }
        }
    }

    /**
     * Checks that a user is authenticated.
     *
     * ```php
     * <?php
     * $I->seeAuthentication();
     * ```
     */
    public function seeAuthentication(): void
    {
        /** @var Security $security */
        $security = $this->grabService('security.helper');

        $user = $security->getUser();

        if ($user === null) {
            $this->fail('There is no user in session');
        }

        $this->assertTrue(
            $security->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY),
            'There is no authenticated user'
        );
    }

    /**
     * Submit a form specifying the form name only once.
     *
     * Use this function instead of $I->submitForm() to avoid repeating the form name in the field selectors.
     * If you customized the names of the field selectors use $I->submitForm() for full control.
     *
     * ```php
     * <?php
     * $I->submitSymfonyForm('login_form', [
     *     '[email]'    => 'john_doe@gmail.com',
     *     '[password]' => 'secretForest'
     * ]);
     * ```
     *
     * @param string $name
     * @param string[] $fields
     */
    public function submitSymfonyForm(string $name, array $fields): void
    {
        $selector = sprintf('form[name=%s]', $name);

        $params = [];
        foreach ($fields as $key => $value) {
            $fixedKey = sprintf('%s%s', $name, $key);
            $params[$fixedKey] = $value;
        }
        $button = sprintf('%s_submit', $name);

        $this->submitForm($selector, $params, $button);
    }

    /**
     * Checks that a user is authenticated with the 'remember me' option.
     *
     * ```php
     * <?php
     * $I->seeRememberedAuthentication();
     * ```
     */
    public function seeRememberedAuthentication(): void
    {
        /** @var Security $security */
        $security = $this->grabService('security.helper');

        $user = $security->getUser();

        if ($user === null) {
            $this->fail('There is no user in session');
        }

        $hasRememberMeCookie = $this->client->getCookieJar()->get('REMEMBERME');
        $hasRememberMeRole = $security->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_REMEMBERED);

        $isRemembered = $hasRememberMeCookie && $hasRememberMeRole;
        $this->assertTrue(
            $isRemembered,
            'User does not have remembered authentication'
        );
    }

    /**
     * Check that user is not authenticated with the 'remember me' option.
     *
     * ```php
     * <?php
     * $I->dontSeeRememberedAuthentication();
     * ```
     */
    public function dontSeeRememberedAuthentication(): void
    {
        /** @var Security $security */
        $security = $this->grabService('security.helper');

        $hasRememberMeCookie = $this->client->getCookieJar()->get('REMEMBERME');
        $hasRememberMeRole = $security->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_REMEMBERED);

        $isRemembered = $hasRememberMeCookie && $hasRememberMeRole;
        $this->assertFalse(
            $isRemembered,
            'User does have remembered authentication'
        );
    }

    /**
     * Verifies that the current user has multiple roles
     *
     * ``` php
     * <?php
     * $I->seeUserHasRoles(['ROLE_USER', 'ROLE_ADMIN']);
     * ```
     *
     * @param string[] $roles
     */
    public function seeUserHasRoles(array $roles): void
    {
        foreach ($roles as $role) {
            $this->seeUserHasRole($role);
        }
    }

    /**
     * Check that the current user has a role
     *
     * ```php
     * <?php
     * $I->seeUserHasRole('ROLE_ADMIN');
     * ```
     *
     * @param string $role
     */
    public function seeUserHasRole(string $role): void
    {
        /** @var Security $security */
        $security = $this->grabService('security.helper');

        $user = $security->getUser();

        if ($user === null) {
            $this->fail('There is no user in session');
        }

        $this->assertTrue(
            $security->isGranted($role),
            sprintf(
                'User %s has no role %s',
                $user->getUsername(),
                $role
            )
        );
    }

    /**
     * Check that user is not authenticated.
     *
     * ```php
     * <?php
     * $I->dontSeeAuthentication();
     * ```
     */
    public function dontSeeAuthentication(): void
    {
        /** @var Security $security */
        $security = $this->grabService('security.helper');

        $this->assertFalse(
            $security->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY),
            'There is an user authenticated'
        );
    }

    /**
     * Grabs a Symfony parameter
     *
     * ```php
     * <?php
     * $I->grabParameter('app.business_name');
     * ```
     *
     * @param string $name
     * @return mixed|null
     */
    public function grabParameter(string $name)
    {
        /** @var ParameterBagInterface $parameterBag */
        $parameterBag = $this->grabService('parameter_bag');
        return $parameterBag->get($name);
    }

    /**
     * Make sure events fired during the test.
     *
     * ``` php
     * <?php
     * $I->seeEventTriggered('App\MyEvent');
     * $I->seeEventTriggered(new App\Events\MyEvent());
     * $I->seeEventTriggered(['App\MyEvent', 'App\MyOtherEvent']);
     * ```
     * @param string|object|string[] $expected
     */
    public function seeEventTriggered($expected): void
    {
        /** @var EventDataCollector $eventCollector */
        $eventCollector = $this->grabCollector('events', __FUNCTION__);

        /** @var Data $data */
        $data = $eventCollector->getCalledListeners();

        if ($data->count() === 0) {
            $this->fail('No event was triggered');
        }

        $actual = $data->getValue(true);
        $expected = is_array($expected) ? $expected : [$expected];

        foreach ($expected as $expectedEvent) {
            $triggered = false;
            $expectedEvent = is_object($expectedEvent) ? get_class($expectedEvent) : $expectedEvent;

            foreach ($actual as $actualEvent) {
                if (strpos($actualEvent['pretty'], $expectedEvent) === 0) {
                    $triggered = true;
                }
            }
            $this->assertTrue($triggered, "The '$expectedEvent' event did not trigger");
        }
    }

    /**
     * Make sure events did not fire during the test.
     *
     * ``` php
     * <?php
     * $I->dontSeeEventTriggered('App\MyEvent');
     * $I->dontSeeEventTriggered(new App\Events\MyEvent());
     * $I->dontSeeEventTriggered(['App\MyEvent', 'App\MyOtherEvent']);
     * ```
     * @param string|object|string[] $expected
     */
    public function dontSeeEventTriggered($expected): void
    {
        /** @var EventDataCollector $eventCollector */
        $eventCollector = $this->grabCollector('events', __FUNCTION__);

        /** @var Data $data */
        $data = $eventCollector->getNotCalledListeners();

        $actual = $data->getValue(true);
        $expected = is_array($expected) ? $expected : [$expected];

        foreach ($expected as $expectedEvent) {
            $notTriggered = false;
            $expectedEvent = is_object($expectedEvent) ? get_class($expectedEvent) : $expectedEvent;

            foreach ($actual as $actualEvent) {
                if (strpos($actualEvent['pretty'], $expectedEvent) === 0) {
                    $notTriggered = true;
                }
            }
            $this->assertTrue($notTriggered, "The '$expectedEvent' event triggered");
        }
    }

    /**
     * Checks that current page matches action
     *
     * ``` php
     * <?php
     * $I->seeCurrentActionIs('PostController::index');
     * $I->seeCurrentActionIs('HomeController');
     * ```
     *
     * @param string $action
     */
    public function seeCurrentActionIs(string $action): void
    {
        /** @var RouterInterface $router */
        $router = $this->grabService('router');

        $routes = $router->getRouteCollection()->getIterator();

        foreach ($routes as $route) {
            $controller = $route->getDefault('_controller');
            if (substr_compare($controller, $action, -strlen($action)) === 0) {
                /** @var Request $request */
                $request = $this->client->getRequest();
                $currentActionFqcn = $request->attributes->get('_controller');

                $this->assertStringEndsWith($action, $currentActionFqcn, "Current action is '$currentActionFqcn'.");
                return;
            }
        }
        $this->fail("Action '$action' does not exist");
    }

    /**
     * Verifies that multiple fields on a form have errors.
     *
     * If you only specify the name of the fields, this method will
     * verify that the field contains at least one error of any type:
     *
     * ``` php
     * <?php
     * $I->seeFormErrorMessages(['telephone', 'address']);
     * ```
     *
     * If you want to specify the error messages, you can do so
     * by sending an associative array instead, with the key being
     * the name of the field and the error message the value.
     *
     * This method will validate that the expected error message
     * is contained in the actual error message, that is,
     * you can specify either the entire error message or just a part of it:
     *
     * ``` php
     * <?php
     * $I->seeFormErrorMessages([
     *     'address'   => 'The address is too long'
     *     'telephone' => 'too short', // the full error message is 'The telephone is too short'
     * ]);
     * ```
     *
     * If you don't want to specify the error message for some fields,
     * you can pass `null` as value instead of the message string,
     * or you can directly omit the value of that field. If that is the case,
     * it will be validated that that field has at least one error of any type:
     *
     * ``` php
     * <?php
     * $I->seeFormErrorMessages([
     *     'telephone' => 'too short',
     *     'address'   => null,
     *     'postal code',
     * ]);
     * ```
     *
     * @param string[] $expectedErrors
     */
    public function seeFormErrorMessages(array $expectedErrors): void
    {
        foreach ($expectedErrors as $field => $message) {
            if (is_int($field)) {
                $this->seeFormErrorMessage($message);
            } else {
                $this->seeFormErrorMessage($field, $message);
            }
        }
    }

    /**
     * Verifies that a form field has an error.
     * You can specify the expected error message as second parameter.
     *
     * ``` php
     * <?php
     * $I->seeFormErrorMessage('username');
     * $I->seeFormErrorMessage('username', 'Username is empty');
     * ```
     * @param string $field
     * @param string|null $message
     */
    public function seeFormErrorMessage(string $field, ?string $message = null): void
    {
        /** @var FormDataCollector $formCollector */
        $formCollector = $this->grabCollector('form', __FUNCTION__);

        if (!$forms = $formCollector->getData()->getValue('forms')['forms']) {
            $this->fail('There are no forms on the current page.');
        }

        $fields = [];
        $errors = [];

        foreach ($forms as $form) {
            foreach ($form['children'] as $child) {
                $fieldName = $child['name'];
                $fields[] = $fieldName;

                if (!array_key_exists('errors', $child)) {
                    continue;
                }
                foreach ($child['errors'] as $error) {
                    $errors[$fieldName] = $error['message'];
                }
            }
        }

        if (!in_array($field, $fields)) {
            $this->fail("the field '$field' does not exist in the form.");
        }

        if (!array_key_exists($field, $errors)) {
            $this->fail("No form error message for field '$field'.");
        }

        if (!$message) {
            return;
        }

        $this->assertStringContainsString(
            $message,
            $errors[$field],
            sprintf(
                "There is an error message for the field '%s', but it does not match the expected message.",
                $field
            )
        );
    }

    /**
     * Checks that the user's password would not benefit from rehashing.
     * If the user is not provided it is taken from the current session.
     *
     * You might use this function after performing tasks like registering a user or submitting a password update form.
     *
     * ```php
     * <?php
     * $I->seeUserPasswordDoesNotNeedRehash();
     * $I->seeUserPasswordDoesNotNeedRehash($user);
     * ```
     *
     * @param UserInterface|null $user
     */
    public function seeUserPasswordDoesNotNeedRehash(UserInterface $user = null): void
    {
        if ($user === null) {
            /** @var Security $security */
            $security = $this->grabService('security.helper');
            $user = $security->getUser();
            if ($user === null) {
                $this->fail('No user found to validate');
            }
        }
        $encoder = $this->grabService('security.user_password_encoder.generic');

        $this->assertFalse($encoder->needsRehash($user), 'User password needs rehash');
    }

    /**
     * Verifies that there are no errors bound to the submitted form.
     *
     * ``` php
     * <?php
     * $I->dontSeeFormErrors();
     * ```
     */
    public function dontSeeFormErrors(): void
    {
        /** @var FormDataCollector $formCollector */
        $formCollector = $this->grabCollector('form', __FUNCTION__);

        $this->assertEquals(
            0,
            $formCollector->getData()->offsetGet('nb_errors'),
            'Expecting that the form does not have errors, but there were!'
        );
    }

    /**
     * Login with the given user object.
     * The `$user` object must have a persistent identifier.
     * If you have more than one firewall or firewall context, you can specify the desired one as a parameter.
     *
     * ```php
     * <?php
     * $user = $I->grabEntityFromRepository(User::class, [
     *     'email' => 'john_doe@gmail.com'
     * ]);
     * $I->amLoggedInAs($user);
     * ```
     *
     * @param UserInterface $user
     * @param string $firewallName
     * @param null $firewallContext
     */
    public function amLoggedInAs(UserInterface $user, string $firewallName = 'main', $firewallContext = null): void
    {
        /** @var SessionInterface $session */
        $session = $this->grabService('session');

        if ($this->config['guard']) {
            $token = new PostAuthenticationGuardToken($user, $firewallName, $user->getRoles());
        } else {
            $token = new UsernamePasswordToken($user, null, $firewallName, $user->getRoles());
        }

        if ($firewallContext) {
            $session->set('_security_'.$firewallContext, serialize($token));
        } else {
            $session->set('_security_'.$firewallName, serialize($token));
        }

        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $this->client->getCookieJar()->set($cookie);
    }

    /**
     * Verifies that there are one or more errors bound to the submitted form.
     *
     * ``` php
     * <?php
     * $I->seeFormHasErrors();
     * ```
     */
    public function seeFormHasErrors(): void
    {
        /** @var FormDataCollector $formCollector */
        $formCollector = $this->grabCollector('form', __FUNCTION__);

        $this->assertGreaterThan(
            0,
            $formCollector->getData()->offsetGet('nb_errors'),
            'Expecting that the form has errors, but there were none!'
        );
    }

    /**
     * Grab a Doctrine entity repository.
     * Works with objects, entities, repositories, and repository interfaces.
     *
     * ```php
     * <?php
     * $I->grabRepository($user);
     * $I->grabRepository(User::class);
     * $I->grabRepository(UserRepository::class);
     * $I->grabRepository(UserRepositoryInterface::class);
     * ```
     *
     * @param object|string $mixed
     * @return \Doctrine\ORM\EntityRepository|null
     */
    public function grabRepository($mixed)
    {
        $entityRepoClass = '\Doctrine\ORM\EntityRepository';
        $isNotARepo = function () use ($mixed): void {
            $this->fail(
                sprintf("'%s' is not an entity repository", $mixed)
            );
        };
        $getRepo = function () use ($mixed, $entityRepoClass, $isNotARepo) {
            if (!$repo = $this->grabService($mixed)) return null;
            if (!$repo instanceof $entityRepoClass) {
                $isNotARepo();
                return null;
            }
            return $repo;
        };

        if (is_object($mixed)) {
            $mixed = get_class($mixed);
        }

        if (interface_exists($mixed)) {
            return $getRepo();
        }

        if (!is_string($mixed) || !class_exists($mixed) ) {
            $isNotARepo();
            return null;
        }

        if (is_subclass_of($mixed, $entityRepoClass)){
            return $getRepo();
        }

        $em = $this->_getEntityManager();
        if ($em->getMetadataFactory()->isTransient($mixed)) {
            $isNotARepo();
            return null;
        }

        return $em->getRepository($mixed);
    }
}
