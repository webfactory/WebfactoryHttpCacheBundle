<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\HttpCacheBundle\NotModified\Annotation;

/**
 * @Annotation
 * @deprecated, to be replaced by attribute-based configuration
 */
final class ReplaceWithNotModifiedResponse extends \Webfactory\HttpCacheBundle\NotModified\Attribute\ReplaceWithNotModifiedResponse
{
    public function __construct(array $parameters)
    {
        trigger_deprecation(
            'webfactory/http-cache-bundle',
            '1.4.0',
            'The %s annotation has been deprecated, use the %s attribute instead.',
            NotModified\Annotation\ReplaceWithNotModifiedResponse::class,
            NotModified\Attribute\ReplaceWithNotModifiedResponse::class
        );

        parent::__construct($parameters['value']);
    }
}
