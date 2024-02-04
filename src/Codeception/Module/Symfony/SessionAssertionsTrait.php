<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
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
     */
    public function amLoggedInAs(UserInterface $user, string $firewallName = 'main', string $firewallContext = null): void
    {
        $roles = $user->getRoles();
        $token = $this->createAuthenticationToken($user, $firewallName, $roles);
        $this->loginWithToken($token, $firewallContext, $firewallName);
    }

    public function amLoggedInWithToken(TokenInterface $token, string $firewallName = 'main', string $firewallContext = null): void
    {
        $this->loginWithToken($token, $firewallName, $firewallContext);
    }

    private function loginWithToken(TokenInterface $token, string $firewallName = 'main', string $firewallContext = null): void
    {
        $this->getTokenStorage()->setToken($token);

        $session = $this->getCurrentSession();
        $sessionKey = $firewallContext ? "_security_{$firewallContext}" : "_security_{$firewallName}";
        $session->set($sessionKey, serialize($token));
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
     */
    public function dontSeeInSession(string $attribute, mixed $value = null): void
    {
        $session = $this->getCurrentSession();

        $attributeExists = $session->has($attribute);
        $this->assertFalse($attributeExists, "Session attribute '{$attribute}' exists.");

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
        $logoutPath = $this->getLogoutUrlGenerator()->getLogoutPath();
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
        $cookiesToExpire = ['MOCKSESSID', 'REMEMBERME', $sessionName];
        foreach ($cookieJar->all() as $cookie) {
            $cookieName = $cookie->getName();
            if (in_array($cookieName, $cookiesToExpire, true)) {
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

        $attributeExists = $session->has($attribute);
        $this->assertTrue($attributeExists, "No session attribute with name '{$attribute}'");

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

        if ($this->getSymfonyMajorVersion() < 6 || $container->has('session')) {
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

    /**
     * @return UsernamePasswordToken|PostAuthenticationGuardToken|PostAuthenticationToken
     */
    protected function createAuthenticationToken(UserInterface $user, string $firewallName, array $roles)
    {
        if ($this->getSymfonyMajorVersion() < 6) {
            return $this->config['guard']
                ? new PostAuthenticationGuardToken($user, $firewallName, $roles)
                : new UsernamePasswordToken($user, null, $firewallName, $roles);
        }

        return $this->config['authenticator']
            ? new PostAuthenticationToken($user, $firewallName, $roles)
            : new UsernamePasswordToken($user, $firewallName, $roles);
    }
}
