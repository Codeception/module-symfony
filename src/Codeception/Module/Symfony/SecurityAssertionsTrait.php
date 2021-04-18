<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

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
     *
     * @return self
     */
    public function dontSeeAuthentication()
    {
        $security = $this->grabSecurityService();

        $this->assertFalse(
            $security->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY),
            'There is an user authenticated'
        );

        return $this;
    }

    /**
     * Check that user is not authenticated with the 'remember me' option.
     *
     * ```php
     * <?php
     * $I->dontSeeRememberedAuthentication();
     * ```
     *
     * @return self
     */
    public function dontSeeRememberedAuthentication()
    {
        $security = $this->grabSecurityService();

        $hasRememberMeCookie = $this->client->getCookieJar()->get('REMEMBERME');
        $hasRememberMeRole = $security->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_REMEMBERED);

        $isRemembered = $hasRememberMeCookie && $hasRememberMeRole;
        $this->assertFalse(
            $isRemembered,
            'User does have remembered authentication'
        );

        return $this;
    }

    /**
     * Checks that a user is authenticated.
     *
     * ```php
     * <?php
     * $I->seeAuthentication();
     * ```
     *
     * @return self
     */
    public function seeAuthentication()
    {
        $security = $this->grabSecurityService();

        $user = $security->getUser();

        if ($user === null) {
            $this->fail('There is no user in session');
        }

        $this->assertTrue(
            $security->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY),
            'There is no authenticated user'
        );

        return $this;
    }

    /**
     * Checks that a user is authenticated with the 'remember me' option.
     *
     * ```php
     * <?php
     * $I->seeRememberedAuthentication();
     * ```
     *
     * @return self
     */
    public function seeRememberedAuthentication()
    {
        $security = $this->grabSecurityService();

        $user = $security->getUser();

        if ($user === null) {
            $this->fail('There is no user in session');
        }

        $hasRememberMeCookie = $this->client->getCookieJar()->get('REMEMBERME');
        $hasRememberMeRole = $security->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_REMEMBERED);

        $isRemembered = $hasRememberMeCookie && $hasRememberMeRole;
        $this->assertTrue(
            $isRemembered,
            'User does not have remembered authentication'
        );

        return $this;
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
     * @return self
     */
    public function seeUserHasRole(string $role)
    {
        $security = $this->grabSecurityService();

        $user = $security->getUser();

        if ($user === null) {
            $this->fail('There is no user in session');
        }

        $this->assertTrue(
            $security->isGranted($role),
            sprintf(
                'User %s has no role %s',
                $user->getUsername(),
                $role
            )
        );

        return $this;
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
     *
     * @return self
     */
    public function seeUserHasRoles(array $roles)
    {
        foreach ($roles as $role) {
            $this->seeUserHasRole($role);
        }

        return $this;
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
     * @return self
     */
    public function seeUserPasswordDoesNotNeedRehash(UserInterface $user = null)
    {
        if ($user === null) {
            $security = $this->grabSecurityService();
            $user = $security->getUser();
            if ($user === null) {
                $this->fail('No user found to validate');
            }
        }
        $hasher = $this->grabPasswordHasherService();

        $this->assertFalse($hasher->needsRehash($user), 'User password needs rehash');

        return $this;
    }

    protected function grabSecurityService(): Security
    {
        return $this->grabService('security.helper');
    }

    protected function grabPasswordHasherService(): UserPasswordEncoderInterface
    {
        return $this->grabService('security.user_password_encoder.generic');
    }
}