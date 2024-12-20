<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\HttpCacheBundle\NotModified\Attribute;

use Attribute;
use DateTime;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Webfactory\HttpCacheBundle\NotModified\LastModifiedDeterminator;

/**
 * This attribute determines the latest last modified date over all of its LastModifiedDeterminators. This date is used
 * by the \Webfactory\HttpCacheBundle\NotModified\EventListener to possibly replace the execution of a controller with
 * sending a Not Modified HTTP response.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class ReplaceWithNotModifiedResponse
{
    /** @var LastModifiedDeterminator[] */
    private array $lastModifiedDeterminators;
    private ContainerInterface $container;
    private ?DateTime $lastModified = null;

    public function __construct(
        private readonly array $parameters,
    ) {
    }

    public function determineLastModified(Request $request): ?DateTime
    {
        $this->initialiseLastModifiedDeterminators();

        foreach ($this->lastModifiedDeterminators as $lastModifiedDeterminator) {
            $lastModifiedOfCurrentDeterminator = $lastModifiedDeterminator->getLastModified($request);
            if (null === $this->lastModified || $this->lastModified < $lastModifiedOfCurrentDeterminator) {
                $this->lastModified = $lastModifiedOfCurrentDeterminator;
            }
        }

        return $this->lastModified;
    }

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    private function initialiseLastModifiedDeterminators(): void
    {
        if (0 === count($this->parameters)) {
            throw new RuntimeException('The attribute '.get_class($this).' has to be parametrised with LastModifiedDeterminators.');
        }

        foreach ($this->parameters as $lastModifiedDeterminatorDescription) {
            $lastModifiedDeterminator = null;

            if (is_string($lastModifiedDeterminatorDescription)) {
                if ('@' === $lastModifiedDeterminatorDescription[0]) {
                    $lastModifiedDeterminator = $this->container->get(substr($lastModifiedDeterminatorDescription, 1));
                } else {
                    $lastModifiedDeterminator = new $lastModifiedDeterminatorDescription();
                }
            }

            if (is_array($lastModifiedDeterminatorDescription)) {
                $lastModifiedDeterminatorClass = key($lastModifiedDeterminatorDescription);
                $lastModifiedDeterminatorParameter = current($lastModifiedDeterminatorDescription);
                $lastModifiedDeterminator = new $lastModifiedDeterminatorClass($lastModifiedDeterminatorParameter);
            }

            if (!($lastModifiedDeterminator instanceof LastModifiedDeterminator)) {
                throw new RuntimeException('The class "'.get_class($lastModifiedDeterminator).'" does not implement '.LastModifiedDeterminator::class.'.');
            }

            $this->lastModifiedDeterminators[] = $lastModifiedDeterminator;
        }
    }
}
