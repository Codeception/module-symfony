<?php

declare(strict_types=1);

namespace Tests\App\Repository;

use Doctrine\ORM\EntityRepository;
use Tests\App\Entity\User;

/** @extends EntityRepository<User> */
final class UserRepository extends EntityRepository implements UserRepositoryInterface
{
    public function getByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }
}
