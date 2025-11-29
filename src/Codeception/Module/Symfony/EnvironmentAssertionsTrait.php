<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Assert;
use Symfony\Component\HttpKernel\Kernel;

use function array_flip;
use function array_keys;
use function array_merge;
use function file_exists;
use function file_get_contents;
use function getenv;
use function implode;
use function in_array;
use function is_dir;
use function is_executable;
use function is_file;
use function is_readable;
use function is_writable;
use function preg_match_all;
use function sprintf;
use function strncasecmp;
use function strtolower;
use function version_compare;

trait EnvironmentAssertionsTrait
{
    /**
     * Asserts that the Kernel is running in the expected environment (e.g., 'test', 'dev').
     *
     * ```php
     * <?php
     * $I->seeKernelEnvironmentIs('test');
     * ```
     */
    public function seeKernelEnvironmentIs(string $expectedEnv): void
    {
        $currentEnv = $this->kernel->getEnvironment();
        $this->assertSame(
            $expectedEnv,
            $currentEnv,
            sprintf('Kernel is running in environment "%s" but expected "%s".', $currentEnv, $expectedEnv)
        );
    }

    /**
     * Asserts that the application's debug mode is enabled.
     *
     * ```php
     * <?php
     * $I->seeDebugModeEnabled();
     * ```
     */
    public function seeDebugModeEnabled(): void
    {
        $this->assertTrue($this->kernel->isDebug(), 'Debug mode is expected to be enabled, but it is not.');
    }

    /**
     * Asserts that the application's debug mode is disabled.
     *
     * ```php
     * <?php
     * $I->dontSeeDebugModeEnabled();
     * ```
     */
    public function dontSeeDebugModeEnabled(): void
    {
        $this->assertFalse($this->kernel->isDebug(), 'Debug mode is expected to be disabled, but it is enabled.');
    }

    /**
     * Asserts that the current Symfony version satisfies the given comparison.
     *
     * ```php
     * <?php
     * $I->assertSymfonyVersion('>=', '6.4');
     * ```
     */
    public function assertSymfonyVersion(string $operator, string $version, string $message = ''): void
    {
        $this->assertTrue(
            version_compare(Kernel::VERSION, $version, $operator),
            $message ?: sprintf('Symfony version %s does not satisfy the constraint: %s %s', Kernel::VERSION, $operator, $version)
        );
    }

    /**
     * Asserts that `APP_ENV` and `APP_DEBUG` env vars match the Kernel state.
     *
     * ```php
     * <?php
     * $I->seeAppEnvAndDebugMatchKernel();
     * ```
     */
    public function seeAppEnvAndDebugMatchKernel(): void
    {
        $appEnv = getenv('APP_ENV');
        $appDebug = getenv('APP_DEBUG');

        if ($appEnv !== false) {
            $this->assertSame(
                $this->kernel->getEnvironment(),
                (string) $appEnv,
                sprintf('APP_ENV (%s) differs from Kernel environment (%s).', $appEnv, $this->kernel->getEnvironment())
            );
        }

        if ($appDebug !== false) {
            $expected = $this->kernel->isDebug();
            $normalized = in_array(strtolower((string) $appDebug), ['1', 'true', 'yes', 'on'], true);
            $this->assertSame(
                $expected,
                $normalized,
                sprintf('APP_DEBUG (%s) differs from Kernel debug (%s).', $appDebug, $expected ? 'true' : 'false')
            );
        }
    }

    /**
     * Asserts that the application's cache directory is writable.
     *
     * ```php
     * <?php
     * $I->seeAppCacheIsWritable();
     * ```
     */
    public function seeAppCacheIsWritable(): void
    {
        $cacheDir = $this->kernel->getCacheDir();
        $this->assertTrue(
            is_writable($cacheDir),
            sprintf('Symfony cache directory is not writable: %s', $cacheDir)
        );
    }

    /**
     * Asserts that the application's log directory is writable.
     *
     * ```php
     * <?php
     * $I->seeAppLogIsWritable();
     * ```
     */
    public function seeAppLogIsWritable(): void
    {
        $container = $this->_getContainer();
        if ($container->hasParameter('kernel.logs_dir')) {
            $value = $container->getParameter('kernel.logs_dir');
            Assert::assertIsString($value);
            /** @var string $logDir */
            $logDir = $value;
        } else {
            $logDir = $this->kernel->getLogDir();
        }

        $this->assertTrue(
            is_writable($logDir),
            sprintf('Symfony log directory is not writable: %s', $logDir)
        );
    }

    /**
     * Asserts that the minimal Symfony project structure exists and is usable.
     *
     * ```php
     * <?php
     * $I->seeProjectStructureIsSane();
     * ```
     */
    public function seeProjectStructureIsSane(): void
    {
        $root = $this->getProjectDir();
        foreach (['config', 'src', 'public', 'var'] as $dir) {
            $this->assertTrue(is_dir($root . $dir), sprintf('Directory "%s" is missing.', $dir));
        }

        foreach (['var/cache', 'var/log'] as $dir) {
            $this->assertTrue(is_dir($root . $dir), sprintf('Directory "%s" is missing.', $dir));
            $this->assertTrue(is_writable($root . $dir), sprintf('Directory "%s" is not writable.', $dir));
        }

        $this->assertFileExists($root . 'config/bundles.php', 'Missing config/bundles.php file.');

        $bin = $root . 'bin/console';
        $this->assertTrue(is_file($bin), 'bin/console is missing.');
        if (strncasecmp(PHP_OS, 'WIN', 3) !== 0) {
            $this->assertTrue(is_executable($bin), 'bin/console is not executable.');
        }
    }

