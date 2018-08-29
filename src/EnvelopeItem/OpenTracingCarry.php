<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\EnvelopeItem;

use Symfony\Component\Messenger\EnvelopeItemInterface;

class OpenTracingCarry implements EnvelopeItemInterface
{
    /**
     * @var array
     */
    protected $carry = [];

    /**
     * OpenTracing constructor.
     *
     * @param array $carry
     */
    public function __construct(array $carry)
    {
        $this->setCarry($carry);
    }

    /**
     * @return array
     */
    public function getCarry(): array
    {
        return $this->carry;
    }

    /**
     * @param array $carry
     *
     * @return self
     */
    public function setCarry(array $carry): self
    {
        $this->carry = $carry;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(): string
    {
        return serialize($this->carry);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized): void
    {
        $this->carry = unserialize($serialized, ['allowed_classes' => false]);
    }
}
