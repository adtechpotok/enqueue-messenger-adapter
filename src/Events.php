<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle;

final class Events
{
    public const REJECT = 'enqueue.messenger.reject';
    public const REPEAT = 'enqueue.messenger.repeat';
    public const REQUEUE = 'enqueue.messenger.requeue';
    public const THROWABLE = 'enqueue.messenger.throwable';
}
