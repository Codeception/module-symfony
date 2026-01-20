<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Assert;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

use function array_keys;
use function array_unique;
use function array_values;
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

    /**
     * Asserts that a security firewall is configured and active.
     *
     * ```php
     * <?php
     * $I->seeFirewallIsActive('main');
     * ```
     */
    public function seeFirewallIsActive(string $firewallName): void
    {
        $container = $this->_getContainer();

        if ($container->hasParameter('security.firewalls')) {
            /** @var list<string> $firewalls */
            $firewalls = $container->getParameter('security.firewalls');
            $this->assertContains(
                $firewallName,
                $firewalls,
                sprintf('Firewall "%s" is not configured. Check your security.yaml.', $firewallName)
            );

            return;
        }

        $contextId = 'security.firewall.map.context.' . $firewallName;
        $this->assertTrue(
            $container->has($contextId),
            sprintf('Firewall "%s" context was not found (checked "%s").', $firewallName, $contextId)
        );
    }

    /**
     * Asserts that a role is present either as a key of the role hierarchy or among any inherited roles.
     * Skips when role hierarchy is not configured.
     *
     * ```php
     * <?php
     * $I->seeRoleInHierarchy('ROLE_ADMIN');
     * ```
     */
    public function seeRoleInHierarchy(string $role): void
    {
        $container = $this->_getContainer();
        if (!$container->hasParameter('security.role_hierarchy.roles')) {
            Assert::markTestSkipped('Role hierarchy is not configured; skipping role hierarchy assertion.');
        }

        /** @var array<string, list<string>> $hierarchy */
        $hierarchy = $container->getParameter('security.role_hierarchy.roles');

        $all = array_keys($hierarchy);
        foreach ($hierarchy as $children) {
            foreach ($children as $child) {
                $all[] = $child;
            }
        }
        $all = array_values(array_unique($all));

        $this->assertContains(
            $role,
            $all,
            sprintf('Role "%s" was not found in the role hierarchy. Check security.yaml.', $role)
        );
    }

    /**
     * Asserts that a secret stored in Symfony's vault can be resolved.
     *
     * ```php
     * <?php
     * $I->seeSecretCanBeResolved('DATABASE_PASSWORD');
     * ```
     *
     * @param non-empty-string $secretName The name of the secret (e.g., 'DATABASE_PASSWORD').
     */
    public function seeSecretCanBeResolved(string $secretName): void
    {
        try {
            /** @var ContainerBagInterface $params */
            $params = $this->grabService('parameter_bag');
            $value = $params->get(sprintf('env(resolve:%s)', $secretName));

            Assert::assertIsString($value, sprintf('Secret "%s" could be resolved but did not return a string.', $secretName));
        } catch (Throwable $e) {
            Assert::fail(
                sprintf(
                    'Failed to resolve secret "%s". Check your vault and decryption keys. Error: %s',
                    $secretName,
                    $e->getMessage()
                )
            );
        }
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
