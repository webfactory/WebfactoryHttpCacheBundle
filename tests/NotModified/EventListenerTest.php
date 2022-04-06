<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\HttpCacheBundle\Tests\NotModified;

use Closure;
use DateTime;
use Doctrine\Common\Annotations\Reader;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Webfactory\HttpCacheBundle\NotModified\Annotation\ReplaceWithNotModifiedResponse;
use Webfactory\HttpCacheBundle\NotModified\EventListener;
use Webfactory\HttpCacheBundle\NotModified\LastModifiedDeterminator;

/**
 * Tests for the EventListener.
 *
 * @group time-sensitive
 */
final class EventListenerTest extends TestCase
{
    /**
     * System under test.
     *
     * @var EventListener
     */
    private $eventListener;

    /** @var Reader|MockObject */
    private $reader;

    /** @var ContainerInterface|MockObject */
    private $container;

    /** @var ControllerEvent|MockObject */
    private $filterControllerEvent;

    /** @var Request */
    private $request;

    /** @var Response */
    private $response;

    private $callable;

    /** @var KernelInterface|MockObject */
    private $kernel;

    /**
     * @see \PHPUnit_Framework_TestCase::setUp()
     */
    protected function setUp(): void
    {
        $this->kernel = $this->createMock(KernelInterface::class);
        $this->callable = [DummyController::class, 'action'];
        $this->request = new Request();
        $this->response = new Response();
        $this->filterControllerEvent = new ControllerEvent($this->kernel, $this->callable, $this->request, HttpKernelInterface::MASTER_REQUEST);
        $this->reader = $this->createMock(Reader::class);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->eventListener = new EventListener($this->reader, $this->container);
    }

    /** @test */
    public function onKernelControllerDoesNoHarmForMissingAnnotation(): void
    {
        $this->setUpAnnotationReaderToReturn(null);

        $this->eventListener->onKernelController($this->filterControllerEvent);

        $this->assertRegularControllerResponse();
    }

    /** @test */
    public function onKernelControllerDoesNoHarmForNoDeterminedLastModified(): void
    {
        $this->setUpAnnotationReaderToReturn(new ReplaceWithNotModifiedResponse(['value' => [AbstainingLastModifiedDeterminator::class]]));

        $this->eventListener->onKernelController($this->filterControllerEvent);

        $this->assertRegularControllerResponse();
    }

    /** @test */
    public function onKernelControllerDoesNoHarmIfNotModifiedSinceHeaderIsNotInRequest(): void
    {
        $this->setUpAnnotationReaderToReturn(new ReplaceWithNotModifiedResponse(['value' => [OneDayAgoModifiedLastModifiedDeterminator::class]]));

        $this->eventListener->onKernelController($this->filterControllerEvent);

        $this->assertRegularControllerResponse();
    }

    /** @test */
    public function onKernelControllerSkipsToModifiedResponseIfLastModifiedIsSmallerThanIfNotModifiedSinceHeader(): void
    {
        $this->setUpAnnotationReaderToReturn(new ReplaceWithNotModifiedResponse(['value' => [OneDayAgoModifiedLastModifiedDeterminator::class]]));
        $this->request->headers->set('If-Modified-Since', '-1 hour');

        $this->eventListener->onKernelController($this->filterControllerEvent);

        $this->assertNotModifiedResponse();
    }

    /** @test */
    public function onKernelControllerAlwaysRunsControllerInKernelDebugMode(): void
    {
        $this->eventListener = new EventListener($this->reader, $this->container, true);
        $this->setUpAnnotationReaderToReturn(new ReplaceWithNotModifiedResponse(['value' => [OneDayAgoModifiedLastModifiedDeterminator::class]]));
        $this->request->headers->set('If-Modified-Since', '-1 hour');

        $this->eventListener->onKernelController($this->filterControllerEvent);

        $this->assertRegularControllerResponse();
    }

    /** @test */
    public function onKernelControllerSkipsToNotModifiedResponseIfLastModifiedIsEqualToIfNotModifiedSinceHeader(): void
    {
        $this->setUpAnnotationReaderToReturn(new ReplaceWithNotModifiedResponse(['value' => [FixedDateAgoModifiedLastModifiedDeterminator::class]]));
        $this->request->headers->set('If-Modified-Since', '2000-01-01');

        $this->eventListener->onKernelController($this->filterControllerEvent);

        $this->assertNotModifiedResponse();
    }

