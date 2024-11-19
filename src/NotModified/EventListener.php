<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\HttpCacheBundle\NotModified;

use SplObjectStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Webfactory\HttpCacheBundle\NotModified\Attribute\ReplaceWithNotModifiedResponse;

/**
 * Symfony EventListener for adding a "last modified" header to the response on the one hand. On the other hand, it
 * replaces the execution of a controller action with a Not Modified HTTP response, if no newer "last modified" date is
 * determined than the one in the header of a subsequent request.
 */
final class EventListener
{
    /**
     * Maps (master and sub) requests to their corresponding last modified date. This date is determined by the
     * ReplaceWithNotModifiedResponse annotation of the corresponding controller's action.
     */
    private SplObjectStorage $lastModified;

    public function __construct(
        private readonly ContainerInterface $container,
        // Symfony kernel.debug mode
        private readonly bool $debug = false,
    ) {
        $this->lastModified = new SplObjectStorage();
    }

    /**
     * When the controller action for a request is determined, check it for a ReplaceWithNotModifiedResponse annotation.
     * If it determines that the underlying resources for the response were not modified after the "If-Modified-Since"
     * header in the request, replace the determined controller action with a minimal action that just returns an
     * "empty" response with a 304 Not Modified HTTP status code.
     */
    public function onKernelController(ControllerEvent $event): void
    {
        $attributes = $event->getAttributes(ReplaceWithNotModifiedResponse::class);

        if (!$attributes) {
            return;
        }

        $attribute = $attributes[0];
        $request = $event->getRequest();
        $attribute->setContainer($this->container);
        $lastModified = $attribute->determineLastModified($request);
        if (!$lastModified) {
            return;
        }

        $this->lastModified[$request] = $lastModified;

        /* Never send 304s for kernel.debug = true: This leads to confusing
           behavior, for example if you don't see updates to Twig templates. */
        if ($this->debug) {
            return;
        }

        $response = new Response();
        $response->setLastModified($lastModified);

        if ($response->isNotModified($request)) {
            $event->setController(
                function () use ($response) {
                    return $response;
                },
                $event->getAttributes()
            );
        }
    }

    /**
     * If a last modified date was determined for the current (master or sub) request, set it to the response so the
     * client can use it for the "If-Modified-Since" header in subsequent requests.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (isset($this->lastModified[$request])) {
            $response->setLastModified($this->lastModified[$request]);
        }
    }
}
