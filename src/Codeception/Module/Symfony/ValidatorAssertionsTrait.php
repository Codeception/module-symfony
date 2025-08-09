<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

trait ValidatorAssertionsTrait
{
    /**
     * Asserts that the given subject fails validation.
     * This assertion does not concern the exact number of violations.
     *
     * ```php
     * <?php
     * $I->dontSeeViolatedConstraint($subject);
     * $I->dontSeeViolatedConstraint($subject, 'propertyName');
     * $I->dontSeeViolatedConstraint($subject, 'propertyName', 'Symfony\Validator\ConstraintClass');
     * ```
     */
    public function dontSeeViolatedConstraint(object $subject, ?string $propertyPath = null, ?string $constraint = null): void
    {
        $violations = $this->getViolationsForSubject($subject, $propertyPath, $constraint);
        $this->assertCount(0, $violations, 'Constraint violations found.');
    }

    /**
     * Asserts that the given subject passes validation.
     * This assertion does not concern the exact number of violations.
     *
     * ```php
     * <?php
     * $I->seeViolatedConstraint($subject);
     * $I->seeViolatedConstraint($subject, 'propertyName');
     * $I->seeViolatedConstraint($subject, 'propertyName', 'Symfony\Validator\ConstraintClass');
     * ```
     */
    public function seeViolatedConstraint(object $subject, ?string $propertyPath = null, ?string $constraint = null): void
    {
        $violations = $this->getViolationsForSubject($subject, $propertyPath, $constraint);
        $this->assertNotCount(0, $violations, 'No constraint violations found.');
    }

    /**
     * Asserts the exact number of violations for the given subject.
     *
     * ```php
     * <?php
     * $I->seeViolatedConstraintsCount(3, $subject);
     * $I->seeViolatedConstraintsCount(2, $subject, 'propertyName');
     * ```
     */
    public function seeViolatedConstraintsCount(int $expected, object $subject, ?string $propertyPath = null, ?string $constraint = null): void
    {
        $violations = $this->getViolationsForSubject($subject, $propertyPath, $constraint);
        $this->assertCount($expected, $violations);
    }

    /**
     * Asserts that a constraint violation message or a part of it is present in the subject's violations.
     *
     * ```php
     * <?php
     * $I->seeViolatedConstraintMessage('too short', $user, 'address');
     * ```
     */
    public function seeViolatedConstraintMessage(string $expected, object $subject, string $propertyPath): void
    {
        $violations = $this->getViolationsForSubject($subject, $propertyPath);
        $containsExpected = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === $propertyPath && str_contains((string)$violation->getMessage(), $expected)) {
                $containsExpected = true;
                break;
            }
        }

        $this->assertTrue($containsExpected, 'The violation messages do not contain: ' . $expected);
    }

    /** @return ConstraintViolationInterface[] */
    protected function getViolationsForSubject(object $subject, ?string $propertyPath = null, ?string $constraint = null): array
    {
        $validator = $this->getValidatorService();
        $violations = $propertyPath ? $validator->validateProperty($subject, $propertyPath) : $validator->validate($subject);

        $violations = iterator_to_array($violations);

        if ($constraint !== null) {
            return (array)array_filter(
                $violations,
                static fn (ConstraintViolationInterface $violation): bool => get_class((object)$violation->getConstraint()) === $constraint &&
                    ($propertyPath === null || $violation->getPropertyPath() === $propertyPath)
            );
        }

        return $violations;
    }

    protected function getValidatorService(): ValidatorInterface
    {
        /** @var ValidatorInterface $validator */
        $validator = $this->grabService(ValidatorInterface::class);
        return $validator;
    }
}
