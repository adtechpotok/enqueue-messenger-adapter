<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception;

use InvalidArgumentException;
use Symfony\Component\Messenger\Envelope;

class MissedUuidEnvelopeItem extends InvalidArgumentException
{
    /**
     * @var Envelope|null
     */
    protected $envelope;

    /**
     * @param Envelope $envelope
     *
     * @return self
     */
    public function setEnvelope(Envelope $envelope): self
    {
        $this->envelope = $envelope;

        return $this;
    }

    /**
     * @return Envelope|null
     */
    public function getEnvelope(): ?Envelope
    {
        return $this->envelope;
    }
}
