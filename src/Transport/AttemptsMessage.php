<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Transport;

use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception\RepeatMessageException;
use Symfony\Component\Messenger\EnvelopeItemInterface;

class AttemptsMessage implements EnvelopeItemInterface
{
    /** @var int время задержки в секундах */
    private $timeToDelay;
    /** @var int максимальное кол-во попыток */
    private $maxAttempts;
    /** @var int счетчик попыток */
    private $attempts;

    public function __construct(
        int $timeToDelay = RepeatMessageException::DEFAULT_DELAY,
        int $maxAttempts = RepeatMessageException::DEFAULT_ATTEMPTS,
        int $attempts = 0
    )
    {
        $this->timeToDelay = $timeToDelay;
        $this->maxAttempts = $maxAttempts;
        $this->attempts = $attempts;
    }

    public function isRepeatable(): bool
    {
        return $this->attempts < $this->maxAttempts;
    }

    /**
     * возвращает кол-во миллисекунд на который в данные момент надо отложить сообщение.
     *
     * @return int
     */
    public function getNowDelayToMs(): int
    {
        return $this->timeToDelay * ($this->attempts + 1) * 1000;
    }

    public function getTimeToDelay(): int
    {
        return $this->timeToDelay;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function serialize(): string
    {
        return serialize(['timeToDelay' => $this->timeToDelay, 'attempts' => $this->attempts, 'maxAttempts' => $this->maxAttempts]);
    }

    public function unserialize($serialized): void
    {
        ['timeToDelay' => $timeToDelay, 'maxAttempts' => $maxAttempts, 'attempts' => $attempts] = unserialize($serialized, ['allowed_classes' => false]);
        $this->__construct($timeToDelay, $maxAttempts, $attempts + 1);
    }
}
