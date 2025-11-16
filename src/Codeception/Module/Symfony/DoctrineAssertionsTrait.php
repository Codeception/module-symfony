<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\SchemaValidator;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Assert;
use Throwable;

use function implode;
use function interface_exists;
use function is_dir;
use function is_object;
use function is_string;
use function is_subclass_of;
use function is_writable;
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

    /**
     * Asserts that Doctrine can connect to a database.
     *
     * ```php
     * <?php
     * $I->seeDoctrineDatabaseIsUp();
     * $I->seeDoctrineDatabaseIsUp('custom');
     * ```
     *
     * @param non-empty-string $connectionName The name of the Doctrine connection to check.
     */
    public function seeDoctrineDatabaseIsUp(string $connectionName = 'default'): void
    {
        try {
            /** @var ManagerRegistry $doctrine */
            $doctrine   = $this->grabService('doctrine');
            /** @var Connection $connection */
            $connection = $doctrine->getConnection($connectionName);
            $connection->executeQuery($connection->getDatabasePlatform()->getDummySelectSQL());
        } catch (Throwable $e) {
            Assert::fail(sprintf('Doctrine connection "%s" failed: %s', $connectionName, $e->getMessage()));
        }
    }

    /**
     * Asserts that the Doctrine mapping is valid and the DB schema is in sync for one entity manager.
     * Programmatic equivalent of `bin/console doctrine:schema:validate`.
     *
     * ```php
     * <?php
     * $I->seeDoctrineSchemaIsValid();
     * $I->seeDoctrineSchemaIsValid('custom');
     * ```
     *
     * @param non-empty-string $entityManagerName
     */
    public function seeDoctrineSchemaIsValid(string $entityManagerName = 'default'): void
    {
        try {
            /** @var ManagerRegistry $doctrine */
            $doctrine = $this->grabService('doctrine');
            /** @var EntityManagerInterface $em */
            $em = $doctrine->getManager($entityManagerName);
            $validator = new SchemaValidator($em);
            $errors = $validator->validateMapping();
            $errorMessages = [];
            foreach ($errors as $className => $classErrors) {
                $errorMessages[] = sprintf(' - %s: %s', $className, implode('; ', $classErrors));
            }
            $this->assertEmpty(
                $errors,
                sprintf(
                    "The Doctrine mapping is invalid for the '%s' entity manager:\n%s",
                    $entityManagerName,
                    implode("\n", $errorMessages)
                )
            );

            if (!$validator->schemaInSyncWithMetadata()) {
                Assert::fail(
                    sprintf(
                        'The database schema is not in sync with the current mapping for the "%s" entity manager. Generate and run a new migration.',
                        $entityManagerName
                    )
                );
            }
        } catch (Throwable $e) {
            Assert::fail(
                sprintf('Could not validate Doctrine schema for the "%s" entity manager: %s', $entityManagerName, $e->getMessage())
            );
        }
    }

    /**
     * Asserts that Doctrine proxy directory is writable for a given entity manager.
     *
     * ```php
     * <?php
     * $I->seeDoctrineProxyDirIsWritable();
     * $I->seeDoctrineProxyDirIsWritable('custom');
     * ```
     */
    public function seeDoctrineProxyDirIsWritable(string $entityManagerName = 'default'): void
    {
        /** @var ManagerRegistry $doctrine */
        $doctrine = $this->grabService('doctrine');
        /** @var EntityManagerInterface $em */
        $em = $doctrine->getManager($entityManagerName);
        $proxyDir = $em->getConfiguration()->getProxyDir();

        $this->assertIsString($proxyDir, sprintf('Doctrine proxy dir is not configured for EM "%s".', $entityManagerName));
        $this->assertTrue(is_dir($proxyDir), sprintf('Doctrine proxy dir does not exist: %s', $proxyDir));
        $this->assertTrue(is_writable($proxyDir), sprintf('Doctrine proxy dir is not writable: %s', $proxyDir));
    }
}
