<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Transport;

use Enqueue\AmqpBunny\AmqpProducer;
use Enqueue\AmqpTools\DelayStrategy;
use Enqueue\AmqpTools\DelayStrategyAware;
use Enqueue\MessengerAdapter\ContextManager;
use Enqueue\MessengerAdapter\EnvelopeItem\RepeatMessage;
use Enqueue\MessengerAdapter\EnvelopeItem\TransportConfiguration;
use Enqueue\MessengerAdapter\Exception\RejectMessageException;
use Enqueue\MessengerAdapter\Exception\RepeatMessageException;
use Enqueue\MessengerAdapter\Exception\RequeueMessageException;
use Enqueue\MessengerAdapter\Exception\SendingMessageFailedException;
use Interop\Queue\DeliveryDelayNotSupportedException;
use Interop\Queue\Exception as InteropQueueException;
use Interop\Queue\PriorityNotSupportedException;
use Interop\Queue\TimeToLiveNotSupportedException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\DecoderInterface;
use Symfony\Component\Messenger\Transport\Serialization\EncoderInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QueueInteropTransport implements TransportInterface
{
    private $decoder;
    private $encoder;
    private $contextManager;
    private $options;
    private $debug;
    private $shouldStop;

    public function __construct(
        DecoderInterface $decoder,
        EncoderInterface $encoder,
        ContextManager $contextManager,
        array $options = [],
        $debug = false
    )
    {
        $this->decoder = $decoder;
        $this->encoder = $encoder;
        $this->contextManager = $contextManager;
        $this->debug = $debug;

        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $this->options = $resolver->resolve($options);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function receive(callable $handler): void
    {
        $psrContext = $this->contextManager->psrContext();
        $destination = $this->getDestination(null);
        $queue = $psrContext->createQueue($destination['queue']);
        $consumer = $psrContext->createConsumer($queue);

        if ($this->debug) {
            $this->contextManager->ensureExists($destination);
        }

        while (!$this->shouldStop) {
            try {
                if (null === ($message = $consumer->receive($this->options['receiveTimeout'] ?? 0))) {
                    $handler(null);
                    continue;
                }
            } catch (\Exception $e) {
                if ($this->contextManager->recoverException($e, $destination)) {
                    continue;
                }

                throw $e;
            }

            try {
                $envelope = $this->decoder->decode(
                    [
                        'body' => $message->getBody(),
                        'headers' => $message->getHeaders(),
                        'properties' => $message->getProperties(),
                    ]
                );
                $handler($envelope);

                $consumer->acknowledge($message);
            } catch (RepeatMessageException $e) {
                $consumer->reject($message);

                $repeat = $envelope->get(RepeatMessage::class);
                if (null === $repeat) {
                    $repeat = new RepeatMessage($e->getTimeToDelay(), $e->getMaxAttempts());
                }
                if ($repeat->isRepeatable()) {
                    $this->send($envelope->with($repeat));
                }
            } catch (RejectMessageException $e) {
                $consumer->reject($message);
            } catch (RequeueMessageException $e) {
                $consumer->reject($message, true);
            } catch (\Throwable $e) {
                $consumer->reject($message);
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws DeliveryDelayNotSupportedException
     * @throws PriorityNotSupportedException
     * @throws TimeToLiveNotSupportedException
     */
    public function send(Envelope $message): void
    {
        $psrContext = $this->contextManager->psrContext();
        $destination = $this->getDestination($message);
        $topic = $psrContext->createTopic($destination['topic']);

        if ($this->debug) {
            $this->contextManager->ensureExists($destination);
        }

        $encodedMessage = $this->encoder->encode($message);

        $psrMessage = $psrContext->createMessage(
            $encodedMessage['body'],
            $encodedMessage['properties'] ?? [],
            $encodedMessage['headers'] ?? []
        );

        /** @var AmqpProducer $producer */
        $producer = $psrContext->createProducer();

        if (isset($this->options['deliveryDelay'])) {
            if ($producer instanceof DelayStrategyAware) {
                $producer->setDelayStrategy($this->options['delayStrategy']);
            }
            $producer->setDeliveryDelay($this->options['deliveryDelay']);
        }
        if (isset($this->options['priority'])) {
            $producer->setPriority($this->options['priority']);
        }
        if (isset($this->options['timeToLive'])) {
            $producer->setTimeToLive($this->options['timeToLive']);
        }

        /** @var RepeatMessage $repeat */
        $repeat = $message->get(RepeatMessage::class);
        if (null !== $repeat) {
            $producer->setDeliveryDelay($repeat->getNowDelayToMs());
        }

        try {
            $producer->send($topic, $psrMessage);
        } catch (InteropQueueException $e) {
            if ($this->contextManager->recoverException($e, $destination)) {
                // The context manager recovered the exception, we re-try.
                $this->send($message);

                return;
            }

            throw new SendingMessageFailedException($e->getMessage(), null, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        $this->shouldStop = true;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'receiveTimeout' => null,
            'deliveryDelay' => null,
            'delayStrategy' => RabbitMq375DelayPluginDelayStrategy::class,
            'priority' => null,
            'timeToLive' => null,
            'maximumPriority' => null,
            'durability' => 1,
            'topic' => ['name' => 'messages', 'type' => 'topic'],
            'queue' => ['name' => 'messages'],
        ]);

        $resolver->setAllowedTypes('receiveTimeout', ['null', 'int']);
        $resolver->setAllowedTypes('deliveryDelay', ['null', 'int']);
        $resolver->setAllowedTypes('priority', ['null', 'int']);
        $resolver->setAllowedTypes('timeToLive', ['null', 'int']);
        $resolver->setAllowedTypes('delayStrategy', ['null', 'string']);
        $resolver->setAllowedTypes('maximumPriority', ['null', 'int']);
        $resolver->setAllowedTypes('durability', ['null', 'int']);

        $resolver->setNormalizer('delayStrategy', function (Options $options, $value) {
            if (null === $value) {
                return null;
            }

            $delayStrategy = new $value();
            if (!$delayStrategy instanceof DelayStrategy) {
                throw new \InvalidArgumentException(sprintf(
                    'The delayStrategy option must be either instance of "%s", but got "%s"',
                    DelayStrategy::class,
                    $value
                ));
            }

            return $delayStrategy;
        });
    }

    private function getDestination(?Envelope $message): array
    {
        /** @var TransportConfiguration|null $configuration */
        $configuration = $message ? $message->get(TransportConfiguration::class) : null;
        $topic = null !== $configuration ? $configuration->getTopic() : null;

        return [
            'topic' => $topic ?? $this->options['topic']['name'],
            'queue' => $this->options['queue']['name'],
        ];
    }
}
