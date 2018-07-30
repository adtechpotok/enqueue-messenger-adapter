<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Transport;

use Enqueue\MessengerAdapter\QueueInteropTransport;
use Interop\Queue\PsrContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Messenger\Transport\Serialization\DecoderInterface;
use Symfony\Component\Messenger\Transport\Serialization\EncoderInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class QueueInteropTransportFactory extends \Enqueue\MessengerAdapter\QueueInteropTransportFactory
{
    private $decoder;
    private $encoder;
    private $debug;
    private $container;

    public function __construct(DecoderInterface $decoder, EncoderInterface $encoder, ContainerInterface $container, bool $debug = false)
    {
        parent::__construct($decoder, $encoder, $container, $debug);

        $this->encoder = $encoder;
        $this->decoder = $decoder;
        $this->container = $container;
        $this->debug = $debug;
    }

    public function createTransport(string $dsn, array $options): TransportInterface
    {
        list($contextManager, $options) = $this->parseDsn($dsn);

        return new QueueInteropTransport(
            $this->decoder,
            $this->encoder,
            $contextManager,
            $options,
            $this->debug
        );
    }

    private function parseDsn(string $dsn): array
    {
        $parsedDsn = parse_url($dsn);
        $enqueueContextName = $parsedDsn['host'];

        $amqpOptions = [];
        if (isset($parsedDsn['query'])) {
            parse_str($parsedDsn['query'], $parsedQuery);
            $parsedQuery = array_map(
                function ($e) {
                    return is_numeric($e) ? (int) $e : $e;
                },
                $parsedQuery
            );
            $amqpOptions = array_replace_recursive($amqpOptions, $parsedQuery);
        }

        if (!$this->container->has($contextService = 'enqueue.transport.'.$enqueueContextName.'.context')) {
            throw new \RuntimeException(
                sprintf(
                    'Can\'t find Enqueue\'s transport named "%s": Service "%s" is not found.',
                    $enqueueContextName,
                    $contextService
                )
            );
        }

        $psrContext = $this->container->get($contextService);
        if (!$psrContext instanceof PsrContext) {
            throw new \RuntimeException(sprintf('Service "%s" not instanceof PsrContext', $contextService));
        }

        return [
            new AmqpContextManager($psrContext),
            $amqpOptions,
        ];
    }
}
