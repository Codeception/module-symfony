<?php

declare(strict_types=1);

namespace Tests\App\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
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
        if (self::$entityManager !== null && self::$entityManager->isOpen()) {
            return self::$entityManager;
        }

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

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        if (method_exists(EntityManager::class, 'create')) {
            self::$entityManager = EntityManager::create($connection, $config);
        } else {
            self::$entityManager = new EntityManager($connection, $config);
        }

        return self::$entityManager;
    }
}
