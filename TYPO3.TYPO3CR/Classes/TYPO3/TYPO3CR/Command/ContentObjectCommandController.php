<?php
namespace TYPO3\TYPO3CR\Command;

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
use TYPO3\Flow\Cli\CommandController;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;
use TYPO3\Neos\Controller\CreateContentContextTrait;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Service\ContentProxyableEntityService;

/**
 * Node command controller for the TYPO3.TYPO3CR package
 *
 * @Flow\Scope("singleton")
 */
class ContentObjectCommandController extends CommandController
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
     * Show a list of proxyable entities
     */
    public function typesCommand()
    {
        $entities = $this->contentProxyableEntityService->getEntities();
        $this->outputLine();
        $this->outputLine('<info>Proxyable Entities</info>');
        array_map(function ($entity) {
            $this->outputLine('<comment>--</comment> %s', [$entity['className']]);
        }, $entities);
    }

    /**
     * @param string $type
     */
    public function syncCommand($type)
    {
        $context = $this->createContentContext('live');
        $processedEntities = [];
        $this->contentProxyableEntityService->synchronizeAll($type, $context, function (NodeInterface $node, $entity, $updated) use (&$processedEntities) {
            $identifier = $this->persistenceManager->getIdentifierByObject($entity);
            if (!isset($processedEntities[$identifier])) {
                $processedEntities[$identifier] = true;
                $this->outputLine();
                $this->outputLine('Process entity "<b>%s</b>" (%s)', [$node->getLabel(), $identifier]);
            }
            $result = [
                $node->getPath(),
                $node->getIdentifier()
            ];
            if ($updated) {
                $this->outputLine('<info>++</info> node updated %s (%s)', $result);
            } else {
                $this->outputLine('<comment>~~</comment> node skipped %s (%s)', $result);
            }
        });
    }
}
