<?php

declare(strict_types=1);

namespace Tests\App\config;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\TraceableHttpClient;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;
use Symfony\Component\Mailer\EventListener\MessageLoggerListener;
use Symfony\Component\Notifier\EventListener\NotificationLoggerListener;
use Tests\App\Command\TestCommand;
use Tests\App\Controller\AppController;
use Tests\App\Doctrine\DoctrineSetup;
use Tests\App\Entity\User;
use Tests\App\Event\TestEvent;
use Tests\App\HttpClient\MockResponseFactory;
use Tests\App\Listener\TestEventListener;
use Tests\App\Logger\ArrayLogger;
use Tests\App\Mailer\RegistrationMailer;
use Tests\App\Notifier\NotifierFixture;
use Tests\App\Repository\UserRepository;
use Tests\App\Repository\UserRepositoryInterface;
use Tests\App\Security\TestUserProvider;
use Twig\Extension\ProfilerExtension;
use Twig\Profiler\Profile;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->defaults()->autowire()->autoconfigure()->public();

    $services->set(AppController::class);
    $services->set(TestCommand::class)->tag('console.command', ['command' => 'app:test-command']);

    $services->set('doctrine.orm.entity_manager', EntityManagerInterface::class)
        ->factory([DoctrineSetup::class, 'createEntityManager']);
    $services->alias('doctrine.orm.default_entity_manager', 'doctrine.orm.entity_manager')->public();
    $services->set('doctrine.dbal.default_connection', Connection::class)
        ->factory([DoctrineSetup::class, 'createConnection']);

    $services->set(UserRepository::class)
        ->factory([service('doctrine.orm.entity_manager'), 'getRepository'])
        ->arg(0, User::class);
    $services->alias(UserRepositoryInterface::class, UserRepository::class)->public();

    $services->set('security.user.provider.test', TestUserProvider::class)
        ->arg('$repository', service(UserRepository::class))
        ->tag('security.user_provider');

    $services->alias('security.password_hasher', 'security.user_password_hasher')->public();

    if (class_exists(Security::class)) {
        $services->set(Security::class)->arg('$container', service('test.service_container'));
        $services->alias('security.helper', Security::class)->public();
    }

    $services->set('mailer.message_logger_listener', MessageLoggerListener::class)->tag('kernel.event_subscriber');
    $services->set('notifier.notification_logger_listener', NotificationLoggerListener::class)->tag('kernel.event_subscriber');
    $services->alias('notifier.logger_notification_listener', 'notifier.notification_logger_listener')->public();

    $services->set(RegistrationMailer::class)->arg('$mailer', service('mailer'));
    $services->set(NotifierFixture::class)->arg('$dispatcher', service('event_dispatcher'));

    $services->set(TestEventListener::class)
        ->tag('kernel.event_listener', ['event' => TestEvent::class, 'method' => 'onTestEvent'])
        ->tag('kernel.event_listener', ['event' => 'named.event', 'method' => 'onNamedEvent']);

    $services->set('logger', ArrayLogger::class);
    $services->alias(LoggerInterface::class, 'logger')->public();

    $services->set(Profile::class);
    $services->set(ProfilerExtension::class)->arg('$profile', service(Profile::class))->tag('twig.extension');

    $services->set(MockResponseFactory::class);

    $services->set('app.http_client.inner', MockHttpClient::class)
        ->arg('$responseFactory', service(MockResponseFactory::class));

    $services->set('app.http_client', TraceableHttpClient::class)
        ->args([service('app.http_client.inner'), service('debug.stopwatch')->nullOnInvalid()]);

    $services->set('app.http_client.json_client.inner', MockHttpClient::class)
        ->args([service(MockResponseFactory::class), 'https://api.example.com/']);

    $services->set('app.http_client.json_client', TraceableHttpClient::class)
        ->args([service('app.http_client.json_client.inner'), service('debug.stopwatch')->nullOnInvalid()]);

    $services->set(HttpClientDataCollector::class)
        ->call('registerClient', ['app.http_client', service('app.http_client')])
        ->call('registerClient', ['app.http_client.json_client', service('app.http_client.json_client')])
        ->tag('data_collector', ['id' => 'http_client', 'template' => '@WebProfiler/Collector/http_client.html.twig', 'priority' => 100]);
    $services->alias('data_collector.http_client', HttpClientDataCollector::class)->public();

    $services->set(LoggerDataCollector::class)
        ->arg('$logger', service('logger'))
        ->tag('data_collector', ['id' => 'logger', 'template' => '@WebProfiler/Collector/logger.html.twig', 'priority' => 300]);
    $services->alias('data_collector.logger', LoggerDataCollector::class)->public();

};
