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
use function is_subclass_of;
use function is_writable;
use function json_encode;
use function sprintf;
use function trim;

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
     * The argument is treated as a connection name first.
     * If no connection with that name exists, the method falls back to
     * resolving it as an entity manager name and uses that manager's connection.
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
            $connection = $this->resolveDoctrineConnection($connectionName);

            $connection->executeQuery($connection->getDatabasePlatform()->getDummySelectSQL());
        } catch (Throwable $e) {
            Assert::fail(sprintf('Doctrine connection/entity manager "%s" failed: %s', $connectionName, $e->getMessage()));
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
            $em = $this->resolveEntityManager($entityManagerName);
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
     * This assertion is only meaningful when Doctrine's legacy proxy system is in use.
     * If no proxy dir is configured (for example with native lazy objects),
     * the assertion is skipped.
     *
     * ```php
     * <?php
     * $I->seeDoctrineProxyDirIsWritable();
     * $I->seeDoctrineProxyDirIsWritable('custom');
     * ```
     */
    public function seeDoctrineProxyDirIsWritable(string $entityManagerName = 'default'): void
    {
        $em = $this->resolveEntityManager($entityManagerName);
        $proxyDir = $em->getConfiguration()->getProxyDir();

        if ($proxyDir === null || trim($proxyDir) === '') {
            Assert::markTestSkipped(
                sprintf(
                    'Doctrine proxy dir is not configured for EM "%s". This can be expected when native lazy objects are used.',
                    $entityManagerName
                )
            );
        }

        $this->assertTrue(is_dir($proxyDir), sprintf('Doctrine proxy dir does not exist: %s', $proxyDir));
        $this->assertTrue(is_writable($proxyDir), sprintf('Doctrine proxy dir is not writable: %s', $proxyDir));
    }

    private function resolveDoctrineConnection(string $connectionOrEntityManagerName): Connection
    {
        $doctrine = $this->resolveManagerRegistry();
        if ($doctrine instanceof ManagerRegistry) {
            try {
                $connection = $doctrine->getConnection($connectionOrEntityManagerName);
                if ($connection instanceof Connection) {
                    return $connection;
                }

                Assert::fail(
                    sprintf(
                        'Doctrine connection "%s" is not an instance of %s.',
                        $connectionOrEntityManagerName,
                        Connection::class
                    )
                );
            } catch (Throwable) {
                $manager = $doctrine->getManager($connectionOrEntityManagerName);
                if ($manager instanceof EntityManagerInterface) {
                    return $manager->getConnection();
                }

                Assert::fail(
                    sprintf(
                        'Doctrine manager "%s" is not an instance of %s.',
                        $connectionOrEntityManagerName,
                        EntityManagerInterface::class
                    )
                );
            }
        }

        if ($connectionOrEntityManagerName !== 'default') {
            Assert::fail(
                sprintf(
                    'Cannot resolve Doctrine connection/entity manager "%s" without the "doctrine" ManagerRegistry service.',
                    $connectionOrEntityManagerName
                )
            );
        }

        return $this->_getEntityManager()->getConnection();
    }

    private function resolveEntityManager(string $entityManagerName): EntityManagerInterface
    {
        $doctrine = $this->resolveManagerRegistry();
        if ($doctrine instanceof ManagerRegistry) {
            $manager = $doctrine->getManager($entityManagerName);
            if ($manager instanceof EntityManagerInterface) {
                return $manager;
            }

            Assert::fail(
                sprintf(
                    'Doctrine manager "%s" is not an instance of %s.',
                    $entityManagerName,
                    EntityManagerInterface::class
                )
            );
        }

        if ($entityManagerName !== 'default') {
            Assert::fail(
                sprintf(
                    'Cannot resolve Doctrine entity manager "%s" without the "doctrine" ManagerRegistry service.',
                    $entityManagerName
                )
            );
        }

        return $this->_getEntityManager();
    }

    private function resolveManagerRegistry(): ?ManagerRegistry
    {
        $container = $this->_getContainer();

        if ($container->has('doctrine')) {
            $service = $container->get('doctrine');
            if ($service instanceof ManagerRegistry) {
                return $service;
            }
        }

        if ($container->has(ManagerRegistry::class)) {
            $service = $container->get(ManagerRegistry::class);
            if ($service instanceof ManagerRegistry) {
                return $service;
            }
        }

        return null;
    }
}
