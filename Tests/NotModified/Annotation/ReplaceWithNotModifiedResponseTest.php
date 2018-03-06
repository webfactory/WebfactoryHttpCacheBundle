<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\HttpCacheBundle\Tests\NotModified\Annotation;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Webfactory\HttpCacheBundle\NotModified\Annotation\ReplaceWithNotModifiedResponse;
use Webfactory\HttpCacheBundle\NotModified\VoterInterface;

/**
 * Tests for the ReplaceWithNotModifiedResponse annotation.
 */
final class ReplaceWithNotModifiedResponseTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function votersParameterIsRequired()
    {
        $this->setExpectedException(\RuntimeException::class);
        $annotation = new ReplaceWithNotModifiedResponse([]);
        $annotation->determineLastModified(new Request());
    }

    /**
     * @test
     */
    public function votersParameterCannotBeEmpty()
    {
        $this->setExpectedException(\RuntimeException::class);
        $annotation = new ReplaceWithNotModifiedResponse(['voters' => []]);
        $annotation->determineLastModified(new Request());
    }

    /**
     * @test
     */
    public function stringAsSimpleVoterParameter()
    {
        $this->setExpectedException(null);
        $annotation = new ReplaceWithNotModifiedResponse(['voters' => [Voter::class]]);
        $annotation->determineLastModified(new Request());
    }

    /**
     * @test
     */
    public function serviceNameAsVoterParameter()
    {
        $voterServiceName = '@my.service';
        $voterServiceObject = new Voter();

        /** @var ContainerInterface|\PHPUnit_Framework_MockObject_MockObject $container */
        $container = $this->getMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with($voterServiceName)
            ->willReturn($voterServiceObject);

        $annotation = new ReplaceWithNotModifiedResponse(['voters' => [$voterServiceName]]);
        $annotation->setContainer($container);

        $this->setExpectedException(null);
        $annotation->determineLastModified(new Request());
    }

    /**
     * @test
     */
    public function invalidArrayAsVoterParameter()
    {
        $this->setExpectedException(\RuntimeException::class);
        $annotation = new ReplaceWithNotModifiedResponse(['voters' => [['invalid', 'array', 'structure']]]);
        $annotation->determineLastModified(new Request());
    }

    /**
     * @test
     */
    public function validArrayAsVoterParameterWithConstructorArguments()
    {
        $this->setExpectedException(null);
        $annotation = new ReplaceWithNotModifiedResponse(['voters' => [[Voter::class => new \DateTime('2000-01-01')]]]);
        $annotation->determineLastModified(new Request());
    }

    /**
     * @test
     */
    public function votersHaveToImplementInterface()
    {
        $this->setExpectedException(\RuntimeException::class);
        $annotation = new ReplaceWithNotModifiedResponse(['voters' => [VoterWithoutInterface::class]]);
        $annotation->determineLastModified(new Request());
    }

    /**
     * @test
     */
    public function determineLastModifiedDeterminesLastModifiedOfOneVoter()
    {
        $annotation = new ReplaceWithNotModifiedResponse(['voters' => [Voter::class]]);
        $this->assertEquals(
            new \DateTime(),
            $annotation->determineLastModified(new Request()),
            '',
            $allowedDeltaInSeconds = 3
        );
    }

    /**
     * @test
     */
    public function determineLastModifiedDeterminesLastModifiedOfMultipleVoters()
    {
        $annotation = new ReplaceWithNotModifiedResponse(['voters' => [
            [Voter::class => new \DateTime('2001-01-01')],
            [Voter::class => new \DateTime('2003-01-01')],
            [Voter::class => new \DateTime('2002-01-01')],
        ]]);
        $this->assertEquals(new \DateTime('2003-01-01'), $annotation->determineLastModified(new Request()));
    }
}



final class VoterWithoutInterface
{
}

final class Voter implements VoterInterface
{
    /** @var \DateTime */
    private $lastModified;

    public function __construct(\DateTime $lastModified = null)
    {
        $this->lastModified = $lastModified ?: new \DateTime();
    }

    public function getLastModified(Request $request)
    {
        return $this->lastModified;
    }
}
