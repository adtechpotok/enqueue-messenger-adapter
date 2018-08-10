<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle;

use Symfony\Component\Messenger\EnvelopeItemInterface;

class UuidEnvelopeItem implements EnvelopeItemInterface
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * UuidEnvelopeItem constructor.
     *
     * @param string $uuid
     */
    public function __construct(string $uuid = '')
    {
        $this->setUuid($uuid);
    }

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
    public function setUuid(string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(): string
    {
        return serialize($this->uuid);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized): void
    {
        $this->uuid = unserialize($serialized, ['allowed_classes' => false]);
    }
}
