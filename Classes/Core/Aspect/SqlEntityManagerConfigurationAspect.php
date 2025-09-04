<?php
namespace Sandstorm\Plumber\Core\Aspect;


use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Sandstorm\Plumber\Core\Sql\Middleware\SqlProfilingMiddleware;
use Sandstorm\Plumber\Core\Sql\SqlStatementProfiler;

#[Flow\Aspect]
class SqlEntityManagerConfigurationAspect {

    #[Flow\Around("method(Neos\Flow\Persistence\Doctrine\EntityManagerFactory->enableSqlLogger())")]
    public function enableSqlLogger(JoinPointInterface $joinPoint): mixed {
        $configuredSqlLogger = $joinPoint->getMethodArgument('configuredSqlLogger');
        $doctrineConfiguration = $joinPoint->getMethodArgument('doctrineConfiguration');

        if ($configuredSqlLogger === SqlStatementProfiler::class) {
            return $doctrineConfiguration->setMiddlewares(array_merge($doctrineConfiguration->getMiddlewares(), [new SqlProfilingMiddleware()]));
        }
        return $doctrineConfiguration;
    }
}
