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
use Webfactory\HttpCacheBundle\NotModified\LastModifiedDeterminator;

/**
 * This Annotation determines the latest last modified date over all of its LastModifiedDeterminators. This date is used
 * by the \Webfactory\HttpCacheBundle\NotModified\EventListener to possibly replace the execution of a controller with
 * sending a Not Modified HTTP response.
 *
 * @Annotation
 */
final class ReplaceWithNotModifiedResponse
{
    /** @var array */
    private $parameters;

    /** @var LastModifiedDeterminator[] */
    private $lastModifiedDeterminators;

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
        $this->initialiseLastModifiedDeterminators();

        foreach ($this->lastModifiedDeterminators as $lastModifiedDeterminator) {
            $lastModifiedOfCurrentDeterminator = $lastModifiedDeterminator->getLastModified($request);
            if ($this->lastModified === null || $this->lastModified < $lastModifiedOfCurrentDeterminator) {
                $this->lastModified = $lastModifiedOfCurrentDeterminator;
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

    private function initialiseLastModifiedDeterminators()
    {
        if (count($this->parameters['value']) === 0) {
            throw new \RuntimeException('The annotation ' . get_class($this) . ' has to be parametrised with LastModifiedDeterminators.');
        }

        foreach ($this->parameters['value'] as $lastModifiedDeterminatorDescription) {
            $lastModifiedDeterminator = null;

            if (is_string($lastModifiedDeterminatorDescription)) {
                if ($lastModifiedDeterminatorDescription[0] === '@') {
                    $lastModifiedDeterminator = $this->container->get(substr($lastModifiedDeterminatorDescription, 1));
                } else {
                    $lastModifiedDeterminator = new $lastModifiedDeterminatorDescription;
                }
            }

            if (is_array($lastModifiedDeterminatorDescription)) {
                $lastModifiedDeterminatorClass = key($lastModifiedDeterminatorDescription);
                $lastModifiedDeterminatorParameter = current($lastModifiedDeterminatorDescription);
                $lastModifiedDeterminator = new $lastModifiedDeterminatorClass($lastModifiedDeterminatorParameter);
            }

            if (!($lastModifiedDeterminator instanceof LastModifiedDeterminator)) {
                throw new \RuntimeException(
                    'The class "' . get_class($lastModifiedDeterminator) . '" does not implement ' . LastModifiedDeterminator::class . '.'
                );
            }

            $this->lastModifiedDeterminators[] = $lastModifiedDeterminator;
        }
    }
}
