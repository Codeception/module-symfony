<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\SecurityAssertionsTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\BrowserKit\Cookie;
use Tests\App\Entity\User;
use Tests\Support\CodeceptTestCase;

final class SecurityAssertionsTest extends CodeceptTestCase
{
    use SecurityAssertionsTrait;

    protected function grabSecurityService(): Security
    {
        return new Security($this->_getContainer());
    }

    public function testDontSeeAuthentication(): void
    {
        $this->client->request('GET', '/dashboard');
        $this->dontSeeAuthentication();
    }

    public function testDontSeeRememberedAuthentication(): void
    {
        $this->client->loginUser($this->createTestUser(['ROLE_USER']));
        $this->dontSeeRememberedAuthentication();
    }

    public function testSeeAuthentication(): void
    {
        $this->client->loginUser($this->createTestUser(['ROLE_USER']));
        $this->seeAuthentication();
    }

    public function testSeeRememberedAuthentication(): void
    {
        $this->client->loginUser($this->createTestUser(['ROLE_USER']));
        $this->client->getCookieJar()->set(new Cookie('REMEMBERME', 'test-remember'));
        $this->seeRememberedAuthentication();
    }

    public function testSeeUserHasRole(): void
    {
        $this->client->loginUser($this->createTestUser(['ROLE_USER', 'ROLE_ADMIN']));
        $this->seeUserHasRole('ROLE_ADMIN');
    }

    public function testSeeUserHasRoles(): void
    {
        $this->client->loginUser($this->createTestUser(['ROLE_USER', 'ROLE_CUSTOMER']));
        $this->seeUserHasRoles(['ROLE_USER', 'ROLE_CUSTOMER']);
    }

    public function testSeeUserPasswordDoesNotNeedRehash(): void
    {
        $this->client->loginUser($this->createTestUser(['ROLE_USER']));
        $this->seeUserPasswordDoesNotNeedRehash();
    }

    public function testSeeFirewallIsConfigured(): void
    {
        $this->seeFirewallIsConfigured('main');
    }

    private function createTestUser(array $roles): User
    {
        return User::create('john_doe@gmail.com', $this->grabPasswordHasherService()->hashPassword(User::create('tmp', ''), '123456'), $roles);
    }
}
