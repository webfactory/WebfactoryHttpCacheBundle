<?php

namespace Webfactory\HttpCacheBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Symfony Bundle Extension class.
 */
class WebfactoryHttpCacheExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $locator = new FileLocator(__DIR__.'/../NotModified');
        $yamlLoader = new XmlFileLoader($container, $locator);
        $yamlLoader->load('services.xml');
    }
}
