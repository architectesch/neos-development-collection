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

/**
 * ContextFactory Interface
 *
 */
interface ContextFactoryInterface
{
    /**
     * Create the context from the given properties. If a context with those properties was already
     * created before then the existing one is returned.
     *
     * The context properties to give depend on the implementation of the context object, for the
     * TYPO3\TYPO3CR\Domain\Service\Context it should look like this:
     *
     * array(
     *        'workspaceName' => 'live',
     *        'currentDateTime' => new \TYPO3\Flow\Utility\Now(),
     *        'dimensions' => array(),
     *        'invisibleContentShown' => FALSE,
     *        'removedContentShown' => FALSE,
     *        'inaccessibleContentShown' => FALSE
     * )
     *
     * @param array $contextConfiguration
     * @return \TYPO3\TYPO3CR\Domain\Service\Context
     * @api
     */
    public function create(array $contextConfiguration = array());

    /**
     * Clears the instances cache clearing all contexts.
     *
     * @return void
     */
    public function reset();

    /**
     * Returns all known instances of Context.
     *
     * @return array<Context>
     */
    public function getInstances();
}
