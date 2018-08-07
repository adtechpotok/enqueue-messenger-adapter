<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Tests\Service;

use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Service\RedisLockService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Predis\Client;

class RedisLockServiceTest extends TestCase
{
    public function testMessageLockedException()
    {
        $key = uniqid('unit_', 'unit_');

        $redis = new Client();
        $firstWorkerId = 1;
        $secondWorkerId = 2;

        $service = new RedisLockService($redis, $key);
        $service->lock($firstWorkerId);

        try {
            $service->lock($secondWorkerId);
        } catch (\Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception\MessageLocked $e) {
            $this->assertTrue(true);
        }
    }

    public function testWritingKeyNotEqualWrittenKeyException()
    {
        $key = uniqid('unit_', 'unit_');

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
            ->with($key)
            ->willReturn(null, [$firstWorkerId + 1]);

        $redis->expects($this->once())
            ->method('multi')
            ->willReturn(true);

        $redis->expects($this->once())
            ->method('watch')
            ->with($key)
            ->willReturn(true);

        $redis->expects($this->once())
            ->method('exec')
            ->willReturn(true);

        $redis->expects($this->once())
            ->method('hset')
            ->willReturn(true);

        $service = new RedisLockService($redis, $key);

        try {
            $service->lock($firstWorkerId);
        } catch (\Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception\WritingKeyNotEqualWrittenKey $e) {
            $this->assertTrue(true);
        }
    }
}
