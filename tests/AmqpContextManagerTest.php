<?php

namespace Tests;

use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Transport\AmqpContextManager;
use Enqueue\AmqpBunny\AmqpContext;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use Interop\Queue\PsrContext;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

class AmqpContextManagerTest extends TestCase
{
    /**
     * @return array[]
     */
    public function getDataProvider(): array
    {
        return [
            'single_queue' => [
                'destination' => [
                    'topic' => [
                        'name' => 'topicName',
                        'type' => AmqpTopic::TYPE_TOPIC,
                    ],
                    'queue' => [
                        'routingKey' => 'queueName',
                    ],
                ],
            ],
            'multiple_queue' => [
                'destination' => [
                    'topic' => [
                        'name' => 'topicName',
                        'type' => AmqpTopic::TYPE_DIRECT,
                    ],
                    'queue' => [
                        'routingKey' => 'queueName',
                        '*'          => 'queueName',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array $destination
     *
     * @dataProvider getDataProvider
     *
     * @throws InvalidArgumentException
     * @throws \InvalidArgumentException
     */
    public function testAmqpContextManager(array $destination): void
    {
        $queuePsrContext = $this->createQueuePsrContextMock($destination);
        $contextManager = new AmqpContextManager($queuePsrContext);

        $this->assertTrue($contextManager->ensureExists($destination));
    }

    /**
     * @throws InvalidArgumentException
     * @throws \InvalidArgumentException
     */
    public function testAmqpContextManagerNotAmqpContext(): void
    {
        $psrContextMock = $this->createMock(PsrContext::class);
        $contextManager = new AmqpContextManager($psrContextMock);

        $this->assertFalse($contextManager->ensureExists([]));
    }

    /**
     * @param array $destination
     *
     * @throws \InvalidArgumentException
     *
     * @return MockObject|AmqpContext
     */
    protected function createQueuePsrContextMock(array $destination): MockObject
    {
        $topicParams = $destination['topic'];
        $topic = $this->createTopicMock($topicParams);

        $queuePsrContext = $this->createMock(AmqpContext::class);

        $queuePsrContext->expects($this->once())
            ->method('createTopic')
            ->with($this->equalTo($topicParams['name']))
            ->willReturn($topic);

        $queueCount = \count($destination['queue']);
        $queue = $this->createQueueMock($queueCount);

        $queuePsrContext->expects($this->exactly($queueCount))
            ->method('createQueue')
            ->with($this->equalTo($destination['queue']['routingKey']))
            ->willReturn($queue);

        $queuePsrContext->expects($this->exactly($queueCount))
            ->method('declareQueue')
            ->with($queue);

        $queuePsrContext->expects($this->exactly($queueCount))
            ->method('bind')
            ->withConsecutive(
                new AmqpBind($queue, $topic, key($destination['queue'])),
                new AmqpBind($queue, $topic, null)
            );

        return $queuePsrContext;
    }

    /**
     * @param string[] $topicParams
     *
     * @throws \InvalidArgumentException
     *
     * @return MockObject|AmqpTopic
     */
    protected function createTopicMock(array $topicParams): MockObject
    {
        $topic = $this->createMock(AmqpTopic::class);

        $topic->expects($this->once())
            ->method('setType')
            ->with($topicParams['type']);

        return $topic;
    }

    /**
     * @param int $queueCount
     *
     * @throws \InvalidArgumentException
     *
     * @return MockObject|AmqpQueue
     */
    protected function createQueueMock(int $queueCount): MockObject
    {
        $queue = $this->createMock(AmqpQueue::class);

        $queue->expects($this->exactly($queueCount))
            ->method('addFlag')
            ->with(AmqpQueue::FLAG_DURABLE);

        $queue->expects($this->exactly($queueCount))
            ->method('setArgument')
            ->with('x-max-priority', 255);

        return $queue;
    }
}
