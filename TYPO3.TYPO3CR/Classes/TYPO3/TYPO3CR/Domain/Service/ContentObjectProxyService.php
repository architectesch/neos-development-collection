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

use Doctrine\ORM\Event\LifecycleEventArgs;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;
use TYPO3\Flow\Reflection\ObjectAccess;
use TYPO3\Neos\Controller\CreateContentContextTrait;
use TYPO3\TYPO3CR\Domain\Model\ContentProxyProxyableEntityInterface;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Exception;

/**
 * Handle synchronisation of data between doctrine entities to node
 *
 * @Flow\Scope("singleton")
 * @api
 */
class ContentObjectProxyService
{
    use CreateContentContextTrait;

    /**
     * @var PersistenceManagerInterface
     * @Flow\Inject
     */
    protected $persistenceManager;

    /**
     * @var ContentProxyableEntityService
     * @Flow\Inject
     */
    protected $contentProxyableEntityService;

    /**
     * @var boolean
     */
    protected $synchronizationDisabled = false;

    /**
     * @param NodeInterface $node
     * @param string $propertyName name of the property that has been changed/added
     * @param mixed $oldValue the property value before it was changed or NULL if the property is new
     * @param mixed $newValue the new property value
     * @return void
     */
    public function setProperty(NodeInterface $node, $propertyName, $oldValue, $newValue)
    {
        $contentObject = $node->getContentObject();
        if ($contentObject === null || !ObjectAccess::isPropertySettable($contentObject, $propertyName)) {
            return;
        }
        $this->withoutSynchronization(function () use ($contentObject, $propertyName, $newValue) {
            ObjectAccess::setProperty($contentObject, $propertyName, $newValue);
            $this->persistenceManager->update($contentObject);
            $this->persistenceManager->persistAll();
        });
    }

    /**
     * Synchronize doctrine properties to the content repository on post update
     *
     * @param LifecycleEventArgs $eventArgs
     */
    public function postUpdate(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getEntity();
        if (!$entity instanceof ContentProxyProxyableEntityInterface) {
            return;
        }
        $this->contentProxyableEntityService->synchronizeAll(get_class($entity), $this->createContentContext('live'));
    }

    /**
     * @param \Closure $callback
     * @throws \Exception
     */
    public function withoutSynchronization(\Closure $callback)
    {
        $alreadyDisabled = $this->synchronizationDisabled;
        $this->synchronizationDisabled = true;
        try {
            /** @noinspection PhpUndefinedMethodInspection */
            $callback->__invoke();
        } catch (\Exception $exception) {
            $this->synchronizationDisabled = false;
            throw $exception;
        }
        if ($alreadyDisabled === false) {
            $this->synchronizationDisabled = false;
        }
    }
}
