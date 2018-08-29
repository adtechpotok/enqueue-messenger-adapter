<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Service;

use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception\MessageLocked;
use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception\WritingKeyNotEqualWrittenKey;
use Predis\ClientInterface;

class RedisLockService implements LockContract
{
    public const DEFAULT_LIFETIME = 172800; // 2 days

    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var string
     */
    protected $lockField;

    /**
     * @var string
     */
    protected $keyPrefix;

    /**
     * @var int
     */
    protected $keyLifetimeSeconds;

    /**
     * RedisLockService constructor.
     *
     * @param ClientInterface $client
     * @param string          $keyPrefix
     * @param int             $keyLifetimeSeconds
     * @param string          $lockField
     */
    public function __construct(ClientInterface $client,
        string $keyPrefix = '',
        int $keyLifetimeSeconds = self::DEFAULT_LIFETIME,
        string $lockField = 'worker_id'
    ) {
        $this->client = $client;
        $this->keyPrefix = $keyPrefix;
        $this->keyLifetimeSeconds = $keyLifetimeSeconds;
        $this->lockField = $lockField;
    }

    /**
     * @param string      $workerId
     * @param string      $messageUuid
     * @param int         $attempt     = 0
     * @param null|string $queueName
     *
     * @throws MessageLocked
     * @throws WritingKeyNotEqualWrittenKey
     *
     * @return bool
     */
    public function lock(string $workerId, string $messageUuid, int $attempt = 0, ?string $queueName = null): bool
    {
        if ($queueName) {
            $key = sprintf('%s%s_%s_attempt_%d', $this->keyPrefix, $queueName, $messageUuid, $attempt);
        } else {
            $key = sprintf('%s%s_attempt_%d', $this->keyPrefix, $messageUuid, $attempt);
        }

        $this->client->watch($key);

        $item = $this->client->hmget($key, [$this->lockField]);

        if (!$item || !$item[0]) {
            $this->client->multi();
            $this->client->hset($key, $this->lockField, $workerId);
            $this->client->expire($key, $this->keyLifetimeSeconds);
            $this->client->exec();

            $writtenId = $this->client->hmget($key, [$this->lockField])[0];

            if ((string) $writtenId !== $workerId) {
                // another client locked it earlier
                throw (new WritingKeyNotEqualWrittenKey())->setLockKeys($workerId, $writtenId);
            }

            return true;
        }

        throw (new MessageLocked())->setRedisKey($key);
    }
}
