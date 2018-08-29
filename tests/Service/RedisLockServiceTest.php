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

        $attempt = random_int(0, 100);

        $firstWorkerId = 1;

        /** @var MockObject|Client $redis */
        $redis = $this->createPartialMock(Client::class, [
            'hmget',
            'multi',
            'exec',
            'hset',
            'watch',
            '__call',
            'expire',
        ]);

        $redis->expects($this->exactly(1))
            ->method('hmget')
            ->with($messageUuid.'_attempt_'.$attempt)
            ->willReturn([$firstWorkerId + 1]);

        $service = new RedisLockService($redis);

        $this->expectException(MessageLocked::class);

        $service->lock($firstWorkerId, $messageUuid, $attempt);
    }

    public function testWritingKeyNotEqualWrittenKeyException()
    {
        $messageUuid = uniqid('unit_', 'unit_');

        $attempt = random_int(0, 100);

        /** @var MockObject|Client $redis */
        $redis = $this->createPartialMock(Client::class, [
            'hmget',
            'multi',
            'exec',
            'hset',
            'watch',
            '__call',
            'expire',
        ]);

        $firstWorkerId = 1;

        $redis->expects($this->exactly(2))
            ->method('hmget')
            ->with($messageUuid.'_attempt_'.$attempt)
            ->willReturn(null, [$firstWorkerId + 1]);

        $redis->expects($this->once())
            ->method('multi')
            ->willReturn(true);

        $redis->expects($this->once())
            ->method('watch')
            ->with($messageUuid.'_attempt_'.$attempt)
            ->willReturn(true);

        $redis->expects($this->once())
            ->method('exec')
            ->willReturn(true);

        $redis->expects($this->once())
            ->method('hset')
            ->willReturn(true);

        $redis->expects($this->exactly(1))
            ->method('expire')
            ->with($messageUuid.'_attempt_'.$attempt, RedisLockService::DEFAULT_LIFETIME);

        $service = new RedisLockService($redis);

        $this->expectException(WritingKeyNotEqualWrittenKey::class);

        $service->lock($firstWorkerId, $messageUuid, $attempt);
    }
}
