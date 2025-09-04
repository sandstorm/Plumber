<?php

declare(strict_types=1);

namespace Sandstorm\PhpProfiler\Sql\Middleware;

use Neos\Flow\Annotations as Flow;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Sandstorm\PhpProfiler\Profiler;

#[Flow\Proxy(false)]
final class SqlProfilingMiddleware implements MiddlewareInterface
{
    public function wrap(DriverInterface $driver): DriverInterface
    {

        return new SqlProfilingDriver($driver, Profiler::getInstance());
    }
}
