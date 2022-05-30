<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Doctrine\ORM\EntityRepository;
use function class_exists;
use function get_class;
use function interface_exists;
use function is_object;
use function is_string;
use function is_subclass_of;
use function json_encode;
use function sprintf;

trait DoctrineAssertionsTrait
{
    /**
     * Retrieves number of records from database
     * 'id' is the default search parameter.
     *
     * ```php
     * <?php
     * $I->grabNumRecords('User::class', ['name' => 'davert']);
     * ```
     *
     * @param string $entityClass The entity class
     * @param array  $criteria    Optional query criteria
     */
    public function grabNumRecords(string $entityClass, array $criteria = []): int
    {
        $em         = $this->_getEntityManager();
        $repository = $em->getRepository($entityClass);

        if (empty($criteria)) {
            return (int)$repository->createQueryBuilder('a')
                ->select('count(a.id)')
                ->getQuery()
                ->getSingleScalarResult();
        }

        return $repository->count($criteria);
    }

    /**
     * Grab a Doctrine entity repository.
     * Works with objects, entities, repositories, and repository interfaces.
     *
     * ```php
     * <?php
     * $I->grabRepository($user);
     * $I->grabRepository(User::class);
     * $I->grabRepository(UserRepository::class);
     * $I->grabRepository(UserRepositoryInterface::class);
     * ```
     */
    public function grabRepository(object|string $mixed): ?EntityRepository
    {
        $entityRepoClass = EntityRepository::class;
        $isNotARepo = function () use ($mixed): void {
            $this->fail(
                sprintf("'%s' is not an entity repository", $mixed)
            );
        };
        $getRepo = function () use ($mixed, $entityRepoClass, $isNotARepo): ?EntityRepository {
            if (!$repo = $this->grabService($mixed)) return null;

            if (!$repo instanceof $entityRepoClass) {
                $isNotARepo();
                return null;
            }

            return $repo;
        };

        if (is_object($mixed)) {
            $mixed = $mixed::class;
        }

        if (interface_exists($mixed)) {
            return $getRepo();
        }

        if (!is_string($mixed) || !class_exists($mixed)) {
            $isNotARepo();
            return null;
        }

        if (is_subclass_of($mixed, $entityRepoClass)) {
            return $getRepo();
        }

        $em = $this->_getEntityManager();
        if ($em->getMetadataFactory()->isTransient($mixed)) {
            $isNotARepo();
            return null;
        }

        return $em->getRepository($mixed);
    }

    /**
     * Checks that number of given records were found in database.
     * 'id' is the default search parameter.
     *
     * ```php
     * <?php
     * $I->seeNumRecords(1, User::class, ['name' => 'davert']);
     * $I->seeNumRecords(80, User::class);
     * ```
     *
     * @param int $expectedNum Expected number of records
     * @param string $className A doctrine entity
     * @param array $criteria Optional query criteria
     */
    public function seeNumRecords(int $expectedNum, string $className, array $criteria = []): void
    {
        $currentNum = $this->grabNumRecords($className, $criteria);

        $this->assertSame(
            $expectedNum,
            $currentNum,
            sprintf(
                'The number of found %s (%d) does not match expected number %d with %s',
                $className, $currentNum, $expectedNum, json_encode($criteria, JSON_THROW_ON_ERROR)
            )
        );
    }
}