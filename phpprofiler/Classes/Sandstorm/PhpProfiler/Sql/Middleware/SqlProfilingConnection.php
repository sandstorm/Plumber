<?php

declare(strict_types=1);

namespace Sandstorm\PhpProfiler\Sql\Middleware;

use Neos\Flow\Annotations as Flow;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Sandstorm\PhpProfiler\Profiler;

#[Flow\Proxy(false)]
final class SqlProfilingConnection extends AbstractConnectionMiddleware
{
    /** @internal This connection can be only instantiated by its driver. */
    public function __construct(ConnectionInterface $connection, private readonly Profiler $profiler)
    {
        parent::__construct($connection);
    }

    public function prepare(string $sql): DriverStatement
    {
        return new SqlProfilingStatement(
            parent::prepare($sql),
            $sql,
            $this->profiler
        );
    }

    public function query(string $sql): Result
    {
        $params = [];
        $params['_sql'] = $sql;
        $this->profiler->getRun()->startTimer('SQL Query', $params);
        $this->profiler->getRun()->logSqlQuery($sql);
        try {
            return parent::query($sql);
        } finally {
            $this->profiler->getRun()->stopTimer('SQL Query');
        }
    }

    public function exec(string $sql): int
    {
        $params['_sql'] = $sql;
        $this->profiler->getRun()->startTimer('SQL Query', $params);
        $this->profiler->getRun()->logSqlQuery($sql);
        try {
            return parent::exec($sql);
        } finally {
            $this->profiler->getRun()->stopTimer('SQL Query');
        }
    }
}