    /** @test */
    public function onKernelControllerDoesNotReplaceDeterminedControllerIfLastModifiedIsGreaterThanIfNotModifiedSinceHeader(): void
    {
        $this->setUpAnnotationReaderToReturn(new ReplaceWithNotModifiedResponse(['value' => [OneDayAgoModifiedLastModifiedDeterminator::class]]));
        $this->request->headers->set('If-Modified-Since', '-2 day');

        $this->eventListener->onKernelController($this->filterControllerEvent);

        $this->assertRegularControllerResponse();
    }

    /**
     * @test
     */
    public function onKernelResponseSetsLastModifiedHeaderToResponseIfAvailable(): void
    {
        $this->setUpAnnotationReaderToReturn(new ReplaceWithNotModifiedResponse(['value' => [OneDayAgoModifiedLastModifiedDeterminator::class]]));
        $this->eventListener->onKernelController($this->filterControllerEvent);

        $filterResponseEvent = $this->createFilterResponseEvent($this->filterControllerEvent->getRequest(), $this->response);
        $this->eventListener->onKernelResponse($filterResponseEvent);

        self::assertEquals(DateTime::createFromFormat('U', time() - 86400), $this->response->getLastModified());
    }

    /** @test */
    public function onKernelResponseDoesNotSetLastModifiedHeaderToResponseIfNotAvailable(): void
    {
        $this->setUpAnnotationReaderToReturn(new ReplaceWithNotModifiedResponse(['value' => [AbstainingLastModifiedDeterminator::class]]));
        $this->eventListener->onKernelController($this->filterControllerEvent);

        $filterResponseEvent = $this->createFilterResponseEvent($this->filterControllerEvent->getRequest(), $this->response);
        $this->eventListener->onKernelResponse($filterResponseEvent);

        self::assertNull($this->response->getLastModified());
    }

    /** @test */
    public function eventListenerDifferentiatesBetweenMultipleRequests(): void
    {
        $this->setUpAnnotationReaderToReturn(new ReplaceWithNotModifiedResponse(['value' => [OneDayAgoModifiedLastModifiedDeterminator::class]]));
        $this->eventListener->onKernelController($this->filterControllerEvent);

        // first request - should get a last modified
        $filterResponseEvent = $this->createFilterResponseEvent($this->filterControllerEvent->getRequest(), $this->response);
        $this->eventListener->onKernelResponse($filterResponseEvent);
        self::assertNotNull($this->response->getLastModified());

        // event for another request - it's response should not get a last modified
        $anotherRequest = new Request();
        $anotherResponse = new Response();
        $anotherFilterResponseEvent = $this->createFilterResponseEvent($anotherRequest, $anotherResponse);
        $this->eventListener->onKernelResponse($anotherFilterResponseEvent);
        self::assertNull($anotherResponse->getLastModified());
    }

    /**
     * @param object|null $annotation
     */
    private function setUpAnnotationReaderToReturn($annotation = null): void
    {
        $this->reader->method('getMethodAnnotation')->willReturn($annotation);
    }

    private function assertRegularControllerResponse(): void
    {
        self::assertSame($this->callable, $this->filterControllerEvent->getController());
    }

    private function assertNotModifiedResponse(): void
    {
        $closure = $this->filterControllerEvent->getController();

        self::assertInstanceOf(Closure::class, $closure);

        $response = $closure();
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(304, $response->getStatusCode());
    }

    private function createFilterResponseEvent(Request $request, Response $response): ResponseEvent
    {
        return new ResponseEvent($this->kernel, $request, HttpKernelInterface::MASTER_REQUEST, $response);
    }
}

final class DummyController
{
    public static function action(): Response
    {
        return new Response();
    }
}

final class AbstainingLastModifiedDeterminator implements LastModifiedDeterminator
{
    public function getLastModified(Request $request): ?DateTime
    {
        return null;
    }
}

final class OneDayAgoModifiedLastModifiedDeterminator implements LastModifiedDeterminator
{
    public function getLastModified(Request $request): DateTime
    {
        return DateTime::createFromFormat('U', time() - 86400);
    }
}

final class FixedDateAgoModifiedLastModifiedDeterminator implements LastModifiedDeterminator
{
    public function getLastModified(Request $request): DateTime
    {
        return new DateTime('2000-01-01');
    }
}
