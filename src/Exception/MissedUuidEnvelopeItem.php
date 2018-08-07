<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception;

class MissedUuidEnvelopeItem extends \Error
{
    /**
     * @var \Symfony\Component\Messenger\Envelope|null
     */
    protected $envelope;

    /**
     * @param \Symfony\Component\Messenger\Envelope $envelope
     *
     * @return $this
     */
    public function setEnvelope($envelope)
    {
        $this->envelope = $envelope;

        return $this;
    }
}
