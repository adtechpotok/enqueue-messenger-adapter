<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Middleware;

use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\EnvelopeItem\OpenTracingCarry;
use Enqueue\MessengerAdapter\EnvelopeItem\QueueName;
use OpenTracing\Formats;
use OpenTracing\Tracer;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EnvelopeAwareInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;

class OpenTracingConsumeMiddleware implements MiddlewareInterface, EnvelopeAwareInterface
{
    /**
     * @var Tracer
     */
    protected $tracer;

    /**
     * OpenTracingConsumeMiddleware constructor.
     *
     * @param Tracer $tracer
     */
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

        /** @var QueueName|null $queueName */
        $queueName = $envelope->get(QueueName::class);

        if ($queueName) {
            $queueName = ' '.$queueName->getQueueName();
        } else {
            $queueName = '';
        }

        /** @var OpenTracingCarry|null $carry */
        $carry = $envelope->get(OpenTracingCarry::class);

        $span = null;

        if ($carry) {
            $context = $this->tracer->extract(Formats\TEXT_MAP, $carry->getCarry());
            $spanConsume = $this->tracer->startActiveSpan('consume'.$queueName, [
                'child_of'             => $context,
                'finish_span_on_close' => true,
            ]);
        } else {
            $spanConsume = $this->tracer->startActiveSpan('consume'.$queueName, [
                'finish_span_on_close' => true,
            ]);
        }

        $spanHandle = $this->tracer->startActiveSpan('handle'.$queueName, [
            'child_of'             => $this->tracer->getActiveSpan(),
            'finish_span_on_close' => true,
        ]);

        try {
            return $next($envelope);
        } finally {
            $spanHandle->close();
            $spanConsume->close();

            $this->tracer->flush();
        }
    }
}
