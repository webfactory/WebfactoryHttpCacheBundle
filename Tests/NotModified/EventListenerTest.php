<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\HttpCacheBundle\Tests\NotModified;

use Doctrine\Common\Annotations\Reader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Webfactory\HttpCacheBundle\NotModified\Annotation\ReplaceWithNotModifiedResponse;
use Webfactory\HttpCacheBundle\NotModified\EventListener;
use Webfactory\HttpCacheBundle\NotModified\VoterInterface;

/**
 * Tests for the EventListener.
 */
final class EventListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * System under test.
     *
     * @var EventListener
     */
    private $eventListener;

    /** @var Reader|\PHPUnit_Framework_MockObject_MockObject */
    private $reader;

    /** @var ContainerInterface|\PHPUnit_Framework_MockObject_MockObject */
    private $container;

    /** @var FilterControllerEvent|\PHPUnit_Framework_MockObject_MockObject */
    private $filterControllerEvent;

    /** @var Request */
    private $request;

    /** @var Response */
    private $response;

    /**
     * @see \PHPUnit_Framework_TestCase::setUp()
     */
    protected function setUp()
    {
        $this->request = new Request();
        $this->response = new Response();
        $this->filterControllerEvent = $this->getMockBuilder(FilterControllerEvent::class)->disableOriginalConstructor()->getMock();
        $this->filterControllerEvent->expects($this->any())
            ->method('getController')
            ->willReturn([DummyController::class, 'action']);
        $this->filterControllerEvent->expects($this->any())
            ->method('getRequest')
            ->willReturn($this->request);

        $this->reader = $this->getMock(Reader::class);
        $this->container = $this->getMock(ContainerInterface::class);
        $this->eventListener = new EventListener($this->reader, $this->container);
    }

    /** @test */
    public function onKernelControllerDoesNoHarmForMissingAnnotation()
    {
        $this->expectRegularControllerResponse();
        $this->setUpAnnotationReaderToReturn(null);

        $this->eventListener->onKernelController($this->filterControllerEvent);
    }

    /** @test */
    public function onKernelControllerDoesNoHarmForNoDeterminedLastModified()
    {
        $this->setUpAnnotationReaderToReturn(new ReplaceWithNotModifiedResponse(['voters' => [AbstainingVoter::class]]));
        $this->expectRegularControllerResponse();

        $this->eventListener->onKernelController($this->filterControllerEvent);
    }

    /** @test */
    public function onKernelControllerDoesNoHarmIfNotModifiedSinceHeaderIsNotInRequest()
    {
        $this->setUpAnnotationReaderToReturn(new ReplaceWithNotModifiedResponse(['voters' => [OneDayAgoModifiedVoter::class]]));
        $this->expectRegularControllerResponse();

        $this->eventListener->onKernelController($this->filterControllerEvent);
    }

    /** @test */
    public function onKernelControllerSkipsToModifiedResponseIfLastModifiedIsSmallerThanIfNotModifiedSinceHeader()
    {
        $this->setUpAnnotationReaderToReturn(new ReplaceWithNotModifiedResponse(['voters' => [OneDayAgoModifiedVoter::class]]));
        $this->request->headers->set('If-Modified-Since', '-1 hour');
        $this->expectNotModifiedResponse();

        $this->eventListener->onKernelController($this->filterControllerEvent);
    }

    /** @test */
    public function onKernelControllerSkipsToNotModifiedResponseIfLastModifiedIsEqualToIfNotModifiedSinceHeader()
    {
        $this->setUpAnnotationReaderToReturn(new ReplaceWithNotModifiedResponse(['voters' => [FixedDateAgoModifiedVoter::class]]));
        $this->request->headers->set('If-Modified-Since', '2000-01-01');
        $this->expectNotModifiedResponse();

        $this->eventListener->onKernelController($this->filterControllerEvent);
    }

    /** @test */
    public function onKernelControllerDoesNotReplaceDeterminedControllerIfLastModifiedIsGreaterThanIfNotModifiedSinceHeader()
    {
        $this->setUpAnnotationReaderToReturn(new ReplaceWithNotModifiedResponse(['voters' => [OneDayAgoModifiedVoter::class]]));
        $this->request->headers->set('If-Modified-Since', '-2 day');
        $this->expectRegularControllerResponse();

        $this->eventListener->onKernelController($this->filterControllerEvent);
    }

    /**
     * @test
     */
    public function onKernelResponseSetsLastModifiedHeaderToResponseIfAvailable()
    {
        $this->setUpAnnotationReaderToReturn(new ReplaceWithNotModifiedResponse(['voters' => [OneDayAgoModifiedVoter::class]]));
        $this->eventListener->onKernelController($this->filterControllerEvent);

        $filterResponseEvent = $this->createFilterResponseEvent($this->filterControllerEvent->getRequest(), $this->response);
        $this->eventListener->onKernelResponse($filterResponseEvent);

        $this->assertNotNull($this->response->getLastModified());
    }

    /** @test */
    public function onKernelResponseDoesNotSetLastModifiedHeaderToResponseIfNotAvailable()
    {
        $this->setUpAnnotationReaderToReturn(new ReplaceWithNotModifiedResponse(['voters' => [AbstainingVoter::class]]));
        $this->eventListener->onKernelController($this->filterControllerEvent);

        $filterResponseEvent = $this->createFilterResponseEvent($this->filterControllerEvent->getRequest(), $this->response);
        $this->eventListener->onKernelResponse($filterResponseEvent);

        $this->assertNull($this->response->getLastModified());
    }

    /** @test */
    public function eventListenerDifferentiatesBetweenMultipleRequests()
    {
        $this->setUpAnnotationReaderToReturn(new ReplaceWithNotModifiedResponse(['voters' => [OneDayAgoModifiedVoter::class]]));
        $this->eventListener->onKernelController($this->filterControllerEvent);

        // first request - should get a last modified
        $filterResponseEvent = $this->createFilterResponseEvent($this->filterControllerEvent->getRequest(), $this->response);
        $this->eventListener->onKernelResponse($filterResponseEvent);
        $this->assertNotNull($this->response->getLastModified());

        // event for another request - it's response should not get a last modified
        $anotherRequest = new Request();
        $anotherResponse = new Response();
        $anotherFilterResponseEvent = $this->createFilterResponseEvent($anotherRequest, $anotherResponse);
        $this->eventListener->onKernelResponse($anotherFilterResponseEvent);
        $this->assertNull($anotherResponse->getLastModified());
    }

    /**
     * @param object|null $annotation
     */
    private function setUpAnnotationReaderToReturn($annotation = null)
    {
        $this->reader->expects($this->any())
            ->method('getMethodAnnotation')
            ->willReturn($annotation);
    }

    private function expectRegularControllerResponse()
    {
        $this->filterControllerEvent->expects($this->never())
            ->method('setController');
    }

    private function expectNotModifiedResponse()
    {
        $this->filterControllerEvent->expects($this->once())
            ->method('setController');
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \PHPUnit_Framework_MockObject_MockObject|FilterResponseEvent
     */
    private function createFilterResponseEvent(Request $request, Response $response)
    {
        $filterResponseEvent = $this->getMockBuilder(FilterResponseEvent::class)->disableOriginalConstructor()->getMock();
        $filterResponseEvent->expects($this->any())
            ->method('getRequest')
            ->willReturn($request);
        $filterResponseEvent->expects($this->any())
            ->method('getResponse')
            ->willReturn($response);

        return $filterResponseEvent;
    }
}



final class DummyController
{
    public function action()
    {
        return new Response();
    }
}

final class AbstainingVoter implements VoterInterface
{
    public function getLastModified(Request $request)
    {
        return null;
    }
}

final class OneDayAgoModifiedVoter implements VoterInterface
{
    public function getLastModified(Request $request)
    {
        return new \DateTime('-1 day');
    }
}

final class FixedDateAgoModifiedVoter implements VoterInterface
{
    public function getLastModified(Request $request)
    {
        return new \DateTime('2000-01-01');
    }
}
