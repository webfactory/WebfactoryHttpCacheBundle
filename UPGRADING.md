# Upgrade notes for `WebfactoryHttpCacheBundle`

## Version 2.0.0

* Annotations support and the `\Webfactory\HttpCacheBundle\NotModified\Annotation\ReplaceWithNotModifiedResponse` class have been removed.

## Version 1.4.0

* The `\Webfactory\HttpCacheBundle\NotModified\Annotation\ReplaceWithNotModifiedResponse` annotation has been deprecated. Use the 
  `\Webfactory\HttpCacheBundle\NotModified\Attribute\ReplaceWithNotModifiedResponse` attribute for configuration instead. 
