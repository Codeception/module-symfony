<?php

declare(strict_types=1);

namespace Tests\App\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;

final class DoctrineSetup
{
    private static ?EntityManagerInterface $entityManager = null;

    public static function createConnection(): Connection
    {
        return self::createEntityManager()->getConnection();
    }

    public static function createEntityManager(): EntityManagerInterface
    {
        if (self::$entityManager !== null) {
            return self::$entityManager;
        }

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        return self::$entityManager = self::makeEntityManager($connection);
    }

    /**
     * Rebuilds the manager on the same connection, mirroring what
     * ManagerRegistry::resetManager() does for a lazy service. Reusing the connection
     * matters here: the fixture database is in-memory, so reconnecting would lose the schema.
     */
    public static function resetEntityManager(): void
    {
        $connection = self::createEntityManager()->getConnection();

        self::$entityManager = self::makeEntityManager($connection);
    }

    private static function makeEntityManager(Connection $connection): EntityManagerInterface
    {
        $config = self::createConfiguration();

        if (method_exists(EntityManager::class, 'create')) {
            return EntityManager::create($connection, $config);
        }

        return new EntityManager($connection, $config);
    }

    private static function createConfiguration(): Configuration
    {
        $entityDir = dirname(__DIR__) . '/Entity';

        if (method_exists(ORMSetup::class, 'createAttributeMetadataConfig')) {
            $config = ORMSetup::createAttributeMetadataConfig([$entityDir], true);
        } else {
            $config = ORMSetup::createAttributeMetadataConfiguration([$entityDir], true);
        }

        $proxyDir = sys_get_temp_dir() . '/doctrine-proxies';
        if (!is_dir($proxyDir)) {
            mkdir($proxyDir, 0o777, true);
        }

        $config->setProxyDir($proxyDir);
        $config->setProxyNamespace('TestsProxies');
        $config->setAutoGenerateProxyClasses(true);

        if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        return $config;
    }
}
