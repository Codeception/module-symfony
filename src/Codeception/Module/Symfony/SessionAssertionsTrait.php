<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use function is_int;
use function serialize;

trait SessionAssertionsTrait
{
    /**
     * Login with the given user object.
     * The `$user` object must have a persistent identifier.
     * If you have more than one firewall or firewall context, you can specify the desired one as a parameter.
     *
     * ```php
     * <?php
     * $user = $I->grabEntityFromRepository(User::class, [
     *     'email' => 'john_doe@example.com'
     * ]);
     * $I->amLoggedInAs($user);
     * ```
     *
     * @param UserInterface $user
     * @param string $firewallName
     * @param null $firewallContext
     */
    public function amLoggedInAs(UserInterface $user, string $firewallName = 'main', $firewallContext = null): void
    {
        $session = $this->getCurrentSession();

        if ($this->getSymfonyMajorVersion() < 6) {
            if ($this->config['guard']) {
                $token = new PostAuthenticationGuardToken($user, $firewallName, $user->getRoles());
            } else {
                $token = new UsernamePasswordToken($user, null, $firewallName, $user->getRoles());
            }
        } else {
            if ($this->config['authenticator']) {
                $token = new PostAuthenticationToken($user, $firewallName, $user->getRoles());
            } else {
                $token = new UsernamePasswordToken($user, $firewallName, $user->getRoles());
            }
        }

        $this->getTokenStorage()->setToken($token);

        if ($firewallContext) {
            $session->set('_security_' . $firewallContext, serialize($token));
        } else {
            $session->set('_security_' . $firewallName, serialize($token));
        }

        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $this->client->getCookieJar()->set($cookie);
    }

    /**
     * Assert that a session attribute does not exist, or is not equal to the passed value.
     *
     * ```php
     * <?php
     * $I->dontSeeInSession('attribute');
     * $I->dontSeeInSession('attribute', 'value');
     * ```
     *
     */
    public function dontSeeInSession(string $attribute, mixed $value = null): void
    {
        $session = $this->getCurrentSession();

        if ($attributeExists = $session->has($attribute)) {
            $this->fail("Session attribute with name '{$attribute}' does exist");
        }
        $this->assertFalse($attributeExists);

        if (null !== $value) {
            $this->assertNotSame($value, $session->get($attribute));
        }
    }

    /**
     * Go to the configured logout url (by default: `/logout`).
     * This method includes redirection to the destination page configured after logout.
     *
     * See the Symfony documentation on ['Logging Out'](https://symfony.com/doc/current/security.html#logging-out).
     */
    public function goToLogoutPath(): void
    {
        $logoutUrlGenerator = $this->getLogoutUrlGenerator();
        $logoutPath = $logoutUrlGenerator->getLogoutPath();
        $this->amOnPage($logoutPath);
    }

    /**
     * Alias method for [`logoutProgrammatically()`](https://codeception.com/docs/modules/Symfony#logoutProgrammatically)
     *
     * ```php
     * <?php
     * $I->logout();
     * ```
     */
    public function logout(): void
    {
        $this->logoutProgrammatically();
    }

    /**
     * Invalidates the current user's session and expires the session cookies.
     * This method does not include any redirects after logging out.
     *
     * ```php
     * <?php
     * $I->logoutProgrammatically();
     * ```
     */
    public function logoutProgrammatically(): void
    {
        if ($tokenStorage = $this->getTokenStorage()) {
            $tokenStorage->setToken();
        }

        $session = $this->getCurrentSession();

        $sessionName = $session->getName();
        $session->invalidate();

        $cookieJar = $this->client->getCookieJar();
        foreach ($cookieJar->all() as $cookie) {
            $cookieName = $cookie->getName();
            if ($cookieName === 'MOCKSESSID' ||
                $cookieName === 'REMEMBERME' ||
                $cookieName === $sessionName
            ) {
                $cookieJar->expire($cookieName);
            }
        }

        $cookieJar->flushExpiredCookies();
    }

    /**
     * Assert that a session attribute exists.
     *
     * ```php
     * <?php
     * $I->seeInSession('attribute');
     * $I->seeInSession('attribute', 'value');
     * ```
     */
    public function seeInSession(string $attribute, mixed $value = null): void
    {
        $session = $this->getCurrentSession();

        if (!$attributeExists = $session->has($attribute)) {
            $this->fail("No session attribute with name '{$attribute}'");
        }
        $this->assertTrue($attributeExists);

        if (null !== $value) {
            $this->assertSame($value, $session->get($attribute));
        }
    }

    /**
     * Assert that the session has a given list of values.
     *
     * ```php
     * <?php
     * $I->seeSessionHasValues(['key1', 'key2']);
     * $I->seeSessionHasValues(['key1' => 'value1', 'key2' => 'value2']);
     * ```
     *
     * @param array $bindings
     */
    public function seeSessionHasValues(array $bindings): void
    {
        foreach ($bindings as $key => $value) {
            if (is_int($key)) {
                $this->seeInSession($value);
            } else {
                $this->seeInSession($key, $value);
            }
        }
    }

    protected function getTokenStorage(): ?TokenStorageInterface
    {
        return $this->getService('security.token_storage');
    }

    protected function getLogoutUrlGenerator(): ?LogoutUrlGenerator
    {
        return $this->getService('security.logout_url_generator');
    }

    protected function getCurrentSession(): SessionInterface
    {
        $container = $this->_getContainer();

        if ($this->getSymfonyMajorVersion() < 6) {
            return $container->get('session');
        }

        if ($container->has('session')) {
            return $container->get('session');
        }

        $session = $container->get('session.factory')->createSession();
        $container->set('session', $session);

        return $session;
    }

    protected function getSymfonyMajorVersion(): int
    {
        return $this->kernel::MAJOR_VERSION;
    }
}
