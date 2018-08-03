<?php

namespace Tests;

use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Transport\AmqpContextManager;
use Enqueue\AmqpBunny\AmqpContext;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
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
            'returns_true' => [
                'destination' => [
                    'topic' => [
                        'name' => 'topicName',
                        'type' => AmqpTopic::TYPE_TOPIC,
                    ],
                    'queue' => [
                        'routingKey' => 'queueName',
                    ],
                ],
                'context_class' => AmqpContext::class,
                'expected'      => true,
            ],
//            'returns_false' => [
//                'destination' => [
//                    'topic' => [
//                        'name' => 'topicName',
//                        'type' => AmqpTopic::TYPE_TOPIC,
//                    ],
//                    'queue' => [
//                        'routingKey' => 'queueName',
//                    ]
//                ],
//                'context_class' => , // TODO: придумать как сюда передать какой-нибудь класс, отличный от AmqpContext
//                'expected' => false,
//            ],
        ];
    }

    /**
     * @param array  $destination
     * @param string $contextClass
     * @param bool   $expected
     *
     * @dataProvider getDataProvider
     *
     * @throws InvalidArgumentException
     * @throws \InvalidArgumentException
     */
    public function testAmqpContextManager(array $destination, string $contextClass, bool $expected): void
    {
        $queuePsrContext = $this->createQueuePsrContextMock($contextClass, $destination);

        $contextManager = new AmqpContextManager($queuePsrContext);

        static::assertEquals($expected, $contextManager->ensureExists($destination));
    }

    /**
     * @param string $contextClass
     * @param array  $destination
     *
     * @throws \InvalidArgumentException
     *
     * @return MockObject|AmqpContext
     */
    protected function createQueuePsrContextMock(string $contextClass, array $destination): MockObject
    {
        $topicParams = $destination['topic'];
        $topic = $this->createTopicMock($topicParams);

        $queuePsrContext = $this->createMock($contextClass);

        $queuePsrContext->expects($this->once())
            ->method('createTopic')
            ->with($this->equalTo($topicParams['name']))
            ->willReturn($topic);

        $queue = $this->createQueueMock();

        $queuePsrContext->expects($this->once())
            ->method('createQueue')
            ->with($this->equalTo($destination['queue']['routingKey']))
            ->willReturn($queue);

        $queuePsrContext->expects($this->once())
            ->method('declareQueue')
            ->with($queue);

        $queuePsrContext->expects($this->once())
            ->method('bind')
            ->with(new AmqpBind($queue, $topic, key($destination['queue'])));

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
     * @throws \InvalidArgumentException
     *
     * @return MockObject|AmqpQueue
     */
    protected function createQueueMock(): MockObject
    {
        $queue = $this->createMock(AmqpQueue::class);

        $queue->expects($this->once())
            ->method('addFlag')
            ->with(AmqpQueue::FLAG_DURABLE);

        $queue->expects($this->once())
            ->method('setArgument')
            ->with('x-max-priority', 255);

        return $queue;
    }
}
