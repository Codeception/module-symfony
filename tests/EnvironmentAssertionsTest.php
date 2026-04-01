<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\EnvironmentAssertionsTrait;
use Symfony\Component\HttpKernel\Kernel;
use Tests\Support\CodeceptTestCase;

use function array_key_exists;
use function putenv;
use function rtrim;
use function sprintf;

final class EnvironmentAssertionsTest extends CodeceptTestCase
{
    use EnvironmentAssertionsTrait;

    private bool $hadServerAppEnv;
    private bool $hadServerAppDebug;
    private bool $hadEnvAppEnv;
    private bool $hadEnvAppDebug;

    private mixed $serverAppEnvValue = null;
    private mixed $serverAppDebugValue = null;
    private mixed $envAppEnvValue = null;
    private mixed $envAppDebugValue = null;

    private string|false $processAppEnvValue = false;
    private string|false $processAppDebugValue = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hadServerAppEnv = array_key_exists('APP_ENV', $_SERVER);
        $this->hadServerAppDebug = array_key_exists('APP_DEBUG', $_SERVER);
        $this->hadEnvAppEnv = array_key_exists('APP_ENV', $_ENV);
        $this->hadEnvAppDebug = array_key_exists('APP_DEBUG', $_ENV);

        $this->serverAppEnvValue = $_SERVER['APP_ENV'] ?? null;
        $this->serverAppDebugValue = $_SERVER['APP_DEBUG'] ?? null;
        $this->envAppEnvValue = $_ENV['APP_ENV'] ?? null;
        $this->envAppDebugValue = $_ENV['APP_DEBUG'] ?? null;

        $this->processAppEnvValue = getenv('APP_ENV');
        $this->processAppDebugValue = getenv('APP_DEBUG');
    }

    protected function tearDown(): void
    {
        $this->restoreVariable($_SERVER, 'APP_ENV', $this->hadServerAppEnv, $this->serverAppEnvValue);
        $this->restoreVariable($_SERVER, 'APP_DEBUG', $this->hadServerAppDebug, $this->serverAppDebugValue);
        $this->restoreVariable($_ENV, 'APP_ENV', $this->hadEnvAppEnv, $this->envAppEnvValue);
        $this->restoreVariable($_ENV, 'APP_DEBUG', $this->hadEnvAppDebug, $this->envAppDebugValue);

        $this->restoreProcessVariable('APP_ENV', $this->processAppEnvValue);
        $this->restoreProcessVariable('APP_DEBUG', $this->processAppDebugValue);

        parent::tearDown();
    }

    public function testAssertSymfonyVersionAcceptsOneTwoAndThreeSegments(): void
    {
        $this->assertSymfonyVersion('>=', (string) Kernel::MAJOR_VERSION);
        $this->assertSymfonyVersion('>=', sprintf('%d.%d', Kernel::MAJOR_VERSION, Kernel::MINOR_VERSION));
        $this->assertSymfonyVersion('>=', sprintf('%d.%d.0', Kernel::MAJOR_VERSION, Kernel::MINOR_VERSION));
    }

    public function testSeeAppEnvAndDebugMatchKernelUsesServerValues(): void
    {
        $_SERVER['APP_ENV'] = $this->kernel->getEnvironment();
        $_SERVER['APP_DEBUG'] = $this->kernel->isDebug() ? '1' : '0';

        $_ENV['APP_ENV'] = 'mismatch-from-env';
        $_ENV['APP_DEBUG'] = $this->kernel->isDebug() ? '0' : '1';

        putenv('APP_ENV=mismatch-from-getenv');
        putenv(sprintf('APP_DEBUG=%s', $this->kernel->isDebug() ? '0' : '1'));

        $this->seeAppEnvAndDebugMatchKernel();
    }

    public function testSeeAppEnvAndDebugMatchKernelUsesEnvValuesWhenServerMissing(): void
    {
        unset($_SERVER['APP_ENV'], $_SERVER['APP_DEBUG']);

        $_ENV['APP_ENV'] = $this->kernel->getEnvironment();
        $_ENV['APP_DEBUG'] = $this->kernel->isDebug() ? '1' : '0';

        putenv('APP_ENV=mismatch-from-getenv');
        putenv(sprintf('APP_DEBUG=%s', $this->kernel->isDebug() ? '0' : '1'));

        $this->seeAppEnvAndDebugMatchKernel();
    }

    public function testSeeAppEnvAndDebugMatchKernelUsesGetenvValuesWhenServerAndEnvMissing(): void
    {
        unset($_SERVER['APP_ENV'], $_SERVER['APP_DEBUG'], $_ENV['APP_ENV'], $_ENV['APP_DEBUG']);

        putenv(sprintf('APP_ENV=%s', $this->kernel->getEnvironment()));
        putenv(sprintf('APP_DEBUG=%s', $this->kernel->isDebug() ? '1' : '0'));

        $this->seeAppEnvAndDebugMatchKernel();
    }

    public function testGetProjectDirUsesKernelProjectDirParameter(): void
    {
        $projectDir = $this->_getContainer()->getParameter('kernel.project_dir');
        $this->assertIsString($projectDir);

        $this->assertSame(rtrim($projectDir, '/\\') . '/', $this->getProjectDir());
    }

    private function restoreVariable(array &$variables, string $name, bool $exists, mixed $value): void
    {
        if ($exists) {
            $variables[$name] = $value;
            return;
        }

        unset($variables[$name]);
    }

    private function restoreProcessVariable(string $name, string|false $value): void
    {
        if ($value === false) {
            putenv($name);
            return;
        }

        putenv(sprintf('%s=%s', $name, $value));
    }
}
