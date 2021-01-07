<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Codeception\Lib\Connector\Symfony as SymfonyConnector;
use Symfony\Component\HttpFoundation\Response;
use function sprintf;

trait BrowserAssertionsTrait
{
    /**
     * Reboot client's kernel.
     * Can be used to manually reboot kernel when 'rebootable_client' => false
     *
     * ``` php
     * <?php
     *
     * // Perform some requests
     *
     * $I->rebootClientKernel();
     *
     * // Perform other requests
     *
     * ```
     *
     */
    public function rebootClientKernel(): void
    {
        if ($this->client instanceof SymfonyConnector) {
            $this->client->rebootKernel();
        }
    }

    /**
     * Goes to a page and check that it can be accessed.
     *
     * ```php
     * <?php
     * $I->seePageIsAvailable('/dashboard');
     * ```
     *
     * @param string $url
     */
    public function seePageIsAvailable(string $url): void
    {
        $this->amOnPage($url);
        $this->seeResponseCodeIsSuccessful();
        $this->seeInCurrentUrl($url);
    }

    /**
     * Goes to a page and check that it redirects to another.
     *
     * ```php
     * <?php
     * $I->seePageRedirectsTo('/admin', '/login');
     * ```
     *
     * @param string $page
     * @param string $redirectsTo
     */
    public function seePageRedirectsTo(string $page, string $redirectsTo): void
    {
        $this->client->followRedirects(false);
        $this->amOnPage($page);
        /** @var Response $response */
        $response = $this->client->getResponse();
        $this->assertTrue(
            $response->isRedirection()
        );
        $this->client->followRedirect();
        $this->seeInCurrentUrl($redirectsTo);
    }

    /**
     * Submit a form specifying the form name only once.
     *
     * Use this function instead of $I->submitForm() to avoid repeating the form name in the field selectors.
     * If you customized the names of the field selectors use $I->submitForm() for full control.
     *
     * ```php
     * <?php
     * $I->submitSymfonyForm('login_form', [
     *     '[email]'    => 'john_doe@gmail.com',
     *     '[password]' => 'secretForest'
     * ]);
     * ```
     *
     * @param string $name
     * @param string[] $fields
     */
    public function submitSymfonyForm(string $name, array $fields): void
    {
        $selector = sprintf('form[name=%s]', $name);

        $params = [];
        foreach ($fields as $key => $value) {
            $fixedKey = sprintf('%s%s', $name, $key);
            $params[$fixedKey] = $value;
        }
        $button = sprintf('%s_submit', $name);

        $this->submitForm($selector, $params, $button);
    }
}