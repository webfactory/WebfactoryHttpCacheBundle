<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\HttpCacheBundle\Tests\NotModified\Attribute;

use DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Webfactory\HttpCacheBundle\NotModified\Attribute\ReplaceWithNotModifiedResponse;
use Webfactory\HttpCacheBundle\NotModified\LastModifiedDeterminator;

/**
 * Tests for the ReplaceWithNotModifiedResponse attribute.
 */
final class ReplaceWithNotModifiedResponseTest extends TestCase
{
    /**
     * @test
     */
    public function lastModifiedDescriptionsCannotBeEmpty()
    {
        $this->expectException(RuntimeException::class);
        $annotation = new ReplaceWithNotModifiedResponse([]);
        $annotation->determineLastModified(new Request());
    }

    /**
     * @test
     *
     * @doesNotPerformAssertions
     */
    public function stringAsSimpleLastModifiedDescription()
    {
        $annotation = new ReplaceWithNotModifiedResponse([MyLastModifedDeterminator::class]);
        $annotation->determineLastModified(new Request());
    }

    /**
     * @test
     */
    public function serviceNameAsLastModifiedDescription()
    {
        /** @var ContainerInterface|MockObject $container */
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with('my.service')
            ->willReturn(new MyLastModifedDeterminator());

        $annotation = new ReplaceWithNotModifiedResponse(['@my.service']);
        $annotation->setContainer($container);

        $annotation->determineLastModified(new Request());
    }

    /**
     * @test
     *
     * @doesNotPerformAssertions
     */
    public function arrayAslastModifiedDeterminatorDescriptionWithConstructorArguments()
    {
        $annotation = new ReplaceWithNotModifiedResponse([[MyLastModifedDeterminator::class => new DateTime('2000-01-01')]]);
        $annotation->determineLastModified(new Request());
    }

    /**
     * @test
     */
    public function lastModifiedDeterminatorsHaveToImplementInterface()
    {
        $this->expectException(RuntimeException::class);
        $annotation = new ReplaceWithNotModifiedResponse([FakeLastModifiedDeterminatorWithoutInterface::class]);
        $annotation->determineLastModified(new Request());
    }

    /**
     * @test
     *
     * @group time-sensitive
     */
    public function determineLastModifiedDeterminesLastModifiedOfOneDeterminator()
    {
        $annotation = new ReplaceWithNotModifiedResponse([MyLastModifedDeterminator::class]);

        $lastModified = $annotation->determineLastModified(new Request());

        self::assertEquals(DateTime::createFromFormat('U', time()), $lastModified);
    }

    /**
     * @test
     */
    public function determineLastModifiedDeterminesLastModifiedOfMultipleDeterminators()
    {
        $annotation = new ReplaceWithNotModifiedResponse([
            [MyLastModifedDeterminator::class => new DateTime('2001-01-01')],
            [MyLastModifedDeterminator::class => new DateTime('2003-01-01')],
            [MyLastModifedDeterminator::class => new DateTime('2002-01-01')],
        ]);
        $this->assertEquals(new DateTime('2003-01-01'), $annotation->determineLastModified(new Request()));
    }
}

final class FakeLastModifiedDeterminatorWithoutInterface
{
}

final class MyLastModifedDeterminator implements LastModifiedDeterminator
{
    /** @var DateTime */
    private $lastModified;

    public function __construct(DateTime $lastModified = null)
    {
        $this->lastModified = $lastModified ?: DateTime::createFromFormat('U', time());
    }

    public function getLastModified(Request $request)
    {
        return $this->lastModified;
    }
}
