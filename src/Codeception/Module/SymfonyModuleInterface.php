<?php

namespace Codeception\Module;

use Codeception\Lib\Interfaces\DoctrineProvider;
use Codeception\Lib\Interfaces\PartedModule;
use Codeception\TestInterface;
use Symfony\Component\Security\Core\User\UserInterface;

interface SymfonyModuleInterface extends DoctrineProvider, PartedModule
{
    public function _after(TestInterface $test);

    public function _before(TestInterface $test);

    public function _getContainer();

    public function _getEntityManager();

    public function _initialize();

    public function _parts();

    public function amOnRoute($routeName, array $params = []);

    public function dontSeeEmailIsSent();

    public function grabService($service);

    public function invalidateCachedRouter();

    public function amLoggedInAs(UserInterface $user, $firewallName = 'main', $firewallContext = null);

    public function onReconfigure($settings = []);

    public function persistService($serviceName, $isPermanent = false);

    public function rebootClientKernel();

    public function runSymfonyConsoleCommand($command, $parameters = [], $consoleInputs = [], $expectedExitCode = 0);

    public function seeCurrentRouteIs($routeName, array $params = []);

    public function seeEmailIsSent($expectedCount = null);

    public function seeInCurrentRoute($routeName);

    public function unpersistService($serviceName);
}