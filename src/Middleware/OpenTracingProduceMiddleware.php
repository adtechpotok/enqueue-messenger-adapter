<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Middleware;

use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\EnvelopeItem\OpenTracingCarry;
use OpenTracing\Formats;
use OpenTracing\Tracer;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EnvelopeAwareInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;

class OpenTracingProduceMiddleware implements MiddlewareInterface, EnvelopeAwareInterface
{
    /**
     * @var Tracer
     */
    protected $tracer;

    public function __construct(Tracer $tracer)
    {
        $this->tracer = $tracer;
    }

    /**
     * @param Envelope $envelope
     * @param callable $next
     *
     * @throws \RuntimeException
     *
     * @return mixed
     */
    public function handle($envelope, callable $next)
    {
        if (!$envelope instanceof Envelope) {
            throw new \RuntimeException('Envelope was expected but actual '.\get_class($envelope));
        }

        if ($this->tracer->getActiveSpan()) {
            $span = $this->tracer->startSpan('produce envelope', ['child_of' => $this->tracer->getActiveSpan()]);
        } else {
            $span = $this->tracer->startSpan('produce envelope');
        }

        $carry = [];

        $this->tracer->inject($span->getContext(), Formats\TEXT_MAP, $carry);

        try {
            return $next($envelope->with(new OpenTracingCarry($carry)));
        } finally {
            $span->finish();
        }
    }
}
