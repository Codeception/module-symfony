<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use function array_intersect_assoc;
use function array_merge;
use function explode;
use function sprintf;
use function strlen;
use function substr_compare;

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
     *
     * @param string $action
     * @param array $params
     * @return self
     */
    public function amOnAction(string $action, array $params = []): self
    {
        $router = $this->grabRouterService();

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
                return $this;
            }
        }

        return $this;
    }

    /**
     * Opens web page using route name and parameters.
     *
     * ```php
     * <?php
     * $I->amOnRoute('posts.create');
     * $I->amOnRoute('posts.show', ['id' => 34]);
     * ```
     *
     * @param string $routeName
     * @param array $params
     * @return self
     */
    public function amOnRoute(string $routeName, array $params = []): self
    {
        $router = $this->grabRouterService();
        if ($router->getRouteCollection()->get($routeName) === null) {
            $this->fail(sprintf('Route with name "%s" does not exists.', $routeName));
        }
        $url = $router->generate($routeName, $params);
        $this->amOnPage($url);

        return $this;
    }

    /**
     * Invalidate previously cached routes.
     *
     * @return self
     */
    public function invalidateCachedRouter(): self
    {
        $this->unpersistService('router');

        return $this;
    }

    /**
     * Checks that current page matches action
     *
     * ```php
     * <?php
     * $I->seeCurrentActionIs('PostController::index');
     * $I->seeCurrentActionIs('HomeController');
     * ```
     *
     * @param string $action
     * @return self
     */
    public function seeCurrentActionIs(string $action): self
    {
        $router = $this->grabRouterService();

        $routes = $router->getRouteCollection()->getIterator();

        foreach ($routes as $route) {
            $controller = $route->getDefault('_controller');
            if (substr_compare($controller, $action, -strlen($action)) === 0) {
                /** @var Request $request */
                $request = $this->client->getRequest();
                $currentActionFqcn = $request->attributes->get('_controller');

                $this->assertStringEndsWith($action, $currentActionFqcn, "Current action is '{$currentActionFqcn}'.");
                return $this;
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
     *
     * @param string $routeName
     * @param array $params
     * @return self
     */
    public function seeCurrentRouteIs(string $routeName, array $params = []): self
    {
        $router = $this->grabRouterService();
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

        $this->assertSame($expected, $intersection);

        return $this;
    }

    /**
     * Checks that current url matches route.
     * Unlike seeCurrentRouteIs, this can matches without exact route parameters
     *
     * ```php
     * <?php
     * $I->seeInCurrentRoute('my_blog_pages');
     * ```
     *
     * @param string $routeName
     * @return self
     */
    public function seeInCurrentRoute(string $routeName): self
    {
        $router = $this->grabRouterService();
        if ($router->getRouteCollection()->get($routeName) === null) {
            $this->fail(sprintf('Route with name "%s" does not exists.', $routeName));
        }

        $uri = explode('?', $this->grabFromCurrentUrl())[0];
        try {
            $matchedRouteName = (string) $router->match($uri)['_route'];
        } catch (ResourceNotFoundException $e) {
            $this->fail(sprintf('The "%s" url does not match with any route', $uri));
        }

        $this->assertSame($matchedRouteName, $routeName);
        return $this;
    }

    protected function grabRouterService(): RouterInterface
    {
        return $this->grabService('router');
    }
}