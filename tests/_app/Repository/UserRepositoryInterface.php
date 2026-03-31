<?php

declare(strict_types=1);

namespace Tests\App\Repository;

use Tests\App\Entity\User;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function getByEmail(string $email): ?User;
}
