<?php
declare(strict_types=1);
namespace Sandstorm\Plumber\Core\Sql\Middleware;

use Neos\Flow\Annotations as Flow;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Sandstorm\Plumber\Core\Profiler;
use SensitiveParameter;

#[Flow\Proxy(false)]
final class SqlProfilingDriver extends AbstractDriverMiddleware
{

    /** @internal This driver can be only instantiated by its middleware. */
    public function __construct(DriverInterface $driver, private readonly Profiler $profiler)
    {
        parent::__construct($driver);
    }

    /**
     * {@inheritDoc}
     */
    public function connect(
        #[SensitiveParameter]
        array $params
    )
    {
        return new SqlProfilingConnection(
            parent::connect($params),
            $this->profiler
        );
    }
}
