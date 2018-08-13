<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Tests\Service;

use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception\MessageLocked;
use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception\WritingKeyNotEqualWrittenKey;
use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Service\RedisLockService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Predis\Client;

class RedisLockServiceTest extends TestCase
{
    public function testMessageLockedException()
    {
        $messageUuid = uniqid('unit_', 'unit_');

        $firstWorkerId = 1;

        /** @var MockObject|Client $redis */
        $redis = $this->createPartialMock(Client::class, [
            'hmget',
            'multi',
            'exec',
            'hset',
            'watch',
            '__call',
        ]);

        $redis->expects($this->exactly(1))
            ->method('hmget')
            ->with($messageUuid)
            ->willReturn([$firstWorkerId + 1]);

        $service = new RedisLockService($redis);

        $this->expectException(MessageLocked::class);

        $service->lock($firstWorkerId, $messageUuid);
    }

    public function testWritingKeyNotEqualWrittenKeyException()
    {
        $messageUuid = uniqid('unit_', 'unit_');

        /** @var MockObject|Client $redis */
        $redis = $this->createPartialMock(Client::class, [
            'hmget',
            'multi',
            'exec',
            'hset',
            'watch',
            '__call',
        ]);

        $firstWorkerId = 1;

        $redis->expects($this->exactly(2))
            ->method('hmget')
            ->with($messageUuid)
            ->willReturn(null, [$firstWorkerId + 1]);

        $redis->expects($this->once())
            ->method('multi')
            ->willReturn(true);

        $redis->expects($this->once())
            ->method('watch')
            ->with($messageUuid)
            ->willReturn(true);

        $redis->expects($this->once())
            ->method('exec')
            ->willReturn(true);

        $redis->expects($this->once())
            ->method('hset')
            ->willReturn(true);

        $service = new RedisLockService($redis);

        $this->expectException(WritingKeyNotEqualWrittenKey::class);

        $service->lock($firstWorkerId, $messageUuid);
    }
}
