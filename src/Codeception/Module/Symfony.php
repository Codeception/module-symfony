<?php

namespace Codeception\Module;

use Codeception\Configuration;
use Codeception\Exception\ModuleException;
use Codeception\Exception\ModuleRequireException;
use Codeception\Lib\Connector\Symfony as SymfonyConnector;
use Codeception\Lib\Framework;
use Codeception\Lib\Interfaces\DoctrineProvider;
use Codeception\Lib\Interfaces\PartedModule;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * This module uses Symfony Crawler and HttpKernel to emulate requests and test response.
 *
 * ## Demo Project
 *
 * <https://github.com/Codeception/symfony-demo>
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
    const SWIFTMAILER = 'swiftmailer';
    const SYMFONY_MAILER = 'symfony_mailer';

    private static $possibleKernelClasses = [
        'AppKernel', // Symfony Standard
        'App\Kernel', // Symfony Flex
    ];

    /**
     * @var \Symfony\Component\HttpKernel\Kernel
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
     * @return array
     */
    public function _parts()
    {
        return ['services'];
    }

    /**
     * @var
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

    public function _initialize()
    {

        $this->initializeSymfonyCache();
        $this->kernelClass = $this->getKernelClass();
        $maxNestingLevel = 200; // Symfony may have very long nesting level
        $xdebugMaxLevelKey = 'xdebug.max_nesting_level';
        if (ini_get($xdebugMaxLevelKey) < $maxNestingLevel) {
            ini_set($xdebugMaxLevelKey, $maxNestingLevel);
        }

        $this->kernel = new $this->kernelClass($this->config['environment'], $this->config['debug']);
        $this->kernel->boot();

        if ($this->config['cache_router'] === true) {
            $this->persistService('router', true);
        }
    }

    /**
     * Require Symfonys bootstrap.php.cache only for PHP Version < 7
     *
     * @throws ModuleRequireException
     */
    private function initializeSymfonyCache()
    {
        $cache = Configuration::projectDir() . $this->config['var_path'] . DIRECTORY_SEPARATOR . 'bootstrap.php.cache';
        if (PHP_VERSION_ID < 70000 && !file_exists($cache)) {
            throw new ModuleRequireException(
                __CLASS__,
                "Symfony bootstrap file not found in $cache\n \n" .
                "Please specify path to bootstrap file using `var_path` config option\n \n" .
                "If you are trying to load bootstrap from a Bundle provide path like:\n \n" .
                "modules:\n    enabled:\n" .
                "    - Symfony:\n" .
                "        var_path: '../../app'\n" .
                "        app_path: '../../app'"
            );
        }
        if (file_exists($cache)) {
            require_once $cache;
        }
    }

    /**
     * Initialize new client instance before each test
     */
    public function _before(\Codeception\TestInterface $test)
    {
        $this->persistentServices = array_merge($this->persistentServices, $this->permanentServices);
        $this->client = new SymfonyConnector($this->kernel, $this->persistentServices, $this->config['rebootable_client']);
    }

    /**
     * Update permanent services after each test
     */
    public function _after(\Codeception\TestInterface $test)
    {
        foreach ($this->permanentServices as $serviceName => $service) {
            $this->permanentServices[$serviceName] = $this->grabService($serviceName);
        }
        parent::_after($test);
    }

    protected function onReconfigure($settings = [])
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
            $this->persistService($this->config['em_service'], true);

            if ($this->_getContainer()->has('doctrine')) {
                $this->persistService('doctrine', true);
            }
            if ($this->_getContainer()->has('doctrine.orm.default_entity_manager')) {
                $this->persistService('doctrine.orm.default_entity_manager', true);
            }
            if ($this->_getContainer()->has('doctrine.dbal.backend_connection')) {
                $this->persistService('doctrine.dbal.backend_connection', true);
            }
        }
        return $this->permanentServices[$this->config['em_service']];
    }

    /**
     * Return container.
     *
     * @return ContainerInterface
     */
    public function _getContainer()
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
     */
    protected function getKernelClass()
    {
        $path = codecept_root_dir() . $this->config['app_path'];
        if (!file_exists(codecept_root_dir() . $this->config['app_path'])) {
            throw new ModuleRequireException(
                __CLASS__,
                "Can't load Kernel from $path.\n"
                . "Directory does not exists. Use `app_path` parameter to provide valid application path"
            );
        }

        $finder = new Finder();
        $finder->name('*Kernel.php')->depth('0')->in($path);
        $results = iterator_to_array($finder);
        if (!count($results)) {
            throw new ModuleRequireException(
                __CLASS__,
                "File with Kernel class was not found at $path. "
                . "Specify directory where file with Kernel class for your application is located with `app_path` parameter."
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
                $refClass = new \ReflectionClass($class);
                if ($file = array_search($refClass->getFileName(), $filesRealPath)) {
                    return $class;
                }
            }
        }

        throw new ModuleRequireException(
            __CLASS__,
            "Kernel class was not found in $file. "
            . "Specify directory where file with Kernel class for your application is located with `app_path` parameter."
        );
    }

    /**
     * Get service $serviceName and add it to the lists of persistent services.
     * If $isPermanent then service becomes persistent between tests
     *
     * @param string  $serviceName
     * @param boolean $isPermanent
     */
    public function persistService($serviceName, $isPermanent = false)
    {
        $service = $this->grabService($serviceName);
        $this->persistentServices[$serviceName] = $service;
        if ($isPermanent) {
            $this->permanentServices[$serviceName] = $service;
        }
        if ($this->client) {
            $this->client->persistentServices[$serviceName] = $service;
        }
    }

    /**
     * Remove service $serviceName from the lists of persistent services.
     *
     * @param string $serviceName
     */
    public function unpersistService($serviceName)
    {
        if (isset($this->persistentServices[$serviceName])) {
            unset($this->persistentServices[$serviceName]);
        }
        if (isset($this->permanentServices[$serviceName])) {
            unset($this->permanentServices[$serviceName]);
        }
        if ($this->client && isset($this->client->persistentServices[$serviceName])) {
            unset($this->client->persistentServices[$serviceName]);
        }
    }

    /**
     * Invalidate previously cached routes.
     */
    public function invalidateCachedRouter()
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
     * ?>
     * ```
     *
     * @param $routeName
     * @param array $params
     */
    public function amOnRoute($routeName, array $params = [])
    {
        $router = $this->grabService('router');
        if (!$router->getRouteCollection()->get($routeName)) {
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
     * ?>
     * ```
     *
     * @param $routeName
     * @param array $params
     */
    public function seeCurrentRouteIs($routeName, array $params = [])
    {
        $router = $this->grabService('router');
        if (!$router->getRouteCollection()->get($routeName)) {
            $this->fail(sprintf('Route with name "%s" does not exists.', $routeName));
        }

        $uri = explode('?', $this->grabFromCurrentUrl())[0];
        try {
            $match = $router->match($uri);
        } catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException $e) {
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
     * $I->seeCurrentRouteMatches('my_blog_pages');
     * ?>
     * ```
     *
     * @param $routeName
     */
    public function seeInCurrentRoute($routeName)
    {
        $router = $this->grabService('router');
        if (!$router->getRouteCollection()->get($routeName)) {
            $this->fail(sprintf('Route with name "%s" does not exists.', $routeName));
        }

        $uri = explode('?', $this->grabFromCurrentUrl())[0];
        try {
            $matchedRouteName = $router->match($uri)['_route'];
        } catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException $e) {
            $this->fail(sprintf('The "%s" url does not match with any route', $uri));
        }

        $this->assertEquals($matchedRouteName, $routeName);
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
    public function seeEmailIsSent($expectedCount = null)
    {
        if (!$profile = $this->getProfile()) {
            $this->fail("Emails can't be tested without Profiler");
            return;
        }

        $mailer = $this->config['mailer'];
        if ($mailer === self::SYMFONY_MAILER) {
            $mailer = 'mailer';
        }

        if (!$profile->hasCollector($mailer)) {
            $this->fail(
                "Emails can't be tested without Mailer service connector.
                Set your mailer service in `functional.suite.yml`: `mailer: swiftmailer`
                (Or `mailer: symfony_mailer` for Symfony Mailer)."
            );
            return;
        }

        if (!is_int($expectedCount) && !is_null($expectedCount)) {
            $this->fail(sprintf(
                'The required number of emails must be either an integer or null. "%s" was provided.',
                print_r($expectedCount, true)
            ));
            return;
        }

        $mailCollector = $profile->getCollector($mailer);
        if ($mailer === self::SWIFTMAILER) {
            $realCount = $mailCollector->getMessageCount();
        } else {
            $realCount = count($mailCollector->getEvents()->getMessages());
        }

        if ($expectedCount) {
            $this->assertEquals($expectedCount, $realCount, sprintf(
                'Expected number of sent emails was %d, but in reality %d %s sent.',
                $expectedCount, $realCount, $realCount === 2 ? 'was' : 'were'
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
    public function dontSeeEmailIsSent()
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
     * ?>
     * ```
     *
     * @param $service
     * @return mixed
     * @part services
     */
    public function grabService($service)
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
     * ?>
     * ```
     *
     * @param string $command          The console command to execute
     * @param array  $parameters       Parameters (arguments and options) to pass to the command
     * @param array  $consoleInputs    Console inputs (e.g. used for interactive questions)
     * @param int    $expectedExitCode The expected exit code of the command
     *
     * @return string Returns the console output of the command
     */
    public function runSymfonyConsoleCommand($command, $parameters = [], $consoleInputs = [], $expectedExitCode = 0)
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
     * @return \Symfony\Component\HttpKernel\Profiler\Profile
     */
    protected function getProfile()
    {
        $container = $this->_getContainer();
        if (!$container->has('profiler')) {
            return null;
        }

        $profiler = $this->grabService('profiler');
        try {
            $response = $this->client->getResponse();
            return $profiler->loadProfileFromResponse($response);
        } catch (\BadMethodCallException $e) {
            $this->fail('You must perform a request before using this method.');
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
        return null;
    }

    /**
     * @param $url
     */
    protected function debugResponse($url)
    {
        parent::debugResponse($url);

        if (!$profile = $this->getProfile()) {
            return;
        }

        if ($profile->hasCollector('security')) {
            $security = $profile->getCollector('security');
            if ($security->isAuthenticated()) {
                $roles = $security->getRoles();

                if ($roles instanceof Data) {
                    $roles = $this->extractRawRoles($roles);
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
        if ($profile->hasCollector('timer')) {
            $this->debugSection('Time', $profile->getCollector('timer')->getTime());
        }
    }

    /**
     * @param Data $data
     * @return array
     */
    private function extractRawRoles(Data $data)
    {
        if ($this->dataRevealsValue($data)) {
            $roles = $data->getValue();
        } else {
            $raw = $data->getRawData();
            $roles = isset($raw[1]) ? $raw[1] : [];
        }

        return $roles;
    }

    /**
     * Returns a list of recognized domain names.
     *
     * @return array
     */
    protected function getInternalDomains()
    {
        $internalDomains = [];

        $routes = $this->grabService('router')->getRouteCollection();
        /* @var \Symfony\Component\Routing\Route $route */
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
     * ...
     * perform some requests
     * ...
     * $I->rebootClientKernel();
     * ...
     * perform other requests
     * ...
     *
     * ?>
     * ```
     *
     */
    public function rebootClientKernel()
    {
        if ($this->client) {
            $this->client->rebootKernel();
        }
    }

    /**
     * Public API from Data changed from Symfony 3.2 to 3.3.
     *
     * @param \Symfony\Component\VarDumper\Cloner\Data $data
     *
     * @return bool
     */
    private function dataRevealsValue(Data $data)
    {
        return method_exists($data, 'getValue');
    }

    /**
     * Returns list of the possible kernel classes based on the module configuration
     *
     * @return array
     */
    private function getPossibleKernelClasses()
    {
        if (empty($this->config['kernel_class'])) {
            return self::$possibleKernelClasses;
        }

        if (!is_string($this->config['kernel_class'])) {
            throw new ModuleException(
                __CLASS__,
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
    public function seeNumRecords($expectedNum, $className, $criteria = [])
    {
        $em         = $this->_getEntityManager();
        $repository = $em->getRepository($className);

        if (empty($criteria)) {
            $currentNum = (int)$repository->createQueryBuilder('a')
                ->select('count(a.id)')
                ->getQuery()
                ->getSingleScalarResult();
        } else {
            $currentNum = $repository->count($criteria);
        }

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
     * Invalidate the current session.
     * ```php
     * <?php
     * $I->logout();
     * ```
     */
    public function logout()
    {
        $container = $this->_getContainer();

        if ($container->has('security.token_storage')) {
            $tokenStorage = $this->grabService('security.token_storage');
            $tokenStorage->setToken(null);
        }

        if (!$container->has('session')) {
            $this->fail("Symfony container doesn't have 'session' service");
        }
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
     * Assert that a session attribute exists.
     *
     * ```php
     * <?php
     * $I->seeInSession('attrib');
     * $I->seeInSession('attrib', 'value');
     * ```
     *
     * @param string $attrib
     * @param mixed|null $value
     * @return void
     */
    public function seeInSession($attrib, $value = null)
    {
        $container = $this->_getContainer();

        if (!$container->has('session')) {
            $this->fail("Symfony container doesn't have 'session' service");
        }

        $session = $this->grabService('session');

        if (!$session->has($attrib)) {
            $this->fail("No session attribute with name '$attrib'");
        }

        if (null !== $value) {
            $this->assertEquals($value, $session->get($attrib));
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
    public function amOnAction($action, $params = [])
    {
        $container = $this->_getContainer();

        if (!$container->has('router')) {
            $this->fail("Symfony container doesn't have 'router' service");
        }

        $router = $this->grabService('router');

        $routes = $router->getRouteCollection()->getIterator();

        foreach ($routes as $route) {
            $controller = basename($route->getDefault('_controller'));
            if ($controller === $action) {
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
     * You can check users logged in with the option 'remember me' passing true as parameter.
     *
     * ```php
     * <?php
     * $I->seeAuthentication();
     * $I->seeAuthentication(true);
     * ```
     *
     * @param bool $remembered
     */
    public function seeAuthentication($remembered = false)
    {
        $container = $this->_getContainer();

        if (!$container->has('security.helper')) {
            $this->fail("Symfony container doesn't have 'security.helper' service");
        }

        $security = $this->grabService('security.helper');

        $user = $security->getUser();

        if (!$user) {
            $this->fail('There is no user in session');
        }

        if ($remembered) {
            $role = 'IS_AUTHENTICATED_REMEMBERED';
        } else {
            $role = 'IS_AUTHENTICATED_FULLY';
        }

        $this->assertTrue($security->isGranted($role), 'There is no authenticated user');
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
    public function seeUserHasRole($role)
    {
        $container = $this->_getContainer();

        if (!$container->has('security.helper')) {
            $this->fail("Symfony container doesn't have 'security.helper' service");
        }

        $security = $this->grabService('security.helper');

        $user = $security->getUser();

        if (!$user) {
            $this->fail('There is no user in session');
        }

        $this->assertTrue(
            $security->isGranted($role),
            sprintf(
                "User %s has no role %s",
                $user->getUsername(),
                $role
            )
        );
    }

    /**
     * Check that user is not authenticated.
     * You can specify whether users logged in with the 'remember me' option should be ignored by passing 'false' as a parameter.
     *
     * ```php
     * <?php
     * $I->dontSeeAuthentication();
     * ```
     *
     * @param bool $remembered
     */
    public function dontSeeAuthentication($remembered = true)
    {
        $container = $this->_getContainer();

        if (!$container->has('security.helper')) {
            $this->fail("Symfony container doesn't have 'security.helper' service");
        }

        $security = $this->grabService('security.helper');

        if ($remembered) {
            $role = 'IS_AUTHENTICATED_REMEMBERED';
        } else {
            $role = 'IS_AUTHENTICATED_FULLY';
        }

        $this->assertFalse(
            $security->isGranted($role),
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
    public function grabParameter($name)
    {
        $container = $this->_getContainer();

        if (!$container->has('parameter_bag')) {
            $this->fail("Symfony container doesn't have 'parameter_bag' service");
            return null;
        }
        $parameterBag = $this->grabService('parameter_bag');
        return $parameterBag->get($name);
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
    public function seeCurrentActionIs($action)
    {
        $container = $this->_getContainer();

        if (!$container->has('router')) {
            $this->fail("Symfony container doesn't have 'router' service");
        }

        $router = $this->grabService('router');

        $routes = $router->getRouteCollection()->getIterator();

        foreach ($routes as $route) {
            $controller = basename($route->getDefault('_controller'));
            if ($controller === $action) {
                $request = $this->client->getRequest();
                $currentAction = basename($request->attributes->get('_controller'));

                $this->assertEquals($currentAction, $action, "Current action is '$currentAction'.");
                return;
            }
        }
        $this->fail("Action '$action' does not exist");
    }

    public function amLoggedInAs(UserInterface $user, $firewallName = 'main', $firewallContext = null)
    {
        $container = $this->_getContainer();
        if (!$container->has('session')) {
            $this->fail("Symfony container doesn't have 'session' service");
        }

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
        $isNotARepo = function () use ($mixed) {
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

        if (interface_exists($mixed)) {
            return $getRepo();
        }

        if (is_object($mixed)) {
            $mixed = get_class($mixed);
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
