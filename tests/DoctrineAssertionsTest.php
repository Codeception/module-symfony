<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\DoctrineAssertionsTrait;
use PHPUnit\Framework\AssertionFailedError;
use Tests\App\Entity\User;
use Tests\App\Repository\UserRepository;
use Tests\App\Repository\UserRepositoryInterface;
use Tests\Support\CodeceptTestCase;

final class DoctrineAssertionsTest extends CodeceptTestCase
{
    use DoctrineAssertionsTrait;

    public function testDontSeeDuplicateQueries(): void
    {
        $this->client->request('GET', '/');

        $this->dontSeeDuplicateQueries();
    }

    public function testDontSeeDuplicateQueriesDetectsDuplicates(): void
    {
        $this->client->request('GET', '/?duplicateQueries=1');

        $this->expectException(AssertionFailedError::class);
        $this->dontSeeDuplicateQueries();
    }

    public function testGrabNumRecords(): void
    {
        $this->assertSame(1, $this->grabNumRecords(User::class));
    }

    public function testGrabRepository(): void
    {
        $this->assertInstanceOf(UserRepository::class, $this->grabRepository(User::class));
        $this->assertInstanceOf(UserRepository::class, $this->grabRepository(UserRepository::class));
        $this->assertInstanceOf(UserRepository::class, $this->grabRepository($this->grabRepository(User::class)->findOneBy(['email' => 'john_doe@gmail.com'])));
        $this->assertInstanceOf(UserRepository::class, $this->grabRepository(UserRepositoryInterface::class));
    }

    public function testSeeNumQueriesIsLessThan(): void
    {
        $this->client->request('GET', '/');

        $this->seeNumQueriesIsLessThan(3);
    }

    public function testSeeNumRecords(): void
    {
        $this->seeNumRecords(1, User::class);
    }
}
