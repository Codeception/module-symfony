<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Assert;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
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
            'There is an user authenticated.'
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
        $security  = $this->grabSecurityService();
        $client    = $this->getClient();
        $hasCookie = $client->getCookieJar()->get('REMEMBERME') !== null;
        $hasRole   = $security->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_REMEMBERED);

        $this->assertFalse($hasCookie && $hasRole, 'User does have remembered authentication.');
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
        $this->assertTrue(
            $security->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY),
            'There is no authenticated user.'
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
        $security  = $this->grabSecurityService();
        $client    = $this->getClient();
        $hasCookie = $client->getCookieJar()->get('REMEMBERME') !== null;
        $hasRole   = $security->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_REMEMBERED);

        $this->assertTrue($hasCookie && $hasRole, 'User does not have remembered authentication.');
    }

    /**
     * Check that the current user has a role
     *
     * ```php
     * <?php
     * $I->seeUserHasRole('ROLE_ADMIN');
     * ```
     */
    public function seeUserHasRole(string $role): void
    {
        $user       = $this->getAuthenticatedUser();
        $identifier = $user->getUserIdentifier();

        $this->assertTrue(
            $this->grabSecurityService()->isGranted($role),
            sprintf('User %s has no role %s', $identifier, $role)
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
     * If the user is not provided, it is taken from the current session.
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
    public function seeUserPasswordDoesNotNeedRehash(?UserInterface $user = null): void
    {
        $userToValidate = $user ?? $this->getAuthenticatedUser();

        if (!$userToValidate instanceof PasswordAuthenticatedUserInterface) {
            Assert::fail('Provided user does not implement PasswordAuthenticatedUserInterface.');
        }

        $hasher = $this->grabPasswordHasherService();
        $this->assertFalse($hasher->needsRehash($userToValidate), 'User password needs rehash.');
    }

    private function getAuthenticatedUser(): UserInterface
    {
        $user = $this->grabSecurityService()->getUser();
        if ($user === null) {
            Assert::fail('No user found in session to perform this check.');
        }
        return $user;
    }

    /** @return Security */
    protected function grabSecurityService()
    {
        /** @var Security $security */
        $security = $this->grabService('security.helper');
        return $security;
    }

    protected function grabPasswordHasherService(): UserPasswordHasherInterface
    {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $this->getService('security.password_hasher');
        return $hasher;
    }
}
