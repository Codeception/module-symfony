<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\SessionAssertionsTrait;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Tests\App\Entity\User;
use Tests\App\Repository\UserRepository;
use Tests\Support\CodeceptTestCase;

final class SessionAssertionsTest extends CodeceptTestCase
{
    use SessionAssertionsTrait;

    public function testAmLoggedInAs(): void
    {
        $this->amLoggedInAs($this->getTestUser());
        $this->client->request('GET', '/dashboard');
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $this->assertStringContainsString('You are in the Dashboard!', $this->client->getResponse()->getContent());
    }

    public function testAmLoggedInWithToken(): void
    {
        $user = $this->getTestUser();
        $this->amLoggedInWithToken(new PostAuthenticationToken($user, 'main', $user->getRoles()));
        $this->client->request('GET', '/dashboard');
        $this->assertStringContainsString('You are in the Dashboard!', $this->client->getResponse()->getContent());
    }

    public function testDontSeeInSession(): void
    {
        $this->client->request('GET', '/');
        $this->dontSeeInSession('_security_main');

        $this->initSession(['key1' => 'value1']);
        $this->dontSeeInSession('missing');
        $this->dontSeeInSession('key1', 'other');
    }

    public function testGoToLogoutPath(): void
    {
        $this->amLoggedInAs($this->getTestUser());
        $this->client->request('GET', '/dashboard');
        $this->assertStringContainsString('You are in the Dashboard!', $this->client->getResponse()->getContent());

        $this->goToLogoutPath();
        $this->assertSame('/logout', $this->client->getRequest()->getPathInfo());
        $this->assertSame(302, $this->client->getResponse()->getStatusCode());
        $this->client->followRedirect();

        $this->assertSame('/', $this->client->getRequest()->getPathInfo());
    }

    public function testLogout(): void
    {
        $this->amLoggedInAs($this->getTestUser());
        $this->logout();
        $this->client->request('GET', '/dashboard');
        $this->assertSame(302, $this->client->getResponse()->getStatusCode());
    }

    public function testLogoutProgrammatically(): void
    {
        $this->amLoggedInAs($this->getTestUser());
        $this->logoutProgrammatically();
        $this->client->request('GET', '/dashboard');
        $this->assertSame(302, $this->client->getResponse()->getStatusCode());
    }

    public function testSeeInSession(): void
    {
        $this->initSession(['key1' => 'value1']);
        $this->seeInSession('key1');
        $this->seeInSession('key1', 'value1');
    }

    public function testSeeSessionHasValues(): void
    {
        $this->initSession(['key1' => 'value1', 'key2' => 'value2']);
        $this->seeSessionHasValues(['key1', 'key2']);
        $this->seeSessionHasValues(['key1' => 'value1', 'key2' => 'value2']);
    }

    private function getTestUser(): User
    {
        return $this->grabService(UserRepository::class)->getByEmail('john_doe@gmail.com') ?? $this->fail('User not found');
    }

    private function initSession(array $data): void
    {
        $session = $this->getCurrentSession();
        foreach ($data as $key => $value) {
            $session->set($key, $value);
        }
        $session->save();
    }
}
