<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\RouterAssertionsTrait;
use Tests\App\Controller\AppController;
use Tests\Support\CodeceptTestCase;

final class RouterAssertionsTest extends CodeceptTestCase
{
    use RouterAssertionsTrait;

    public function testAmOnAction(): void
    {
        $this->amOnAction(AppController::class . '::index');
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Hello World!', $this->client->getResponse()->getContent());
    }

    public function testAmOnRoute(): void
    {
        $this->amOnRoute('index');
        $this->assertStringContainsString('Hello World!', $this->client->getResponse()->getContent());
    }

    public function testInvalidateCachedRouter(): void
    {
        $this->persistService('router');
        $this->assertArrayHasKey('router', $this->persistentServices);
        $this->invalidateCachedRouter();
        $this->assertArrayNotHasKey('router', $this->persistentServices);
    }

    public function testSeeCurrentActionIs(): void
    {
        $this->client->request('GET', '/');
        $this->seeCurrentActionIs(AppController::class . '::index');
    }

    public function testSeeCurrentRouteIs(): void
    {
        $this->client->request('GET', '/login');
        $this->seeCurrentRouteIs('app_login');
    }

    public function testSeeInCurrentRoute(): void
    {
        $this->client->request('GET', '/register');
        $this->seeInCurrentRoute('app_register');
    }
}
