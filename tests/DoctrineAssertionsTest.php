<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\DoctrineAssertionsTrait;
use Tests\App\Entity\User;
use Tests\App\Repository\UserRepository;
use Tests\App\Repository\UserRepositoryInterface;
use Tests\Support\CodeceptTestCase;

final class DoctrineAssertionsTest extends CodeceptTestCase
{
    use DoctrineAssertionsTrait;

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

    public function testSeeNumRecords(): void
    {
        $this->seeNumRecords(1, User::class);
    }
}
