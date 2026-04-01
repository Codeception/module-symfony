<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Tests\App\Controller\AppController;

return function (RoutingConfigurator $routes): void {
    $routes->add('app_login', '/login')->controller(AppController::class . '::login');
    $routes->add('app_register', '/register')->controller(AppController::class . '::register');
    $routes->add('dashboard', '/dashboard')->controller(AppController::class . '::dashboard');
    $routes->add('deprecated', '/deprecated')->controller(AppController::class . '::deprecated');
    $routes->add('dispatch_event', '/dispatch-event')->controller(AppController::class . '::dispatchEvent');
    $routes->add('dispatch_named_event', '/dispatch-named-event')->controller(AppController::class . '::dispatchNamedEvent');
    $routes->add('dispatch_orphan_event', '/dispatch-orphan-event')->controller(AppController::class . '::dispatchOrphanEvent');
    $routes->add('form_handler', '/form')->controller(AppController::class . '::form');
    $routes->add('http_client', '/http-client')->controller(AppController::class . '::httpClientRequests');
    $routes->add('index', '/')->controller(AppController::class . '::index');
    $routes->add('logout', '/logout')->controller(AppController::class . '::logout');
    $routes->add('redirect_home', '/redirect_home')->controller(AppController::class . '::redirectToHome');
    $routes->add('request_attr', '/request_attr')->controller(AppController::class . '::requestWithAttribute');
    $routes->add('response_cookie', '/response_cookie')->controller(AppController::class . '::responseWithCookie');
    $routes->add('response_json', '/response_json')->controller(AppController::class . '::responseJsonFormat');
    $routes->add('sample', '/sample')->controller(AppController::class . '::sample');
    $routes->add('send_email', '/send-email')->controller(AppController::class . '::sendEmail');
    $routes->add('test_page', '/test_page')->controller(AppController::class . '::testPage');
    $routes->add('unprocessable_entity', '/unprocessable_entity')->controller(AppController::class . '::unprocessableEntity');
};
