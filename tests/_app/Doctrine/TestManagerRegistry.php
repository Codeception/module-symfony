<?php

declare(strict_types=1);

namespace Tests\App\Doctrine;

use Doctrine\Persistence\AbstractManagerRegistry;
use Doctrine\Persistence\Proxy;

/**
 * Minimal stand-in for DoctrineBundle's registry: the fixture app wires Doctrine by hand
 * and has no `doctrine` service to reset the entity manager through.
 *
 * The declared return types and the explicit $proxyInterfaceName keep this compatible with
 * doctrine/persistence 3.x (untyped abstracts, argument required) and 4.x (typed, optional).
 */
final class TestManagerRegistry extends AbstractManagerRegistry
{
    public int $resets = 0;

    public function __construct()
    {
        parent::__construct(
            'ORM',
            ['default' => 'doctrine.dbal.default_connection'],
            ['default' => 'doctrine.orm.entity_manager'],
            'default',
            'default',
            Proxy::class
        );
    }

    protected function getService(string $name): object
    {
        if ($name === 'doctrine.dbal.default_connection') {
            return DoctrineSetup::createConnection();
        }

        return DoctrineSetup::createEntityManager();
    }

    protected function resetService(string $name): void
    {
        ++$this->resets;

        DoctrineSetup::resetEntityManager();
    }
}
