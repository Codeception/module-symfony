<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use function array_intersect_assoc;
use function explode;
use function sprintf;

trait RouterAssertionsTrait
{
    /**
     * Opens web page by action name
     *
     * ```php
     * <?php
     * $I->amOnAction('PostController::index');
     * $I->amOnAction('HomeController');
     * $I->amOnAction('ArticleController', ['slug' => 'lorem-ipsum']);
     * ```
     */
    public function amOnAction(string $action, array $params = []): void
    {
        $router = $this->grabRouterService();
        $routes = $router->getRouteCollection()->getIterator();

        /** @var Route $route */
        foreach ($routes as $route) {
            $controller = $route->getDefault('_controller');
            if (str_ends_with((string) $controller, $action)) {
                $resource = $router->match($route->getPath());
                $url      = $router->generate(
                    $resource['_route'],
                    $params
                );
                $this->amOnPage($url);
                return;
            }
        }
    }

    /**
     * Opens web page using route name and parameters.
     *
     * ```php
     * <?php
     * $I->amOnRoute('posts.create');
     * $I->amOnRoute('posts.show', ['id' => 34]);
     * ```
     */
    public function amOnRoute(string $routeName, array $params = []): void
    {
        $router = $this->grabRouterService();
        if ($router->getRouteCollection()->get($routeName) === null) {
            $this->fail(sprintf('Route with name "%s" does not exist.', $routeName));
        }

        $url = $router->generate($routeName, $params);
        $this->amOnPage($url);
    }

    /**
     * Invalidate previously cached routes.
     */
    public function invalidateCachedRouter(): void
    {
        $this->unpersistService('router');
    }

    /**
     * Checks that current page matches action
     *
     * ```php
     * <?php
     * $I->seeCurrentActionIs('PostController::index');
     * $I->seeCurrentActionIs('HomeController');
     * ```
     */
    public function seeCurrentActionIs(string $action): void
    {
        $router = $this->grabRouterService();
        $routes = $router->getRouteCollection()->getIterator();

        /** @var Route $route */
        foreach ($routes as $route) {
            $controller = $route->getDefault('_controller');
            if (str_ends_with((string) $controller, $action)) {
                $request = $this->getClient()->getRequest();
                $currentActionFqcn = $request->attributes->get('_controller');

                $this->assertStringEndsWith($action, $currentActionFqcn, "Current action is '{$currentActionFqcn}'.");
                return;
            }
        }

        $this->fail("Action '{$action}' does not exist");
    }

    /**
     * Checks that current url matches route.
     *
     * ```php
     * <?php
     * $I->seeCurrentRouteIs('posts.index');
     * $I->seeCurrentRouteIs('posts.show', ['id' => 8]);
     * ```
     */
    public function seeCurrentRouteIs(string $routeName, array $params = []): void
    {
        $router = $this->grabRouterService();
        if ($router->getRouteCollection()->get($routeName) === null) {
            $this->fail(sprintf('Route with name "%s" does not exist.', $routeName));
        }

        $uri = explode('?', $this->grabFromCurrentUrl())[0];
        $match = [];
        try {
            $match = $router->match($uri);
        } catch (ResourceNotFoundException) {
            $this->fail(sprintf('The "%s" url does not match with any route', $uri));
        }

        $expected = ['_route' => $routeName, ...$params];
        $intersection = array_intersect_assoc($expected, $match);

        $this->assertSame($expected, $intersection);
    }

    /**
     * Checks that current url matches route.
     * Unlike seeCurrentRouteIs, this can matches without exact route parameters
     *
     * ```php
     * <?php
     * $I->seeInCurrentRoute('my_blog_pages');
     * ```
     */
    public function seeInCurrentRoute(string $routeName): void
    {
        $router = $this->grabRouterService();
        if ($router->getRouteCollection()->get($routeName) === null) {
            $this->fail(sprintf('Route with name "%s" does not exist.', $routeName));
        }

        $uri = explode('?', $this->grabFromCurrentUrl())[0];
        $matchedRouteName = '';
        try {
            $matchedRouteName = (string)$router->match($uri)['_route'];
        } catch (ResourceNotFoundException) {
            $this->fail(sprintf('The "%s" url does not match with any route', $uri));
        }

        $this->assertSame($matchedRouteName, $routeName);
    }

    protected function grabRouterService(): RouterInterface
    {
        return $this->grabService('router');
    }
}