<?php

declare(strict_types=1);

namespace Tests\App;

require_once __DIR__ . '/Security/SecurityBundleSecurityAlias.php';

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Serializer\DataCollector\SerializerDataCollector;

class TestKernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new SecurityBundle(),
            new TwigBundle(),
        ];
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $this->configureExtensions($container);

        $container->import(__DIR__ . '/config/services.php');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/config/routes.php');
    }

    private function configureExtensions(ContainerConfigurator $container): void
    {
        $profilerConfig = ['enabled' => true, 'collect' => true];
        if (BaseKernel::VERSION_ID >= 60200 && class_exists(SerializerDataCollector::class)) {
            $profilerConfig['collect_serializer_data'] = true;
        }

        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'profiler' => $profilerConfig,
            'property_info' => ['enabled' => true],
            'session' => ['handler_id' => null, 'storage_factory_id' => 'session.storage.factory.mock_file'],
            'mailer' => ['dsn' => 'null://null'],
            'default_locale' => 'en',
            'translator' => ['default_path' => __DIR__ . '/translations', 'fallbacks' => ['es'], 'logging' => true],
            'validation' => ['enabled' => true],
            'form' => ['enabled' => true],
            'notifier' => ['chatter_transports' => ['async' => 'null://null'], 'texter_transports' => ['sms' => 'null://null']],
        ]);

        $container->extension('twig', ['default_path' => __DIR__ . '/templates', 'debug' => true]);

        $this->configureSecurity($container);
    }

    private function configureSecurity(ContainerConfigurator $container): void
    {
        $mainFirewall = [
            'lazy' => BaseKernel::VERSION_ID >= 60000,
            'pattern' => '^/',
            'provider' => 'doctrine_users',
            'logout' => ['path' => 'logout'],
            'form_login' => ['login_path' => 'app_login', 'check_path' => 'app_login'],
            'remember_me' => ['secret' => 'test', 'remember_me_parameter' => '_remember_me'],
        ];

        if (BaseKernel::VERSION_ID < 60000) {
            $mainFirewall['anonymous'] = true;
        }

        $container->extension('security', [
            'password_hashers' => [PasswordAuthenticatedUserInterface::class => 'auto'],
            'providers' => ['doctrine_users' => ['id' => 'security.user.provider.test']],
            'firewalls' => ['main' => $mainFirewall],
        ]);

        $container->parameters()->set('app.param', 'value');
        $container->parameters()->set('app.business_name', 'Codeception');
    }
}
