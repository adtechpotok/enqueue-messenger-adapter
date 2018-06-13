<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Transport;

use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception\RepeatMessageException;
use Enqueue\AmqpBunny\AmqpProducer;
use Enqueue\MessengerAdapter\ContextManager;
use Enqueue\MessengerAdapter\Exception\RejectMessageException;
use Enqueue\MessengerAdapter\Exception\RequeueMessageException;
use Enqueue\MessengerAdapter\Exception\SendingMessageFailedException;
use Interop\Queue\Exception as InteropQueueException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\DecoderInterface;
use Symfony\Component\Messenger\Transport\Serialization\EncoderInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RabbitMQTransport implements TransportInterface
{
    private $decoder;
    private $encoder;
    private $contextManager;
    private $options;
    private $debug;
    private $shouldStop;

    public function __construct(DecoderInterface $decoder, EncoderInterface $encoder, ContextManager $contextManager, array $options = [], $debug = false)
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
     */
    public function receive(callable $handler): void
    {
        $psrContext = $this->contextManager->psrContext();
        $destination = $this->getDestination();
        $queue = $psrContext->createQueue($destination['queue']);
        $consumer = $psrContext->createConsumer($queue);

        if ($this->debug) {
            $this->contextManager->ensureExists($destination);
        }

        while (!$this->shouldStop) {
            try {
                if (null === ($message = $consumer->receive($this->options['receiveTimeout'] ?? 0))) {
                    continue;
                }
            } catch (\Exception $e) {
                if ($this->contextManager->recoverException($e, $destination)) {
                    continue;
                }
                throw $e;
            }

            // TODO - тоже в try
            $envelope = $this->decoder->decode(
                [
                    'body' => $message->getBody(),
                    'headers' => $message->getHeaders(),
                    'properties' => $message->getProperties(),
                ]
            );

            try {
                $handler($envelope);
                $consumer->acknowledge($message);
            } catch (RejectMessageException $e) {
                $consumer->reject($message);
            } catch (RepeatMessageException $e) {
                // удаляем исходное сообщение
                $consumer->reject($message);
                // отправляем копию в отложенную очередь
                $attempts = $envelope->get(AttemptsMessage::class);
                if (null === $attempts) {
                    $attempts = new AttemptsMessage($e->getTimeToDelay(), $e->getMaxAttempts());
                }
                if ($attempts->isRepeatable()) {
                    $this->send($envelope->with($attempts));
                }
            } catch (RequeueMessageException $e) {
                $consumer->reject($message, true);
            } catch (\Throwable $e) {
                $consumer->reject($message);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function send(Envelope $message): void
    {
        $psrContext = $this->contextManager->psrContext();
        $destination = $this->getDestination();
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

        /** @var AttemptsMessage $attempts */
        $attempts = $message->get(AttemptsMessage::class);
        if (null !== $attempts) {
            $producer->setDeliveryDelay($attempts->getNowDelayToMs());
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
                'deliveryDelay' => null,
                'priority' => null,
                'timeToLive' => null,
                'topic' => ['name' => 'messages'],
                'queue' => ['name' => 'messages'],
            ]
        );

        $resolver->setAllowedTypes('receiveTimeout', ['null', 'int']);
        $resolver->setAllowedTypes('deliveryDelay', ['null', 'int']);
        $resolver->setAllowedTypes('priority', ['null', 'int']);
        $resolver->setAllowedTypes('timeToLive', ['null', 'int']);
    }

    private function getDestination(): array
    {
        return [
            'topic' => $this->options['topic']['name'],
            'queue' => $this->options['queue']['name'],
        ];
    }
}
