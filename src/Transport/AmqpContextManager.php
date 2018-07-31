<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Transport;

use Bunny\Exception\ClientException;
use Enqueue\MessengerAdapter\ContextManager;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use Interop\Queue\PsrContext;

class AmqpContextManager implements ContextManager
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

        $topic = $this->psrContext->createTopic($destination['topic']['name']);
        $topic->setType($destination['topic']['type']);
        $topic->addFlag(AmqpTopic::FLAG_DURABLE);
        $this->psrContext->declareTopic($topic);

        foreach ($destination['queue'] as $routingKey => $name) {
            $queue = $this->psrContext->createQueue($name);
            $queue->addFlag(AmqpQueue::FLAG_DURABLE);
            $queue->setArgument('x-max-priority', 255);
            $this->psrContext->declareQueue($queue);
            $this->psrContext->bind(new AmqpBind($queue, $topic, '*' !== $routingKey ? $routingKey : null));
        }

        return true;
    }
}
