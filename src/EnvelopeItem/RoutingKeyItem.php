<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\EnvelopeItem;

use Symfony\Component\Messenger\EnvelopeItemInterface;

class RoutingKeyItem implements EnvelopeItemInterface
{
    /** @var string */
    private $routingKey;

    /**
     * @param string $routingKey
     */
    public function __construct(string $routingKey)
    {
        $this->routingKey = $routingKey;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized): void
    {
    }

    /**
     * @return string
     */
    public function getRoutingKey(): string
    {
        return $this->routingKey;
    }
}
