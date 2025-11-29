<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\HttpCacheBundle\Tests\NotModified\Attribute;

use DateTime;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
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
    #[Test]
    public function lastModifiedDescriptionsCannotBeEmpty()
    {
        $this->expectException(RuntimeException::class);
        $attribute = new ReplaceWithNotModifiedResponse([]);
        $attribute->determineLastModified(new Request());
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function stringAsSimpleLastModifiedDescription()
    {
        $attribute = new ReplaceWithNotModifiedResponse([MyLastModifedDeterminator::class]);
        $attribute->determineLastModified(new Request());
    }

    #[Test]
    public function serviceNameAsLastModifiedDescription()
    {
        /** @var ContainerInterface|MockObject $container */
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with('my.service')
            ->willReturn(new MyLastModifedDeterminator());

        $attribute = new ReplaceWithNotModifiedResponse(['@my.service']);
        $attribute->setContainer($container);

        $attribute->determineLastModified(new Request());
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function arrayAslastModifiedDeterminatorDescriptionWithConstructorArguments()
    {
        $attribute = new ReplaceWithNotModifiedResponse([[MyLastModifedDeterminator::class => new DateTime('2000-01-01')]]);
        $attribute->determineLastModified(new Request());
    }

    #[Test]
    public function lastModifiedDeterminatorsHaveToImplementInterface()
    {
        $this->expectException(RuntimeException::class);
        $attribute = new ReplaceWithNotModifiedResponse([FakeLastModifiedDeterminatorWithoutInterface::class]);
        $attribute->determineLastModified(new Request());
    }

    #[Group('time-sensitive')]
    #[Test]
    public function determineLastModifiedDeterminesLastModifiedOfOneDeterminator()
    {
        $attribute = new ReplaceWithNotModifiedResponse([MyLastModifedDeterminator::class]);

        $lastModified = $attribute->determineLastModified(new Request());

        self::assertEquals(DateTime::createFromFormat('U', time()), $lastModified);
    }

    #[Test]
    public function determineLastModifiedDeterminesLastModifiedOfMultipleDeterminators()
    {
        $attribute = new ReplaceWithNotModifiedResponse([
            [MyLastModifedDeterminator::class => new DateTime('2001-01-01')],
            [MyLastModifedDeterminator::class => new DateTime('2003-01-01')],
            [MyLastModifedDeterminator::class => new DateTime('2002-01-01')],
        ]);
        $this->assertEquals(new DateTime('2003-01-01'), $attribute->determineLastModified(new Request()));
    }
}

final class FakeLastModifiedDeterminatorWithoutInterface
{
}

final class MyLastModifedDeterminator implements LastModifiedDeterminator
{
    private DateTime $lastModified;

    public function __construct(?DateTime $lastModified = null)
    {
        $this->lastModified = $lastModified ?: DateTime::createFromFormat('U', time());
    }

    public function getLastModified(Request $request): DateTime
    {
        return $this->lastModified;
    }
}
