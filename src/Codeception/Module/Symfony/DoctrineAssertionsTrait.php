<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Assert;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;

use function array_count_values;
use function array_filter;
use function array_keys;
use function class_exists;
use function count;
use function implode;
use function interface_exists;
use function is_array;
use function is_object;
use function is_string;
use function is_subclass_of;
use function json_encode;
use function preg_match;
use function sprintf;

trait DoctrineAssertionsTrait
{
    /**
     * Asserts that no identical SQL query was executed more than once during the
     * last request — a common symptom of an N+1 problem.
     *
     * Transaction-control statements (`START TRANSACTION`, `COMMIT`, ...) are ignored,
     * so legitimately repeated transaction boundaries are not flagged as duplicates.
     *
     * Reads Doctrine's `db` profiler collector, so it requires `doctrine/doctrine-bundle`.
     *
     * ```php
     * <?php
     * $I->dontSeeDuplicateQueries();
     * ```
     */
    public function dontSeeDuplicateQueries(): void
    {
        $statements = $this->grabExecutedStatements(__FUNCTION__);

        $duplicates = array_keys(array_filter(array_count_values($statements), static fn(int $count): bool => $count > 1));

        $this->assertSame(
            [],
            $duplicates,
            sprintf('Expected no duplicate database queries, but found %d: %s', count($duplicates), implode(' | ', $duplicates))
        );
    }

    /**
     * Returns the number of rows that match the given criteria for the
     * specified Doctrine entity.
     *
     * ```php
     * <?php
     * $I->grabNumRecords(User::class, ['status' => 'active']);
     * ```
     *
     * @template T of object
     * @param class-string<T> $entityClass Fully-qualified entity class name
     * @param array<string, mixed> $criteria    Optional query criteria
     */
    public function grabNumRecords(string $entityClass, array $criteria = []): int
    {
        return $this->_getEntityManager()->getRepository($entityClass)->count($criteria);
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
     * @template T of object
     * @param object|class-string<T> $entityOrClass
     * @return ($entityOrClass is class-string<T> ? EntityRepository<T> : EntityRepository<object>)
     */
    public function grabRepository(object|string $entityOrClass): EntityRepository
    {
        $id = is_object($entityOrClass) ? $entityOrClass::class : $entityOrClass;

        if (interface_exists($id) || is_subclass_of($id, EntityRepository::class)) {
            $repo = $this->grabService($id);
            if (!($repo instanceof EntityRepository && $repo instanceof $id)) {
                Assert::fail(sprintf("'%s' is not an entity repository", $id));
            }
            /** @var EntityRepository<T>|EntityRepository<object> $repo */
            return $repo;
        }

        $em = $this->_getEntityManager();
        if ($em->getMetadataFactory()->isTransient($id)) {
            Assert::fail(sprintf("'%s' is not a managed Doctrine entity", $id));
        }

        /** @var EntityRepository<T>|EntityRepository<object> */
        return $em->getRepository($id);
    }

    /**
     * Asserts that fewer than the given number of database queries were executed
     * during the last request — a ceiling guard against N+1 problems.
     *
     * Transaction-control statements (`START TRANSACTION`, `COMMIT`, ...) are not
     * counted, so the number reflects the application queries only.
     *
     * Reads Doctrine's `db` profiler collector, so it requires `doctrine/doctrine-bundle`.
     * Counts are environment-sensitive, so assert a ceiling rather than an exact number.
     *
     * ```php
     * <?php
     * $I->seeNumQueriesIsLessThan(5);
     * ```
     */
    public function seeNumQueriesIsLessThan(int $expectedCount): void
    {
        $actualCount = count($this->grabExecutedStatements(__FUNCTION__));

        $this->assertLessThan(
            $expectedCount,
            $actualCount,
            sprintf('Expected fewer than %d database queries, but %d were executed.', $expectedCount, $actualCount)
        );
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
     * @template T of object
     * @param int                  $expectedNum Expected count
     * @param class-string<T> $className   Entity class
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

    private function grabDoctrineCollector(string $function): DoctrineDataCollector
    {
        if (!class_exists(DoctrineDataCollector::class)) {
            Assert::fail(sprintf("The '%s' assertion requires the 'doctrine/doctrine-bundle' package.", $function));
        }

        return $this->grabCollector(
            DataCollectorName::DB,
            $function,
            sprintf("The Doctrine 'db' collector is needed to use '%s'. Is DoctrineBundle enabled with the profiler?", $function)
        );
    }

    /**
     * Flattens the executed SQL of every connection into a single list, dropping
     * transaction-control statements so the N+1 guards count application queries only.
     *
     * @return list<string>
     */
    private function grabExecutedStatements(string $function): array
    {
        $statements = [];
        foreach ($this->grabDoctrineCollector($function)->getQueries() as $connectionQueries) {
            if (!is_array($connectionQueries)) {
                continue;
            }
            foreach ($connectionQueries as $query) {
                $sql = is_array($query) ? ($query['sql'] ?? null) : null;
                if (is_string($sql) && !$this->isTransactionStatement($sql)) {
                    $statements[] = $sql;
                }
            }
        }

        return $statements;
    }

    private function isTransactionStatement(string $sql): bool
    {
        return preg_match('/^\s*("|`)?(START\s+TRANSACTION|BEGIN|COMMIT|ROLLBACK|SAVEPOINT|RELEASE\s+SAVEPOINT)\b/i', $sql) === 1;
    }
}
