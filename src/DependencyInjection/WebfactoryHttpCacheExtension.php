<?php

namespace Webfactory\HttpCacheBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class WebfactoryHttpCacheExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $locator = new FileLocator(__DIR__.'/../NotModified');
        $loader = new XmlFileLoader($container, $locator);
        $loader->load('services.xml');
    }
}
