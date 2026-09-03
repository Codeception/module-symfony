<?php

declare(strict_types=1);

namespace Tests\App\Doctrine;

use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Stands in for DoctrineBundle's `db` collector, which is not wired in this mini-app.
 * It feeds canned queries to the profiler so the query-count assertions can be tested,
 * adding a duplicate query when the request carries `?duplicateQueries=1`.
 *
 * The application queries are wrapped in `START TRANSACTION`/`COMMIT` boundaries (which
 * legitimately repeat) so the assertions are exercised against transaction-control noise:
 * those statements must not inflate the query count nor be reported as duplicates.
 */
final class DbDataCollector extends DoctrineDataCollector
{
    public function __construct()
    {
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $queries = [
            ['sql' => 'START TRANSACTION', 'executionMS' => 0.1],
            ['sql' => 'SELECT * FROM user', 'executionMS' => 0.1],
            ['sql' => 'COMMIT', 'executionMS' => 0.1],
            ['sql' => 'START TRANSACTION', 'executionMS' => 0.1],
            ['sql' => 'SELECT id FROM product WHERE category_id = 1', 'executionMS' => 0.1],
            ['sql' => 'COMMIT', 'executionMS' => 0.1],
        ];

        if ($request->query->getBoolean('duplicateQueries')) {
            $queries[] = ['sql' => 'START TRANSACTION', 'executionMS' => 0.1];
            $queries[] = ['sql' => 'SELECT * FROM user', 'executionMS' => 0.1];
            $queries[] = ['sql' => 'COMMIT', 'executionMS' => 0.1];
        }

        $this->data = [
            'queries' => ['default' => $queries],
            'connections' => ['default'],
            'managers' => ['default' => 'default'],
        ];
    }

    public function reset(): void
    {
        $this->data = [];
    }
}
