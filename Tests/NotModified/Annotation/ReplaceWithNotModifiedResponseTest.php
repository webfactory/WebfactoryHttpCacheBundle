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
use Webfactory\HttpCacheBundle\NotModified\LastModifiedDeterminator;

/**
 * Tests for the ReplaceWithNotModifiedResponse annotation.
 */
final class ReplaceWithNotModifiedResponseTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function lastModifiedDescriptionsCannotBeEmpty()
    {
        $this->setExpectedException(\RuntimeException::class);
        $annotation = new ReplaceWithNotModifiedResponse(['value' => []]);
        $annotation->determineLastModified(new Request());
    }

    /**
     * @test
     */
    public function stringAsSimpleLastModifiedDescription()
    {
        $this->setExpectedException(null);
        $annotation = new ReplaceWithNotModifiedResponse(['value' => [MyLastModifedDeterminator::class]]);
        $annotation->determineLastModified(new Request());
    }

    /**
     * @test
     */
    public function serviceNameAsLastModifiedDescription()
    {
        $lastModifiedDeterminatorServiceName = '@my.service';
        $lastModifiedDeterminatorServiceObject = new MyLastModifedDeterminator();

        /** @var ContainerInterface|\PHPUnit_Framework_MockObject_MockObject $container */
        $container = $this->getMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with($lastModifiedDeterminatorServiceName)
            ->willReturn($lastModifiedDeterminatorServiceObject);

        $annotation = new ReplaceWithNotModifiedResponse(['value' => [$lastModifiedDeterminatorServiceName]]);
        $annotation->setContainer($container);

        $this->setExpectedException(null);
        $annotation->determineLastModified(new Request());
    }

    /**
     * @test
     */
    public function arrayAslastModifiedDeterminatorDescriptionWithConstructorArguments()
    {
        $this->setExpectedException(null);
        $annotation = new ReplaceWithNotModifiedResponse(['value' => [[MyLastModifedDeterminator::class => new \DateTime('2000-01-01')]]]);
        $annotation->determineLastModified(new Request());
    }

    /**
     * @test
     */
    public function lastModifiedDeterminatorsHaveToImplementInterface()
    {
        $this->setExpectedException(\RuntimeException::class);
        $annotation = new ReplaceWithNotModifiedResponse(['value' => [FakeLastModifiedDeterminatorWithoutInterface::class]]);
        $annotation->determineLastModified(new Request());
    }

    /**
     * @test
     */
    public function determineLastModifiedDeterminesLastModifiedOfOneDeterminator()
    {
        $annotation = new ReplaceWithNotModifiedResponse(['value' => [MyLastModifedDeterminator::class]]);
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
    public function determineLastModifiedDeterminesLastModifiedOfMultipleDeterminators()
    {
        $annotation = new ReplaceWithNotModifiedResponse(['value' => [
            [MyLastModifedDeterminator::class => new \DateTime('2001-01-01')],
            [MyLastModifedDeterminator::class => new \DateTime('2003-01-01')],
            [MyLastModifedDeterminator::class => new \DateTime('2002-01-01')],
        ]]);
        $this->assertEquals(new \DateTime('2003-01-01'), $annotation->determineLastModified(new Request()));
    }
}



final class FakeLastModifiedDeterminatorWithoutInterface
{
}

final class MyLastModifedDeterminator implements LastModifiedDeterminator
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
