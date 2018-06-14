<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Transport\Enhancers;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\ReceiverInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class StopWhenMessageCountIsExceededReceiver implements ReceiverInterface
{
    private $decoratedReceiver;
    private $maximumNumberOfMessages;
    private $logger;

    public function __construct(ReceiverInterface $decoratedReceiver, int $maximumNumberOfMessages, LoggerInterface $logger = null)
    {
        $this->decoratedReceiver = $decoratedReceiver;
        $this->maximumNumberOfMessages = $maximumNumberOfMessages;
        $this->logger = $logger;
    }

    public function receive(callable $handler): void
    {
        $receivedMessages = 0;

        $this->decoratedReceiver->receive(function (?Envelope $envelope) use ($handler, &$receivedMessages) {
            try {
                $handler($envelope);
            } finally {
                if (null !== $envelope && ++$receivedMessages >= $this->maximumNumberOfMessages) {
                    $this->stop();
                    if (null !== $this->logger) {
                        $this->logger->info('Receiver stopped due to maximum count of {count} exceeded', ['count' => $this->maximumNumberOfMessages]);
                    }
                }
            }
        });
    }

    public function stop(): void
    {
        $this->decoratedReceiver->stop();
    }
}
