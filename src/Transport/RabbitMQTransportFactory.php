<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Transport;

use Enqueue\AmqpTools\DelayStrategyAware;
use Interop\Queue\PsrContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Messenger\Transport\ReceiverInterface;
use Symfony\Component\Messenger\Transport\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\DecoderInterface;
use Symfony\Component\Messenger\Transport\Serialization\EncoderInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class RabbitMQTransportFactory implements TransportFactoryInterface
{
    private $decoder;
    private $encoder;
    private $debug;
    private $container;

    public function __construct(DecoderInterface $decoder, EncoderInterface $encoder, ContainerInterface $container, bool $debug = false)
    {
        $this->encoder = $encoder;
        $this->decoder = $decoder;
        $this->container = $container;
        $this->debug = $debug;
    }

    // BC layer for Symfony 4.1 beta1
    public function createReceiver(string $dsn, array $options): ReceiverInterface
    {
        return $this->createTransport($dsn, $options);
    }

    // BC layer for Symfony 4.1 beta1
    public function createSender(string $dsn, array $options): SenderInterface
    {
        return $this->createTransport($dsn, $options);
    }

    public function createTransport(string $dsn, array $options): TransportInterface
    {
        [$contextManager, $options] = $this->parseDsn($dsn);

        return new RabbitMQTransport(
            $this->decoder,
            $this->encoder,
            $contextManager,
            $options,
            $this->debug
        );
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === strpos($dsn, 'adtech-enqueue://');
    }

    private function parseDsn(string $dsn): array
    {
        $parsedDsn = parse_url($dsn);
        $enqueueContextName = $parsedDsn['host'];

        $amqpOptions = [];
        if (isset($parsedDsn['query'])) {
            parse_str($parsedDsn['query'], $parsedQuery);
            $parsedQuery = array_map(function ($e) {
                return is_numeric($e) ? (int)$e : $e;
            }, $parsedQuery);
            $amqpOptions = array_replace_recursive($amqpOptions, $parsedQuery);
        }

        if (!$this->container->has($contextService = 'enqueue.transport.' . $enqueueContextName . '.context')) {
            throw new \RuntimeException(sprintf(
                'Can\'t find Enqueue\'s transport named "%s": Service "%s" is not found.',
                $enqueueContextName,
                $contextService
            ));
        }

        $psrContext = $this->container->get($contextService);
        if (!$psrContext instanceof PsrContext) {
            throw new \RuntimeException(sprintf('Service "%s" not instanceof PsrContext', $contextService));
        }
        if ($psrContext instanceof DelayStrategyAware) {
            $psrContext->setDelayStrategy(new RabbitMqDelayPluginDelayStrategy());
        }

        return [
            new RabbitMQContextManager($psrContext),
            $amqpOptions,
        ];
    }
}
