<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\ValidatorAssertionsTrait;
use Symfony\Component\Validator\Constraints as Assert;
use Tests\App\Entity\User;
use Tests\Support\CodeceptTestCase;

final class ValidatorAssertionsTest extends CodeceptTestCase
{
    use ValidatorAssertionsTrait;

    public function testDontSeeViolatedConstraint(): void
    {
        $user = User::create('test@example.com', 'password123');
        $this->dontSeeViolatedConstraint($user);
        $this->dontSeeViolatedConstraint($user, 'email');
        $this->dontSeeViolatedConstraint($user, 'email', Assert\Email::class);

        $user->setEmail('invalid_email');
        $this->dontSeeViolatedConstraint($user, 'password');

        $user->setEmail('test@example.com');
        $user->setPassword('weak');
        $this->dontSeeViolatedConstraint($user, 'email');
        $this->dontSeeViolatedConstraint($user, 'password', Assert\NotBlank::class);
    }

    public function testSeeViolatedConstraint(): void
    {
        $user = User::create('invalid_email', 'password123');
        $this->seeViolatedConstraint($user);
        $this->seeViolatedConstraint($user, 'email');

        $user->setEmail('test@example.com');
        $user->setPassword('weak');
        $this->seeViolatedConstraint($user);
        $this->seeViolatedConstraint($user, 'password');
        $this->seeViolatedConstraint($user, 'password', Assert\Length::class);
    }

    public function testSeeViolatedConstraintsCount(): void
    {
        $user = User::create('invalid_email', 'weak');
        $this->seeViolatedConstraintsCount(2, $user);
        $this->seeViolatedConstraintsCount(1, $user, 'email');

        $user->setEmail('test@example.com');
        $this->seeViolatedConstraintsCount(1, $user);
        $this->seeViolatedConstraintsCount(0, $user, 'email');
    }

    public function testSeeViolatedConstraintMessage(): void
    {
        $user = User::create('invalid_email', 'weak');
        $this->seeViolatedConstraintMessage('valid email', $user, 'email');

        $user->setEmail('');
        $this->seeViolatedConstraintMessage('should not be blank', $user, 'email');
        $this->seeViolatedConstraintMessage('This value is too short', $user, 'email');
    }
}
