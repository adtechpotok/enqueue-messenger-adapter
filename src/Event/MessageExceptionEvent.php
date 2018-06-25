<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Event;

use Interop\Queue\PsrMessage;
use Symfony\Component\EventDispatcher\Event;

class MessageExceptionEvent extends Event
{
    private $message;
    private $exception;

    public function __construct(PsrMessage $message, \Throwable $exception)
    {
        $this->message = $message;
        $this->exception = $exception;
    }

    public function getMessage(): PsrMessage
    {
        return $this->message;
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }
}
