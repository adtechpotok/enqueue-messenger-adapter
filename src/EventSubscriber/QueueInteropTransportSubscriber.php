<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\EventSubscriber;

use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\EnvelopeItem\RoutingKeyItem;
use Enqueue\MessengerAdapter\Event\Events;
use Enqueue\MessengerAdapter\Event\OnSendMessageEvent;
use Interop\Amqp\AmqpMessage;
use Interop\Queue\PsrMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class QueueInteropTransportSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Events::ON_SEND_MESSAGE => [
                ['onSendMessage', 0],
            ],
        ];
    }

    /**
     * @param OnSendMessageEvent $event
     */
    public function onSendMessage(OnSendMessageEvent $event): void
    {
        $message = $event->getEnvelope();

        /** @var AmqpMessage|PsrMessage $psrMessage */
        $psrMessage = $event->getMessage();

        /** @var RoutingKeyItem $routingKeyItem */
        if ($psrMessage instanceof AmqpMessage && null !== $routingKeyItem = $message->get(RoutingKeyItem::class)) {
            $psrMessage->setRoutingKey($routingKeyItem->getRoutingKey());

            $event->setMessage($psrMessage);
        }
    }
}
