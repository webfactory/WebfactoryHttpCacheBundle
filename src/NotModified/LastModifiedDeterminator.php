<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\HttpCacheBundle\NotModified;

use DateTime;
use Symfony\Component\HttpFoundation\Request;

/**
 * The ReplaceWithNotModifiedResponse method annotation is parameterised with instances of LastModifiedDeterminators.
 * Each of them should determine the last modified date of one of the various underlying resources for a response.
 * E.g. if your controller's indexAction builds a response containing News and Users, you can write a
 * NewsLastModifiedDeterminator determining the date of the last published News and a UserLastModifiedDeterminator
 * determining the creation date of the last registered User.
 */
interface LastModifiedDeterminator
{
    /**
     * @return DateTime|null
     */
    public function getLastModified(Request $request);
}
