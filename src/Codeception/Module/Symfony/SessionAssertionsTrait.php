<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use InvalidArgumentException;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use function class_exists;
use function in_array;
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
    public function amLoggedInAs(UserInterface $user, string $firewallName = 'main', ?string $firewallContext = null): void
    {
        $this->amLoggedInWithToken($this->createAuthenticationToken($user, $firewallName), $firewallName, $firewallContext);
    }

    public function amLoggedInWithToken(TokenInterface $token, string $firewallName = 'main', ?string $firewallContext = null): void
    {
        $this->getTokenStorage()->setToken($token);

        $session = $this->getCurrentSession();
        $session->set("_security_" . ($firewallContext ?? $firewallName), serialize($token));
        $session->save();

        $this->getClient()->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
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

        if ($value === null) {
            $this->assertFalse($session->has($attribute), "Session attribute '{$attribute}' exists.");
        } else {
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
        $this->amOnPage($this->getLogoutUrlGenerator()->getLogoutPath());
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
        $this->getTokenStorage()->setToken(null);

        $session     = $this->getCurrentSession();
        $sessionName = $session->getName();
        $session->invalidate();

        $cookieJar        = $this->getClient()->getCookieJar();
        $cookiesToExpire  = ['MOCKSESSID', 'REMEMBERME', $sessionName];
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
        $this->assertTrue($session->has($attribute), "No session attribute with name '{$attribute}'");

        if ($value !== null) {
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
     * @param array<int|string, mixed> $bindings
     */
    public function seeSessionHasValues(array $bindings): void
    {
        foreach ($bindings as $key => $value) {
            if (is_int($key)) {
                if (!is_string($value)) {
                    throw new InvalidArgumentException(
                        sprintf('Attribute name must be string, %s given.', get_debug_type($value))
                    );
                }
                $this->seeInSession($value);
            } else {
                $this->seeInSession($key, $value);
            }
        }
    }

    protected function getTokenStorage(): TokenStorageInterface
    {
        /** @var TokenStorageInterface $storage */
        $storage = $this->grabService('security.token_storage');
        return $storage;
    }

    protected function getLogoutUrlGenerator(): LogoutUrlGenerator
    {
        /** @var LogoutUrlGenerator $generator */
        $generator = $this->grabService('security.logout_url_generator');
        return $generator;
    }

    protected function getAuthenticator(): AuthenticatorInterface
    {
        /** @var AuthenticatorInterface $authenticator */
        $authenticator = $this->grabService(AuthenticatorInterface::class);
        return $authenticator;
    }

    protected function getCurrentSession(): SessionInterface
    {
        $container = $this->_getContainer();

        if ($this->getSymfonyMajorVersion() < 6 || $container->has('session')) {
            /** @var SessionInterface $session */
            $session = $container->get('session');
            return $session;
        }

        /** @var SessionFactoryInterface $factory */
        $factory = $container->get('session.factory');
        $session = $factory->createSession();
        $container->set('session', $session);

        return $session;
    }

    protected function getSymfonyMajorVersion(): int
    {
        return Kernel::MAJOR_VERSION;
    }

    protected function createAuthenticationToken(UserInterface $user, string $firewallName): TokenInterface
    {
        $roles = $user->getRoles();

        if ($this->getSymfonyMajorVersion() >= 6 && ($this->config['authenticator'] ?? false) === true) {
            $passport = new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn () => $user));
            return $this->getAuthenticator()->createToken($passport, $firewallName);
        }

        if ($this->getSymfonyMajorVersion() < 6 && ($this->config['guard'] ?? false) === true) {
            $postClass = 'Symfony\\Component\\Security\\Guard\\Token\\PostAuthenticationGuardToken';
            if (class_exists($postClass)) {
                /** @var TokenInterface $token */
                $token = new $postClass($user, $firewallName, $roles);
                return $token;
            }
        }

        return new UsernamePasswordToken($user, $firewallName, $roles);
    }
}
