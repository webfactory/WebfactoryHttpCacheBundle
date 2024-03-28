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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Webfactory\HttpCacheBundle\NotModified\Attribute\ReplaceWithNotModifiedResponse;
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

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(KernelInterface::class);
        $this->request = new Request();
        $this->response = new Response();
        $this->container = $this->createMock(ContainerInterface::class);
        $this->eventListener = new EventListener($this->container);
    }

    /** @test */
    public function onKernelControllerDoesNoHarmForMissingAnnotation(): void
    {
        $this->exerciseOnKernelController([DummyController::class, 'plainAction']);

        $this->assertRegularControllerResponse();
    }

    /** @test */
    public function onKernelControllerDoesNoHarmForNoDeterminedLastModified(): void
    {
        $this->exerciseOnKernelController([DummyController::class, 'abstainingLastModifiedAction']);

        $this->assertRegularControllerResponse();
    }

    /** @test */
    public function onKernelControllerDoesNoHarmIfNotModifiedSinceHeaderIsNotInRequest(): void
    {
        $this->exerciseOnKernelController([DummyController::class, 'oneDayAgoModifiedLastModifiedAction']);

        $this->assertRegularControllerResponse();
    }

    /** @test */
    public function onKernelControllerSkipsToModifiedResponseIfLastModifiedIsSmallerThanIfNotModifiedSinceHeader(): void
    {
        $this->request->headers->set('If-Modified-Since', '-1 hour');

        $this->exerciseOnKernelController([DummyController::class, 'oneDayAgoModifiedLastModifiedAction']);

        $this->assertNotModifiedResponse();
    }

    /** @test */
    public function onKernelControllerAlwaysRunsControllerInKernelDebugMode(): void
    {
        $this->eventListener = new EventListener($this->container, true);
        $this->request->headers->set('If-Modified-Since', '-1 hour');

        $this->exerciseOnKernelController([DummyController::class, 'oneDayAgoModifiedLastModifiedAction']);

        $this->assertRegularControllerResponse();
    }

    /** @test */
    public function onKernelControllerSkipsToNotModifiedResponseIfLastModifiedIsEqualToIfNotModifiedSinceHeader(): void
    {
        $this->request->headers->set('If-Modified-Since', '2000-01-01');

        $this->exerciseOnKernelController([DummyController::class, 'fixedDateAgoModifiedLastModifiedDeterminatorAction']);

        $this->assertNotModifiedResponse();
    }

    /** @test */
    public function onKernelControllerDoesNotReplaceDeterminedControllerIfLastModifiedIsGreaterThanIfNotModifiedSinceHeader(): void
    {
        $this->request->headers->set('If-Modified-Since', '-2 day');

        $this->exerciseOnKernelController([DummyController::class, 'oneDayAgoModifiedLastModifiedAction']);

        $this->assertRegularControllerResponse();
    }

    /**
     * @test
     */
    public function onKernelResponseSetsLastModifiedHeaderToResponseIfAvailable(): void
    {
        $this->exerciseOnKernelController([DummyController::class, 'oneDayAgoModifiedLastModifiedAction']);

        $filterResponseEvent = $this->createFilterResponseEvent($this->filterControllerEvent->getRequest(), $this->response);
        $this->eventListener->onKernelResponse($filterResponseEvent);

        self::assertEquals(DateTime::createFromFormat('U', time() - 86400), $this->response->getLastModified());
    }

    /** @test */
    public function onKernelResponseDoesNotSetLastModifiedHeaderToResponseIfNotAvailable(): void
    {
        $this->exerciseOnKernelController([DummyController::class, 'abstainingLastModifiedAction']);

        $filterResponseEvent = $this->createFilterResponseEvent($this->filterControllerEvent->getRequest(), $this->response);
        $this->eventListener->onKernelResponse($filterResponseEvent);

        self::assertNull($this->response->getLastModified());
    }

    /** @test */
    public function eventListenerDifferentiatesBetweenMultipleRequests(): void
    {
        $this->exerciseOnKernelController([DummyController::class, 'oneDayAgoModifiedLastModifiedAction']);

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

    private function exerciseOnKernelController(array $callable): void
    {
        $this->callable = $callable;
        $this->filterControllerEvent = new ControllerEvent($this->kernel, $this->callable, $this->request, HttpKernelInterface::MAIN_REQUEST);

        $this->eventListener->onKernelController($this->filterControllerEvent);
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
        return new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }
}

final class DummyController
{
    public static function plainAction(): Response
    {
        return new Response();
    }

    #[ReplaceWithNotModifiedResponse([AbstainingLastModifiedDeterminator::class])]
    public static function abstainingLastModifiedAction(): Response
    {
        return new Response();
    }

    #[ReplaceWithNotModifiedResponse([OneDayAgoModifiedLastModifiedDeterminator::class])]
    public static function oneDayAgoModifiedLastModifiedAction(): Response
    {
        return new Response();
    }

    #[ReplaceWithNotModifiedResponse([FixedDateAgoModifiedLastModifiedDeterminator::class])]
    public static function fixedDateAgoModifiedLastModifiedDeterminatorAction(): Response
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
