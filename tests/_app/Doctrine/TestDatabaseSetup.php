<?php

declare(strict_types=1);

namespace Tests\App\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Tests\App\Entity\User;

final class TestDatabaseSetup
{
    public static function init(EntityManagerInterface $entityManager): void
    {
        $entityManager->clear();

        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $user = User::create('john_doe@gmail.com', 'secret', ['ROLE_TEST']);
        $entityManager->persist($user);
        $entityManager->flush();
        $entityManager->clear();
    }
}
