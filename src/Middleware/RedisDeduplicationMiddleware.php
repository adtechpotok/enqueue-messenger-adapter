<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Middleware;

use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Contract\UniqueIdGetterInterface;
use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception\MessageLocked;
use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception\MissedUuidEnvelopeItem;
use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception\WritingKeyNotEqualWrittenKey;
use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Service\RedisLockService;
use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\UuidEnvelopeItem;
use Enqueue\MessengerAdapter\EnvelopeItem\QueueName;
use Predis\Client;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EnvelopeAwareInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;

class RedisDeduplicationMiddleware implements MiddlewareInterface, EnvelopeAwareInterface
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var UniqueIdGetterInterface
     */
    protected $uniqueIdGetter;

    /**
     * @var string
     */
    protected $keyPrefix;

    /**
     * RedisDeduplicationMiddleware constructor.
     *
     * @param Client                  $client
     * @param UniqueIdGetterInterface $uniqueIdGetter
     * @param string                  $keyPrefix
     */
    public function __construct(
        Client $client,
        UniqueIdGetterInterface $uniqueIdGetter,
        string $keyPrefix
    ) {
        $this->client = $client;
        $this->uniqueIdGetter = $uniqueIdGetter;
        $this->keyPrefix = $keyPrefix;
    }

    /**
     * @param \Symfony\Component\Messenger\Envelope $envelope
     * @param callable                              $next
     *
     * @return mixed
     */
    public function handle($envelope, callable $next)
    {
        if (!$envelope instanceof Envelope) {
            throw new \RuntimeException('Envelope was expected but actual '.get_class($envelope));
        }

        $this->lock($envelope);

        return $next($envelope);
    }

    /**
     * @param \Symfony\Component\Messenger\Envelope $envelope
     *
     * @throws MissedUuidEnvelopeItem
     * @throws WritingKeyNotEqualWrittenKey
     * @throws MessageLocked
     *
     * @return mixed
     */
    public function lock(Envelope $envelope)
    {
        /** @var UuidEnvelopeItem|null $uuid */
        $uuid = $envelope->get(UuidEnvelopeItem::class);

        if (!$uuid) {
            throw (new MissedUuidEnvelopeItem())->setEnvelope($envelope);
        }

        /** @var QueueName $queueName */
        $queueName = $envelope->get(QueueName::class);

        if ($queueName) {
            $key = sprintf('%s%s_%s', $this->keyPrefix, $queueName->getQueueName(), $uuid->getUuid());
        } else {
            $key = $this->keyPrefix.$uuid->getUuid();
        }

        (new RedisLockService($this->client, $key))
            ->lock($this->uniqueIdGetter->getUniqueId());
    }
}
