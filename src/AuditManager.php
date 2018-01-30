<?php declare(strict_types=1);

namespace SimpleThings\EntityAudit;

use Doctrine\Common\EventManager;
use Doctrine\ORM\EntityManagerInterface;
use Kafka\Producer;
use SimpleThings\EntityAudit\EventListener\CreateSchemaListener;
use SimpleThings\EntityAudit\EventListener\LogRevisionsListener;
use SimpleThings\EntityAudit\Metadata\MetadataFactory;

/**
 * Audit Manager grants access to metadata and configuration
 * and has a factory method for audit queries.
 */
class AuditManager
{
    /**
     * @var AuditConfiguration
     */
    private $config;

    /**
     * @var MetadataFactory
     */
    private $metadataFactory;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var Producer
     */
    private $kafkaProducer;

    private $userId;

    /**
     * @param EntityManagerInterface $entityManager
     * @param AuditConfiguration $config
     */
    public function __construct(EntityManagerInterface $entityManager, AuditConfiguration $config, Producer $producer = null, $userId = null)
    {
        $this->entityManager = $entityManager;
        $this->config = $config;
        $this->metadataFactory = new Metadata\MetadataFactory($this->entityManager, $config->getMetadataDriver());
        $this->kafkaProducer = $producer;
        $this->userId = $userId;
        $this->registerEvents($entityManager->getEventManager());
    }

    public function getProducer()
    {
        return $this->kafkaProducer;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function getMetadataFactory()
    {
        return $this->metadataFactory;
    }

    public function getConfiguration()
    {
        return $this->config;
    }

    public function createAuditReader()
    {
        return new AuditReader($this->entityManager, $this->config, $this->metadataFactory);
    }

    protected function registerEvents(EventManager $evm)
    {
        $evm->addEventSubscriber(new CreateSchemaListener($this));
        $evm->addEventSubscriber(new LogRevisionsListener($this));
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @return AuditManager
     */
    public static function create(EntityManagerInterface $entityManager)
    {
        return new self($entityManager, AuditConfiguration::createWithAnnotationDriver());
    }
}
