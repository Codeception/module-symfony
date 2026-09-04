<?php

declare(strict_types=1);

namespace Tests\App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Tests\App\Entity\User;

use function in_array;

/**
 * Implements VoterInterface instead of extending Voter, whose abstract method signature
 * changed across the Symfony versions supported by this module.
 */
final class UserVoter implements VoterInterface
{
    public const EDIT = 'USER_EDIT';

    /**
     * @param mixed[] $attributes
     */
    public function vote(TokenInterface $token, mixed $subject, array $attributes, mixed ...$args): int
    {
        if (!in_array(self::EDIT, $attributes, true) || !$subject instanceof User) {
            return self::ACCESS_ABSTAIN;
        }

        $user = $token->getUser();

        return $user instanceof User && $user->getUserIdentifier() === $subject->getUserIdentifier()
            ? self::ACCESS_GRANTED
            : self::ACCESS_DENIED;
    }
}
