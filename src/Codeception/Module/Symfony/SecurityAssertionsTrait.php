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
        $this->assertFalse(
            $this->grabSecurityService()->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY),
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
        $this->assertFalse($this->isRemembered(), 'User does have remembered authentication.');
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
        $this->assertTrue(
            $this->grabSecurityService()->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY),
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
        $this->assertTrue($this->isRemembered(), 'User does not have remembered authentication.');
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
        $this->assertTrue(
            $this->grabSecurityService()->isGranted($role),
            sprintf('User %s has no role %s', $this->getAuthenticatedUser()->getUserIdentifier(), $role)
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

        $this->assertFalse($this->grabPasswordHasherService()->needsRehash($userToValidate), 'User password needs rehash.');
    }

    private function getAuthenticatedUser(): UserInterface
    {
        return $this->grabSecurityService()->getUser() ?? Assert::fail('No user found in session to perform this check.');
    }

    private function isRemembered(): bool
    {
        return $this->getClient()->getCookieJar()->get('REMEMBERME') !== null
            && $this->grabSecurityService()->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_REMEMBERED);
    }

    /** @return Security */
    protected function grabSecurityService()
    {
        /** @var Security */
        return $this->grabService('security.helper');
    }

    protected function grabPasswordHasherService(): UserPasswordHasherInterface
    {
        /** @var UserPasswordHasherInterface */
        return $this->grabService('security.password_hasher');
    }
}
