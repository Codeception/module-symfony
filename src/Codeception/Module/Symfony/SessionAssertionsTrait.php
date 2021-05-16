<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;
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

        if ($this->config['guard']) {
            $token = new PostAuthenticationGuardToken($user, $firewallName, $user->getRoles());
        } else {
            $token = new UsernamePasswordToken($user, null, $firewallName, $user->getRoles());
        }

        if ($firewallContext) {
            $session->set('_security_'.$firewallContext, serialize($token));
        } else {
            $session->set('_security_'.$firewallName, serialize($token));
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
     * @param string $attribute
     * @param mixed|null $value
     */
    public function dontSeeInSession(string $attribute, $value = null): void
    {
        $session = $this->getCurrentSession();

        if (null === $value) {
            if ($session->has($attribute)) {
                $this->fail("Session attribute with name '{$attribute}' does exist");
            }
        }
        else {
            $this->assertNotSame($value, $session->get($attribute));
        }
    }

    /**
     * Invalidate the current session.
     *
     * ```php
     * <?php
     * $I->logout();
     * ```
     */
    public function logout(): void
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
     *
     * @param string $attribute
     * @param mixed|null $value
     */
    public function seeInSession(string $attribute, $value = null): void
    {
        $session = $this->getCurrentSession();

        if (!$session->has($attribute)) {
            $this->fail("No session attribute with name '{$attribute}'");
        }

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

    protected function getCurrentSession(): SessionInterface
    {
        return $this->grabService('session');
    }
}
