<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Transport;

use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Event\MessageExceptionEvent;
use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Events;
use Enqueue\AmqpBunny\AmqpProducer;
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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\DecoderInterface;
use Symfony\Component\Messenger\Transport\Serialization\EncoderInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RabbitMQTransport implements TransportInterface
{
    private $dispatcher;
    private $decoder;
    private $encoder;
    private $contextManager;
    private $options;
    private $debug;
    private $shouldStop;

    public function __construct(
        EventDispatcherInterface $dispatcher,
        DecoderInterface $decoder,
        EncoderInterface $encoder,
        ContextManager $contextManager,
        array $options = [],
        $debug = false
    ) {
        $this->dispatcher = $dispatcher;
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

        while (! $this->shouldStop) {
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
                        'body'       => $message->getBody(),
                        'headers'    => $message->getHeaders(),
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
                } else {
                    $this->dispatcher->dispatch(Events::REPEAT, new MessageExceptionEvent($message, $e));
                }
            } catch (RejectMessageException $e) {
                $consumer->reject($message);
                $this->dispatcher->dispatch(Events::REJECT, new MessageExceptionEvent($message, $e));
            } catch (RequeueMessageException $e) {
                $consumer->reject($message, true);
                $this->dispatcher->dispatch(Events::REQUEUE, new MessageExceptionEvent($message, $e));
            } catch (\Throwable $e) {
                $consumer->reject($message);
                $this->dispatcher->dispatch(Events::THROWABLE, new MessageExceptionEvent($message, $e));
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
        $resolver->setDefaults(
            [
                'receiveTimeout' => null,
                'deliveryDelay'  => null,
                'priority'       => null,
                'timeToLive'     => null,
                'topic'          => ['name' => 'messages'],
                'queue'          => ['name' => 'messages'],
            ]
        );

        $resolver->setAllowedTypes('receiveTimeout', ['null', 'int']);
        $resolver->setAllowedTypes('deliveryDelay', ['null', 'int']);
        $resolver->setAllowedTypes('priority', ['null', 'int']);
        $resolver->setAllowedTypes('timeToLive', ['null', 'int']);
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
