<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Service;

use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception\MessageLocked;
use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception\MissedUuidEnvelopeItem;
use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception\WritingKeyNotEqualWrittenKey;
use Predis\Client;

class RedisLockService
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $key;

    /**
     * RedisLockService constructor.
     *
     * @param Client $client
     * @param string $key
     */
    public function __construct(Client $client, string $key)
    {
        $this->client = $client;
        $this->key = $key;
    }

    /**
     * @param string $lockId
     * @param mixed  $lockField
     *
     * @throws MissedUuidEnvelopeItem
     * @throws WritingKeyNotEqualWrittenKey
     * @throws MessageLocked
     *
     * @return bool
     */
    public function lock($lockId, $lockField = 'worker_id'): bool
    {
        $this->client->watch($this->key);

        $item = $this->client->hmget($this->key, [$lockField]);

        if (!$item || !$item[0]) {
            $this->client->multi();
            $this->client->hset($this->key, $lockField, $lockId);
            $this->client->exec();

            $writtenId = $this->client->hmget($this->key, [$lockField])[0];

            if ((string) $writtenId !== (string) $lockId) {
                // another client locked it earlier
                throw (new WritingKeyNotEqualWrittenKey())->setLockKeys($lockId, $writtenId);
            }

            return true;
        }

        throw (new MessageLocked())->setRedisKey($this->key);
    }
}
