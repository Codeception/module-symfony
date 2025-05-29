<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Assert;

use function interface_exists;
use function is_object;
use function is_subclass_of;
use function json_encode;
use function sprintf;

trait DoctrineAssertionsTrait
{
    /**
     * Returns the number of rows that match the given criteria for the
     * specified Doctrine entity.
     *
     * ```php
     * <?php
     * $I->grabNumRecords(User::class, ['status' => 'active']);
     * ```
     *
     * @param class-string<object> $entityClass Fully-qualified entity class name
     * @param array<string, mixed> $criteria    Optional query criteria
     */
    public function grabNumRecords(string $entityClass, array $criteria = []): int
    {
        $em         = $this->_getEntityManager();
        $repository = $em->getRepository($entityClass);

        if ($criteria === []) {
            return (int)$repository->createQueryBuilder('e')
                ->select('count(e.id)')
                ->getQuery()
                ->getSingleScalarResult();
        }

        return $repository->count($criteria);
    }

    /**
     * Obtains the Doctrine entity repository {@see EntityRepository}
     * for a given entity, repository class or interface.
     *
     * ```php
     * <?php
     * $I->grabRepository($user);                          // entity object
     * $I->grabRepository(User::class);                    // entity class
     * $I->grabRepository(UserRepository::class);          // concrete repo
     * $I->grabRepository(UserRepositoryInterface::class); // interface
     * ```
     *
     * @param  object|class-string $mixed
     * @return EntityRepository<object>
     */
    public function grabRepository(object|string $mixed): EntityRepository
    {
        $id = is_object($mixed) ? $mixed::class : $mixed;

        if (interface_exists($id) || is_subclass_of($id, EntityRepository::class)) {
            $repo = $this->grabService($id);
            if (!($repo instanceof EntityRepository && $repo instanceof $id)) {
                Assert::fail(sprintf("'%s' is not an entity repository", $id));
            }
            return $repo;
        }

        $em = $this->_getEntityManager();
        if ($em->getMetadataFactory()->isTransient($id)) {
            Assert::fail(sprintf("'%s' is not a managed Doctrine entity", $id));
        }

        return $em->getRepository($id);
    }

    /**
     * Asserts that a given number of records exists for the entity.
     * 'id' is the default search parameter.
     *
     * ```php
     * <?php
     * $I->seeNumRecords(1, User::class, ['name' => 'davert']);
     * $I->seeNumRecords(80, User::class);
     * ```
     *
     * @param int                  $expectedNum Expected count
     * @param class-string<object> $className   Entity class
     * @param array<string, mixed> $criteria    Optional criteria
     */
    public function seeNumRecords(int $expectedNum, string $className, array $criteria = []): void
    {
        $currentNum = $this->grabNumRecords($className, $criteria);

        $this->assertSame(
            $expectedNum,
            $currentNum,
            sprintf(
                'The number of found %s (%d) does not match expected number %d with %s',
                $className,
                $currentNum,
                $expectedNum,
                json_encode($criteria, JSON_THROW_ON_ERROR)
            )
        );
    }
}