    /**
     * Asserts that all keys in example env file(s) exist either in the provided env file(s) OR as OS env vars.
     * This validates presence only, not values. It also considers common local/test files if present.
     *
     * ```php
     * <?php
     * $I->assertEnvFileIsSynchronized();
     * ```
     *
     * @param non-empty-string $envPath
     * @param non-empty-string $examplePath
     * @param list<non-empty-string> $additionalEnvPaths
     */
    public function assertEnvFileIsSynchronized(string $envPath = '.env', string $examplePath = '.env.example', array $additionalEnvPaths = []): void
    {
        $projectDir = $this->getProjectDir();

        $candidateExtras = ['.env.local', '.env.test', '.env.test.local'];
        foreach ($candidateExtras as $extra) {
            if (file_exists($projectDir . $extra)) {
                $additionalEnvPaths[] = $extra;
            }
        }

        $exampleContent = @file_get_contents($projectDir . $examplePath) ?: '';
        $envContent     = @file_get_contents($projectDir . $envPath) ?: '';

        foreach ($additionalEnvPaths as $extra) {
            $envContent .= "\n" . (@file_get_contents($projectDir . $extra) ?: '');
        }

        $exampleKeys = $this->extractEnvKeys($exampleContent);
        $envKeys     = $this->extractEnvKeys($envContent);

        $osKeys = array_keys($_ENV + $_SERVER);
        $present = array_flip(array_merge($envKeys, $osKeys));

        $missing = [];
        foreach ($exampleKeys as $key) {
            if (!isset($present[$key])) {
                $missing[] = $key;
            }
        }

        $this->assertEmpty(
            $missing,
            sprintf('Missing variables from %s (not found across %s nor as OS envs): %s', $examplePath, implode(', ', array_merge([$envPath], $additionalEnvPaths)), implode(', ', $missing))
        );
    }

    /**
     * Asserts that a specific bundle is enabled in the Kernel.
     *
     * ```php
     * <?php
     * $I->seeBundleIsEnabled(Acme\\AcmeBundle::class);
     * ```
     *
     * @param class-string $bundleClass The Fully Qualified Class Name of the bundle.
     */
    public function seeBundleIsEnabled(string $bundleClass): void
    {
        $bundles = $this->kernel->getBundles();
        $found = false;
        foreach ($bundles as $bundle) {
            if ($bundle instanceof $bundleClass || $bundle::class === $bundleClass) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            sprintf('Bundle "%s" is not enabled in the Kernel. Check config/bundles.php.', $bundleClass)
        );
    }

    /**
     * Asserts that an asset manifest file exists, checking for Webpack Encore or AssetMapper.
     *
     * ```php
     * <?php
     * $I->seeAssetManifestExists();
     * ```
     */
    public function seeAssetManifestExists(): void
    {
        $projectDir = $this->getProjectDir();
        $encoreManifest = $projectDir . 'public/build/manifest.json';
        $mapperManifest = $projectDir . 'public/assets/manifest.json';
        $encoreEntrypoints = $projectDir . 'public/build/entrypoints.json';

        if (is_readable($encoreManifest) && is_readable($encoreEntrypoints)) {
            $this->assertJson((string) file_get_contents($encoreManifest), 'Webpack Encore manifest.json is not valid JSON.');
            $this->assertJson((string) file_get_contents($encoreEntrypoints), 'Webpack Encore entrypoints.json is not valid JSON.');
            return;
        }

        if (is_readable($mapperManifest)) {
            $this->assertJson((string) file_get_contents($mapperManifest), 'AssetMapper manifest.json is not valid JSON.');
            return;
        }

        Assert::fail('No asset manifest file found. Checked for Webpack Encore (public/build/manifest.json) and AssetMapper (public/assets/manifest.json).');
    }

    /**
     * Asserts the Kernel charset matches the expected value.
     *
     * ```php
     * <?php
     * $I->seeKernelCharsetIs('UTF-8');
     * ```
     */
    public function seeKernelCharsetIs(string $expected): void
    {
        $charset = $this->kernel->getCharset();
        $this->assertSame($expected, $charset, sprintf('Kernel charset is "%s" but expected "%s".', $charset, $expected));
    }

    /**
     * Helper to get the project's root directory.
     */
    protected function getProjectDir(): string
    {
        return $this->kernel->getProjectDir() . '/';
    }

    /**
     * Extracts variable keys from the content of a .env file.
     *
     * @return list<string>
     */
    private function extractEnvKeys(string $content): array
    {
        $keys = [];
        if (preg_match_all('/^(?!#)\s*([a-zA-Z_][a-zA-Z0-9_]*)=/m', $content, $matches)) {
            $keys = $matches[1];
        }
        return $keys;
    }
}
