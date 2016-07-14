<?php
namespace TYPO3\TYPO3CR\Domain\Service;

/*
 * This file is part of the TYPO3.TYPO3CR package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Log\SystemLoggerInterface;
use TYPO3\Flow\Object\ObjectManagerInterface;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;
use TYPO3\Flow\Reflection\ObjectAccess;
use TYPO3\Flow\Reflection\ReflectionService;
use TYPO3\Flow\Utility\Arrays;
use TYPO3\TYPO3CR\Domain\Factory\NodeFactory;
use TYPO3\TYPO3CR\Domain\Model\ContentProxyProxyableEntityInterface;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Repository\ContentProxyableRepository;
use TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository;
use TYPO3\TYPO3CR\Exception;

/**
 * Manage synchronization between the content repository and doctrine entities
 *
 * @Flow\Scope("singleton")
 * @api
 */
class ContentProxyableEntityService
{
    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @Flow\Inject
     * @var ContentObjectProxyService
     */
    protected $contentObjectProxyService;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @var ContentProxyableRepository
     */
    protected $contentProxyProxyableRepository;

    /**
     * @Flow\Inject
     * @var NodeDataRepository
     */
    protected $nodeDataRepository;

    /**
     * @Flow\Inject
     * @var NodeFactory
     */
    protected $nodeFactory;

    /**
     * @Flow\Inject
     * @var SystemLoggerInterface
     */
    protected $logger;

    /**
     * Return all proxyable entities configuration
     *
     * @return array
     */
    public function getEntities()
    {
        return self::getProxyableEntities($this->objectManager);
    }

    /**
     * @param string $identifier
     * @param Context $context
     * @return array
     */
    public function findProxyNodesByIdentifier($identifier, Context $context)
    {
        return $this->nodeDataRepository->findByContentObjectProxy($identifier, $context->getWorkspace());
    }

    /**
     * Sync content repository with corresponding doctrine entities
     *
     * @param string $className
     * @param Context $context
     * @param \Closure $callback
     */
    public function synchronizeAll($className, Context $context, \Closure $callback = null)
    {
        foreach ($this->contentProxyProxyableRepository->findAll($className) as $entity) {
            $accessibleProperty = ObjectAccess::getGettableProperties($entity);
            $identifier = $this->persistenceManager->getIdentifierByObject($entity);
            $nodeCounter = $updatedNodeCounter = 0;

            foreach ($this->findProxyNodesByIdentifier($identifier, $context) as $nodeData) {
                $node = $this->nodeFactory->createFromNodeData($nodeData, $context);
                $updated = $this->synchronize($node, $accessibleProperty, $className, $context);
                if ($callback !== null) {
                    $callback($node, $entity, $updated);
                }
                if ($updated === true) {
                    $updatedNodeCounter++;
                }
                $nodeCounter++;
            }

            $message = vsprintf('module=content-object-proxy action=entity-synchronized type=%s identifier=%s node-updated=%d node-processed=%s', [
                $className,
                $identifier,
                $updatedNodeCounter,
                $nodeCounter
            ]);
            $this->logger->log($message, LOG_NOTICE);
        }
    }

    /**
     * @param NodeInterface $node
     * @param array $accessibleProperty
     * @param $className
     * @param Context $context
     * @return boolean
     * @throws Exception
     */
    protected function synchronize(NodeInterface $node, $accessibleProperty, $className, Context $context)
    {
        if ($node->getContentObject() === null) {
            throw new Exception('Only nodes with attached content object can be synchronized', 1468343430);
        }

        $updated = false;

        if ($accessibleProperty === []) {
            return $updated;
        }

        $this->contentObjectProxyService->withoutSynchronization(function () use ($node, $accessibleProperty, $className, &$updated) {
            $options = $node->getNodeType()->getOptions();
            $contentObjectProxyMapping = Arrays::getValueByPath($options, ['contentObjectProxyMapping', $className]) ?: [];

            foreach ($accessibleProperty as $propertyName => $propertyValue) {
                $currentValue = $node->getProperty($propertyName);
                if ($node->hasProperty($propertyName) && $currentValue !== $propertyValue) {
                    if (isset($contentObjectProxyMapping[$propertyName])) {
                        $propertyName = $contentObjectProxyMapping[$propertyName];
                    }

                    $node->setProperty($propertyName, $propertyValue);

                    $updated = true;

                    $message = vsprintf('module=content-object-proxy action=node-property-updated node=%s property-name=%s previous-value="%s" value="%s"', [
                        $node->getIdentifier(),
                        $propertyName,
                        $currentValue,
                        $propertyValue
                    ]);
                    $this->logger->log($message, LOG_NOTICE);
                }
            }
            $this->persistenceManager->persistAll();
        });

        $this->nodeFactory->reset();
        $context->getFirstLevelNodeCache()->flush();

        return $updated;
    }

    /**
     * Get all proxyable entities
     *
     * @param ObjectManagerInterface $objectManager
     * @return array
     * @Flow\CompileStatic
     */
    protected static function getProxyableEntities($objectManager)
    {
        $reflectionService = $objectManager->get(ReflectionService::class);
        $nodeImplementations = $reflectionService->getAllImplementationClassNamesForInterface(ContentProxyProxyableEntityInterface::class);
        return array_map(function ($entity) use ($reflectionService) {
            if (!$reflectionService->isClassAnnotatedWith($entity, Flow\Entity::class)) {
                throw new Exception(sprintf('Entity class "%s" is not a doctrine entity', $entity), 1468323424);
            }
            return [
                'className' => $entity
            ];
        }, $nodeImplementations);
    }
}
