<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class EnqueueMessengerAdapterExtension extends Extension implements CompilerPassInterface
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $fileLocator = new FileLocator(__DIR__.'/../Resources/config');
        $loader = new YamlFileLoader($container, $fileLocator);
        $loader->load('services.yml');
    }

    public function process(ContainerBuilder $container)
    {
    }
}
