<?php

namespace Minisite\Infrastructure\Persistence\Repositories;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Minisite\Domain\Entities\Config;
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

            $this->logger->debug("getAll() exit", [
                'count' => count($result),
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("getAll() failed", [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
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
        $this->logger->debug("findByKey() entry", [
            'key' => $key,
        ]);

        try {
            // Use Doctrine's native findOneBy() - no custom implementation needed
            $result = $this->findOneBy(['key' => $key]);

            $this->logger->debug("findByKey() exit", [
                'key' => $key,
                'found' => $result !== null,
                'result_id' => $result?->id,
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("findByKey() failed", [
                'key' => $key,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }

    /**
     * Save configuration (insert or update)
     */
    public function save(Config $config): Config
    {
        $this->logger->debug("save() entry", [
            'key' => $config->key,
            'type' => $config->type,
            'has_id' => $config->id !== null,
        ]);

        try {
            $this->getEntityManager()->persist($config);
            $this->getEntityManager()->flush();

            $this->logger->debug("save() exit", [
                'key' => $config->key,
                'id' => $config->id,
            ]);

            return $config;
        } catch (\Exception $e) {
            $this->logger->error("save() failed", [
                'key' => $config->key,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }

    /**
     * Delete configuration by key
     */
    public function delete(string $key): void
    {
        $this->logger->debug("delete() entry", [
            'key' => $key,
        ]);

        try {
            $config = $this->findByKey($key);
            if ($config) {
                $this->getEntityManager()->remove($config);
                $this->getEntityManager()->flush();

                $this->logger->debug("delete() exit", [
                    'key' => $key,
                    'deleted' => true,
                ]);
            } else {
                $this->logger->debug("delete() exit", [
                    'key' => $key,
                    'deleted' => false,
                    'reason' => 'config_not_found',
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error("delete() failed", [
                'key' => $key,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }
}
