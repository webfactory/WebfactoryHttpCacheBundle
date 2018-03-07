<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\HttpCacheBundle\NotModified\Annotation;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Webfactory\HttpCacheBundle\NotModified\VoterInterface;

/**
 * This Annotation determines the last modified date over all of it's parameterising voters. This date is used by the
 * \Webfactory\HttpCacheBundle\NotModified\EventListener to possibly replace the execution of a controller with
 * sending a Not Modified HTTP response.
 *
 * @Annotation
 */
final class ReplaceWithNotModifiedResponse
{
    /** @var array */
    private $parameters;

    /** @var VoterInterface[] */
    private $voters;

    /** @var ContainerInterface */
    private $container;

    /** @var \DateTime|null */
    private $lastModified;

    /**
     * @param array $parameters
     */
    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * @param Request $request
     * @return \DateTime|null
     */
    public function determineLastModified(Request $request)
    {
        $this->initialiseVoters();

        foreach ($this->voters as $voter) {
            $lastModifiedOfCurrentVoter = $voter->getLastModified($request);
            if ($this->lastModified === null || $this->lastModified < $lastModifiedOfCurrentVoter) {
                $this->lastModified = $lastModifiedOfCurrentVoter;
            }
        }

        return $this->lastModified;
    }

    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

    private function initialiseVoters()
    {
        if (!array_key_exists('voters', $this->parameters) || count($this->parameters['voters']) === 0) {
            throw new \RuntimeException('The annotation ' . get_class($this) . ' has to be parametrised with voters.');
        }

        foreach ($this->parameters['voters'] as $voterDescription) {
            $voter = null;

            if (is_string($voterDescription)) {
                if ($voterDescription[0] === '@') {
                    $voter = $this->container->get($voterDescription);
                } else {
                    $voter = new $voterDescription;
                }
            }

            if (is_array($voterDescription)) {
                $voterClass = key($voterDescription);
                $voterParameter = current($voterDescription);
                $voter = new $voterClass($voterParameter);
            }

            if (!($voter instanceof VoterInterface)) {
                throw new \RuntimeException(
                    'The voter class "' . get_class($voter) . '" does not implement ' . VoterInterface::class . '.'
                );
            }

            $this->voters[] = $voter;
        }
    }
}
