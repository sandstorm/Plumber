<?php
namespace Sandstorm\PhpProfiler\Aspect;


use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Sandstorm\PhpProfiler\Sql\Middleware\SqlProfilingMiddleware;
use Sandstorm\PhpProfiler\Sql\SqlStatementProfiler;

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
