<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Middleware;

use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\UuidEnvelopeItem;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EnvelopeAwareInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;

class UuidItemSetterMiddleware implements MiddlewareInterface, EnvelopeAwareInterface
{
    /**
     * @param \Symfony\Component\Messenger\Envelope $envelope
     * @param callable                              $next
     *
     * @throws \RuntimeException
     *
     * @return mixed
     */
    public function handle($envelope, callable $next)
    {
        if (!$envelope instanceof Envelope) {
            throw new \RuntimeException('Envelope was expected but actual '.get_class($envelope));
        }

        $envelope = $envelope->with((new UuidEnvelopeItem())->setUuid(Uuid::uuid4()));

        return $next($envelope);
    }
}
