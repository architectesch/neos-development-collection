<?php
namespace TYPO3\TYPO3CR\Domain\Repository;

/*
 * This file is part of the TYPO3.TYPO3CR package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\Internal\Hydration\IterableResult;
use Doctrine\ORM\QueryBuilder;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Exception;

/**
 * ContentProxyableRepository
 *
 * @Flow\Scope("singleton")
 * @api
 */
class ContentProxyableRepository
{
    /**
     * @Flow\Inject
     * @var ObjectManager
     */
    protected $entityManager;

    /**
     * @param string $from
     * @param \Closure $callback
     * @return IterableResult
     */
    public function findAll($from, \Closure $callback = null)
    {
        $queryBuilder = $this->getQueryBuilder();

        return $this->iterate($queryBuilder
            ->select('Entity')
            ->from($from, 'Entity')
            ->getQuery()->iterate(), $callback);
    }

    /**
     * Iterator over an IterableResult and return a Generator
     *
     * @param IterableResult $iterator
     * @param callable $callback
     * @return \Generator
     */
    protected function iterate(IterableResult $iterator, callable $callback = null)
    {
        $iteration = 0;
        foreach ($iterator as $object) {
            $object = current($object);
            yield $object;
            if ($callback !== null) {
                call_user_func($callback, $object, $iteration);
            }
            ++$iteration;
        }
    }

    /**
     * @return QueryBuilder
     */
    protected function getQueryBuilder()
    {
        return $this->entityManager->createQueryBuilder();
    }
}
