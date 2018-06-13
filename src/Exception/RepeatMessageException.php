<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception;

use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Throwable;

class RepeatMessageException extends \LogicException implements ExceptionInterface
{
    public const DEFAULT_DELAY = 1;
    public const DEFAULT_ATTEMPTS = 3;

    /** @var int время задержки в секундах */
    private $timeToDelay;
    /** @var int максимальное кол-во попыток повтора */
    private $maxAttempts;

    public function __construct(
        int $timeToDelay = self::DEFAULT_DELAY,
        int $maxAttempts = self::DEFAULT_ATTEMPTS,
        string $message = '',
        int $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->timeToDelay = $timeToDelay;
        $this->maxAttempts = $maxAttempts;
    }

    public function getTimeToDelay(): int
    {
        return $this->timeToDelay;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }
}
