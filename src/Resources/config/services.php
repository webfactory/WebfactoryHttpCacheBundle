<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function(ContainerConfigurator $container) {
    $services = $container->services();
    $parameters = $container->parameters();

    $services->set(\Webfactory\HttpCacheBundle\NotModified\EventListener::class, \Webfactory\HttpCacheBundle\NotModified\EventListener::class)
        ->public()
        ->args([
            service('service_container'),
            '%kernel.debug%',
        ])
        ->tag('kernel.event_listener', ['event' => 'kernel.controller', 'priority' => -200])
        ->tag('kernel.event_listener', ['event' => 'kernel.response']);
};
