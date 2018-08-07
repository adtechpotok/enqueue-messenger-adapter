<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle;

use Symfony\Component\Messenger\EnvelopeItemInterface;

class UuidEnvelopeItem implements EnvelopeItemInterface
{
    protected $uuid;

    /**
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * @param string $uuid
     *
     * @return self
     */
    public function setUuid(string $uuid)
    {
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize($this->uuid);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        $this->uuid = unserialize($serialized, ['allowed_classes' => false]);
    }
}
