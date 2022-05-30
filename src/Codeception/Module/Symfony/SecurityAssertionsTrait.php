<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use function sprintf;

trait SecurityAssertionsTrait
{
    /**
     * Check that user is not authenticated.
     *
     * ```php
     * <?php
     * $I->dontSeeAuthentication();
     * ```
     */
    public function dontSeeAuthentication(): void
    {
        $security = $this->grabSecurityService();

        $this->assertFalse(
            $security->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY),
            'There is an user authenticated'
        );
    }

    /**
     * Check that user is not authenticated with the 'remember me' option.
     *
     * ```php
     * <?php
     * $I->dontSeeRememberedAuthentication();
     * ```
     */
    public function dontSeeRememberedAuthentication(): void
    {
        $security = $this->grabSecurityService();

        $hasRememberMeCookie = $this->client->getCookieJar()->get('REMEMBERME');
        $hasRememberMeRole = $security->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_REMEMBERED);

        $isRemembered = $hasRememberMeCookie && $hasRememberMeRole;
        $this->assertFalse(
            $isRemembered,
            'User does have remembered authentication'
        );
    }

    /**
     * Checks that a user is authenticated.
     *
     * ```php
     * <?php
     * $I->seeAuthentication();
     * ```
     */
    public function seeAuthentication(): void
    {
        $security = $this->grabSecurityService();

        if (!$user = $security->getUser()) {
            $this->fail('There is no user in session');
        }

        $this->assertTrue(
            $security->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY),
            'There is no authenticated user'
        );
    }

    /**
     * Checks that a user is authenticated with the 'remember me' option.
     *
     * ```php
     * <?php
     * $I->seeRememberedAuthentication();
     * ```
     */
    public function seeRememberedAuthentication(): void
    {
        $security = $this->grabSecurityService();

        if ($security->getUser() === null) {
            $this->fail('There is no user in session');
        }

        $hasRememberMeCookie = $this->client->getCookieJar()->get('REMEMBERME');
        $hasRememberMeRole = $security->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_REMEMBERED);

        $isRemembered = $hasRememberMeCookie && $hasRememberMeRole;
        $this->assertTrue(
            $isRemembered,
            'User does not have remembered authentication'
        );
    }

    /**
     * Check that the current user has a role
     *
     * ```php
     * <?php
     * $I->seeUserHasRole('ROLE_ADMIN');
     * ```
     *
     * @param string $role
     */
    public function seeUserHasRole(string $role): void
    {
        $security = $this->grabSecurityService();

        if (!$user = $security->getUser()) {
            $this->fail('There is no user in session');
        }

        $userIdentifier = method_exists($user, 'getUserIdentifier') ?
            $user->getUserIdentifier() :
            $user->getUsername();

        $this->assertTrue(
            $security->isGranted($role),
            sprintf(
                'User %s has no role %s',
                $userIdentifier,
                $role
            )
        );
    }

    /**
     * Verifies that the current user has multiple roles
     *
     * ```php
     * <?php
     * $I->seeUserHasRoles(['ROLE_USER', 'ROLE_ADMIN']);
     * ```
     *
     * @param string[] $roles
     */
    public function seeUserHasRoles(array $roles): void
    {
        foreach ($roles as $role) {
            $this->seeUserHasRole($role);
        }
    }

    /**
     * Checks that the user's password would not benefit from rehashing.
     * If the user is not provided it is taken from the current session.
     *
     * You might use this function after performing tasks like registering a user or submitting a password update form.
     *
     * ```php
     * <?php
     * $I->seeUserPasswordDoesNotNeedRehash();
     * $I->seeUserPasswordDoesNotNeedRehash($user);
     * ```
     *
     * @param UserInterface|null $user
     */
    public function seeUserPasswordDoesNotNeedRehash(UserInterface $user = null): void
    {
        if ($user === null) {
            $security = $this->grabSecurityService();
            if (!$user = $security->getUser()) {
                $this->fail('No user found to validate');
            }
        }

        $hasher = $this->grabPasswordHasherService();

        $this->assertFalse($hasher->needsRehash($user), 'User password needs rehash');
    }

    protected function grabSecurityService(): Security
    {
        return $this->grabService('security.helper');
    }

    protected function grabPasswordHasherService(): UserPasswordHasherInterface|UserPasswordEncoderInterface
    {
        $hasher = $this->getService('security.password_hasher') ?: $this->getService('security.password_encoder');

        if ($hasher === null) {
            $this->fail('Password hasher service could not be found.');
        }

        return $hasher;
    }
}