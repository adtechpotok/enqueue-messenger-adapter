<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Middleware;

use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Contract\UniqueIdGetterInterface;
use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\EnvelopeItem\UuidItem;
use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception\MessageLocked;
use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception\MissedUuidEnvelopeItem;
use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception\WritingKeyNotEqualWrittenKey;
use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Service\LockContract;
use Enqueue\MessengerAdapter\EnvelopeItem\QueueName;
use Enqueue\MessengerAdapter\EnvelopeItem\RepeatMessage;
use InvalidArgumentException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EnvelopeAwareInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;

class LockBasedDeduplicationMiddleware implements MiddlewareInterface, EnvelopeAwareInterface
{
    /**
     * @var LockContract
     */
    protected $locker;

    /**
     * @var UniqueIdGetterInterface
     */
    protected $uniqueIdGetter;

    /**
     * LockBasedDeduplicationMiddleware constructor.
     *
     * @param LockContract            $locker
     * @param UniqueIdGetterInterface $uniqueIdGetter
     */
    public function __construct(LockContract $locker, UniqueIdGetterInterface $uniqueIdGetter)
    {
        $this->locker = $locker;
        $this->uniqueIdGetter = $uniqueIdGetter;
    }

    /**
     * @param Envelope $envelope
     * @param callable $next
     *
     * @return mixed
     */
    public function handle($envelope, callable $next)
    {
        if (!$envelope instanceof Envelope) {
            throw new InvalidArgumentException('Envelope was expected but actual '.\get_class($envelope));
        }

        $this->lock($envelope);

        return $next($envelope);
    }

    /**
     * @param Envelope $envelope
     *
     * @throws MissedUuidEnvelopeItem
     * @throws WritingKeyNotEqualWrittenKey
     * @throws MessageLocked
     */
    public function lock(Envelope $envelope): void
    {
        /** @var UuidItem|null $uuid */
        $uuid = $envelope->get(UuidItem::class);

        if (!$uuid) {
            throw (new MissedUuidEnvelopeItem())->setEnvelope($envelope);
        }

        /** @var QueueName|null $queueName */
        $queueName = $envelope->get(QueueName::class);

        $attempt = 0;

        if ($repeat = $envelope->get(RepeatMessage::class)) {
            /** @var RepeatMessage $repeat */
            $attempt = $repeat->getAttempts();
        }

        $this->locker->lock(
            $this->uniqueIdGetter->getUniqueId(),
            $uuid->getUuid(),
            $attempt,
            $queueName ? $queueName->getQueueName() : null
        );
    }
}
