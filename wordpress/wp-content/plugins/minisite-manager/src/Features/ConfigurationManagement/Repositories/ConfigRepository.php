<?php

namespace Minisite\Features\ConfigurationManagement\Repositories;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Minisite\Features\ConfigurationManagement\Domain\Entities\Config;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Config Repository using Doctrine ORM
 *
 * Note: Naming is agnostic (not "DoctrineConfigRepository") since we have
 * only one implementation. If multiple implementations are needed in future
 * (e.g., for testing, caching, or alternative storage), rename to distinguish.
 */
class ConfigRepository extends EntityRepository implements ConfigRepositoryInterface
{
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
        $this->logger = LoggingServiceProvider::getFeatureLogger('config-repository');
    }

    /**
     * Get all configurations (ordered by key)
     */
    public function getAll(): array
    {
        $this->logger->debug("getAll() entry");

        try {
            $result = $this->createQueryBuilder('c')
                ->orderBy('c.key', 'ASC')
                ->getQuery()
                ->getResult();

            $this->logger->debug("getAll() exit", array(
                'count' => count($result),
            ));

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("getAll() failed", array(
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Find configuration by key
     *
     * Uses Doctrine's native findOneBy() method - this is just a convenience wrapper
     * that adds logging and maintains the interface contract.
     */
    public function findByKey(string $key): ?Config
    {
        $this->logger->debug("findByKey() entry", array(
            'key' => $key,
        ));

        try {
            // Use Doctrine's native findOneBy() - no custom implementation needed
            $result = $this->findOneBy(array('key' => $key));

            $this->logger->debug("findByKey() exit", array(
                'key' => $key,
                'found' => $result !== null,
                'result_id' => $result?->id,
            ));

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("findByKey() failed", array(
                'key' => $key,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Save configuration (insert or update)
     */
    public function save(Config $config): Config
    {
        $this->logger->debug("save() entry", array(
            'key' => $config->key,
            'type' => $config->type,
            'has_id' => $config->id !== null,
        ));

        try {
            $this->getEntityManager()->persist($config);
            $this->getEntityManager()->flush();

            $this->logger->debug("save() exit", array(
                'key' => $config->key,
                'id' => $config->id,
            ));

            return $config;
        } catch (\Exception $e) {
            $this->logger->error("save() failed", array(
                'key' => $config->key,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Delete configuration by key
     */
    public function delete(string $key): void
    {
        $this->logger->debug("delete() entry", array(
            'key' => $key,
        ));

        try {
            $config = $this->findByKey($key);
            if ($config) {
                $this->getEntityManager()->remove($config);
                $this->getEntityManager()->flush();

                $this->logger->debug("delete() exit", array(
                    'key' => $key,
                    'deleted' => true,
                ));
            } else {
                $this->logger->debug("delete() exit", array(
                    'key' => $key,
                    'deleted' => false,
                    'reason' => 'config_not_found',
                ));
            }
        } catch (\Exception $e) {
            $this->logger->error("delete() failed", array(
                'key' => $key,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }
}
