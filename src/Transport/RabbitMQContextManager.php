<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Transport;

use Bunny\Exception\ClientException;
use Enqueue\MessengerAdapter\ContextManager;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use Interop\Queue\PsrContext;

class RabbitMQContextManager implements ContextManager
{
    private $psrContext;

    public function __construct(PsrContext $psrContext)
    {
        $this->psrContext = $psrContext;
    }

    /**
     * {@inheritdoc}
     */
    public function psrContext(): PsrContext
    {
        return $this->psrContext;
    }

    /**
     * {@inheritdoc}
     */
    public function recoverException(\Exception $exception, array $destination): bool
    {
        if ($exception instanceof ClientException && 404 === $exception->getCode()) {
            return $this->ensureExists($destination);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function ensureExists(array $destination): bool
    {
        if (!$this->psrContext instanceof AmqpContext) {
            return false;
        }

        $topic = $this->psrContext->createTopic($destination['topic']);
        $topic->setType(AmqpTopic::TYPE_TOPIC); // TODO - брать из DSN
        $topic->addFlag(AmqpTopic::FLAG_DURABLE); // TODO - брать из DSN
        $this->psrContext->declareTopic($topic);

        $queue = $this->psrContext->createQueue($destination['queue']);
        $queue->addFlag(AmqpQueue::FLAG_DURABLE); // TODO - брать из DSN
        $queue->setArgument('x-max-priority', 255); // TODO - брать из DSN
        $this->psrContext->declareQueue($queue);

        $this->psrContext->bind(new AmqpBind($queue, $topic));

        return true;
    }
}
