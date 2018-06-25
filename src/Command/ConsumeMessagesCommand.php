<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Command;

use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Transport\Enhancers\StopWhenMessageCountIsExceededReceiver;
use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Transport\Enhancers\StopWhenTimeLimitIsReachedReceiver;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand as BaseConsumeMessagesCommand;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Enhancers\StopWhenMemoryUsageIsExceededReceiver;
use Symfony\Component\Messenger\Worker;

class ConsumeMessagesCommand extends BaseConsumeMessagesCommand
{
    protected static $defaultName = 'messenger:consume-messages';

    private $bus;
    private $receiverLocator;
    private $logger;

    public function __construct(MessageBusInterface $bus, ContainerInterface $receiverLocator, LoggerInterface $logger = null, array $receiverNames = [])
    {
        parent::__construct($bus, $receiverLocator, $logger, $receiverNames);

        $this->bus = $bus;
        $this->receiverLocator = $receiverLocator;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        if (!$this->receiverLocator->has($receiverName = $input->getArgument('receiver'))) {
            throw new RuntimeException(sprintf('Receiver "%s" does not exist.', $receiverName));
        }

        $receiver = $this->receiverLocator->get($receiverName);

        if ($limit = $input->getOption('limit')) {
            $receiver = new StopWhenMessageCountIsExceededReceiver($receiver, $limit, $this->logger);
        }

        if ($memoryLimit = $input->getOption('memory-limit')) {
            $receiver = new StopWhenMemoryUsageIsExceededReceiver($receiver, $this->convertToBytes($memoryLimit), $this->logger);
        }

        if ($timeLimit = $input->getOption('time-limit')) {
            $receiver = new StopWhenTimeLimitIsReachedReceiver($receiver, $timeLimit, $this->logger);
        }

        $worker = new Worker($receiver, $this->bus);
        $worker->run();
    }

    private function convertToBytes(string $memoryLimit): int
    {
        $memoryLimit = strtolower($memoryLimit);
        $max = strtolower(ltrim($memoryLimit, '+'));
        if (0 === strpos($max, '0x')) {
            $max = \intval($max, 16);
        } elseif (0 === strpos($max, '0')) {
            $max = \intval($max, 8);
        } else {
            $max = (int) $max;
        }

        switch (substr($memoryLimit, -1)) {
            case 't':
                $max *= 1024;
            // no break
            case 'g':
                $max *= 1024;
            // no break
            case 'm':
                $max *= 1024;
            // no break
            case 'k':
                $max *= 1024;
        }

        return $max;
    }
}
