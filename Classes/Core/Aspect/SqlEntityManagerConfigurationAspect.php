<?php
namespace Sandstorm\Plumber\Core\Aspect;


use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Persistence\Doctrine\Mapping\ClassMetadataFactory;
use Neos\Flow\Persistence\Doctrine\Mapping\Driver\FlowAnnotationDriver;
use Neos\Flow\Reflection\ReflectionService;
use Neos\Flow\Utility\Environment;
use Neos\Utility\Files;
use Sandstorm\Plumber\Core\Sql\Middleware\SqlProfilingMiddleware;

#[Flow\Aspect]
class SqlEntityManagerConfigurationAspect {

    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @Flow\Inject
     * @var ReflectionService
     */
    protected $reflectionService;

    /**
     * @Flow\Inject
     * @var Environment
     */
    protected $environment;

    /**
     * @Flow\InjectConfiguration(package="Neos.Flow", path="persistence")
     * @var array
     */
    protected $settings = [];


    #[Flow\Around("method(Neos\Flow\Persistence\Doctrine\EntityManagerFactory->create())")]
    public function enableSqlLogger(JoinPointInterface $joinPoint): mixed {
        $config = new Configuration();

        // SANDSTORM MODIFICATION START COMPARED TO NEOS 8.4
        $config->setMiddlewares([new SqlProfilingMiddleware()]);
        // SANDSTORM MODIFICATION END COMPARED TO NEOS 8.4

        $config->setClassMetadataFactoryName(ClassMetadataFactory::class);

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

        $orig = $joinPoint->getProxy();
        $orig->emitBeforeDoctrineEntityManagerCreation($connection, $config, $eventManager);
        $entityManager = EntityManager::create($connection, $config, $eventManager);
        $flowAnnotationDriver->setEntityManager($entityManager);
        $orig->emitAfterDoctrineEntityManagerCreation($config, $entityManager);

        return $entityManager;
    }
}
