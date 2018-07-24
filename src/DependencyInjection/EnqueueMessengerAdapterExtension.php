<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\DependencyInjection;

use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Transport\QueueInteropTransportFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class EnqueueMessengerAdapterExtension extends Extension implements CompilerPassInterface
{
    public function load(array $configs, ContainerBuilder $container)
    {
    }

    public function process(ContainerBuilder $container)
    {
        $definition = $container->findDefinition('enqueue.messenger_transport.factory');
        $definition->setClass(QueueInteropTransportFactory::class);
    }
}
