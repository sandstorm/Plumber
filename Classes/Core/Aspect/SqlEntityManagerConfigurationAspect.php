<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Core\Aspect;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\Configuration\Exception\InvalidConfigurationException;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Persistence\Doctrine\EntityManagerFactory;
use Neos\Flow\Persistence\Doctrine\Logging\SqlLogger;
use Neos\Flow\Persistence\Doctrine\Mapping\ClassMetadataFactory;
use Neos\Flow\Persistence\Doctrine\Mapping\Driver\FlowAnnotationDriver;
use Neos\Flow\Utility\Environment;
use Neos\Utility\Files;
use Psr\Log\LoggerInterface;
use Sandstorm\Plumber\Core\Sql\Middleware\SqlProfilingMiddleware;

#[Flow\Aspect]
final class SqlEntityManagerConfigurationAspect
{
    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly Environment $environment,
        #[Flow\InjectConfiguration('persistence', 'Neos.Flow')]
        protected array $settings,
    ) {
    }

    /**
     * @throws InvalidConfigurationException
     */
    #[Flow\Around('method(Neos\Flow\Persistence\Doctrine\EntityManagerFactory->create())')]
    public function enableSqlLogger(JoinPointInterface $joinPoint): EntityManager
    {
        $config = new Configuration();

        // SANDSTORM MODIFICATION START COMPARED TO FLOW 9 EntityManagerFactory::create()
        $config->setMiddlewares([new SqlProfilingMiddleware()]);
        // SANDSTORM MODIFICATION END COMPARED TO FLOW 9 EntityManagerFactory::create()

        $config->setClassMetadataFactoryName(ClassMetadataFactory::class);

        $configuredSqlLogger = $this->settings['doctrine']['sqlLogger'] ?? null;
        if ($configuredSqlLogger && class_exists($configuredSqlLogger)) {
            $config = $this->addFlowSqlLoggerMiddleware($configuredSqlLogger, $config);
        }

        $eventManager = new EventManager();

        $flowAnnotationDriver = $this->objectManager->get(FlowAnnotationDriver::class);
        $config->setMetadataDriverImpl($flowAnnotationDriver);

        $proxyDirectory = Files::concatenatePaths([$this->environment->getPathToTemporaryDirectory(), 'Doctrine/Proxies']);
        Files::createDirectoryRecursively($proxyDirectory);
        $config->setProxyDir($proxyDirectory);
        $config->setProxyNamespace('Neos\Flow\Persistence\Doctrine\Proxies');
        $config->setAutoGenerateProxyClasses(false);

        // Set default host to 127.0.0.1 if there is no host configured but a dbname
        if (empty($this->settings['backendOptions']['host']) && !empty($this->settings['backendOptions']['dbname']) && empty($this->settings['backendOptions']['unix_socket'])) {
            $this->settings['backendOptions']['host'] = '127.0.0.1';
        }

        // The following code tries to connect first, if that succeeds, all is well. If not, the platform is fetched directly from the
        // driver - without version checks to the database server (to which no connection can be made) - and is added to the config
        // which is then used to create a new connection. This connection will then return the platform directly, without trying to
        // detect the version it runs on, which fails if no connection can be made. But the platform is used even if no connection can
        // be made, which was no problem with Doctrine DBAL 2.3. And then came version-aware drivers and platforms...
        $connection = DriverManager::getConnection($this->settings['backendOptions'], $config, $eventManager);
        try {
            $connection->connect();
        } catch (ConnectionException $exception) {
            $settings = $this->settings['backendOptions'];
            $settings['platform'] = $connection->getDriver()->getDatabasePlatform();
            $connection = DriverManager::getConnection($settings, $config, $eventManager);
        }

        /** @var EntityManagerFactory $orig */
        $orig = $joinPoint->getProxy();
        $orig->emitBeforeDoctrineEntityManagerCreation($connection, $config, $eventManager);
        $entityManager = new EntityManager($connection, $config, $eventManager);
        $flowAnnotationDriver->setEntityManager($entityManager);
        $orig->emitAfterDoctrineEntityManagerCreation($config, $entityManager);

        return $entityManager;
    }

    /**
     * Copy of the protected EntityManagerFactory::enableSqlLogger(), which the aspect cannot call.
     *
     * @param class-string $configuredSqlLogger
     * @throws InvalidConfigurationException
     */
    private function addFlowSqlLoggerMiddleware(
        string $configuredSqlLogger,
        Configuration $doctrineConfiguration,
    ): Configuration {
        $sqlLoggerInstance = $this->objectManager->get($configuredSqlLogger);
        if (!$sqlLoggerInstance instanceof SqlLogger) {
            throw new InvalidConfigurationException(sprintf('Neos.Flow.persistence.doctrine.sqlLogger must be \Neos\Flow\Persistence\Doctrine\Logging\SqlLogger, %s given.', get_class($sqlLoggerInstance)), 1426150388);
        }

        $logger = $sqlLoggerInstance->logger;
        if (!$logger instanceof LoggerInterface) {
            throw new InvalidConfigurationException(sprintf('The SqlLogger needs to get a Psr LoggerInterface injected, got "%s"', get_class($logger)), 1720548075);
        }

        return $doctrineConfiguration->setMiddlewares(
            array_merge($doctrineConfiguration->getMiddlewares(), [new Middleware($logger)]),
        );
    }
}
