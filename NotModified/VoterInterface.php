<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\HttpCacheBundle\NotModified;

use Symfony\Component\HttpFoundation\Request;

/**
 * The ReplaceWithNotModifiedResponse method annotation is parameterised with Voters. Each Voter should determine the
 * last modified date of one of the various underlying resources for a response. E.g. if your controller's indexAction
 * builds a response containing News and Users, you can write a NewsVoter determining the date of the last published
 * News and a UserVoter determining the creation date of the last registered User.
 */
interface VoterInterface
{
    /**
     * @param Request $request
     * @return \DateTime|null
     */
    public function getLastModified(Request $request);
}
