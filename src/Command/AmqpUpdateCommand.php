<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Command;

use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Transport\AmqpContextManager;
use Enqueue\MessengerAdapter\ContextManager;
use Enqueue\MessengerAdapter\QueueInteropTransport;
use Interop\Amqp\AmqpContext;
use Interop\Queue\PsrContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AmqpUpdateCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'amqp:update';

    /** @var ContextManager */
    private $contextManager;
    /** @var array[] */
    private $destinations;

    /**
     * {@inheritdoc}
     *
     * @param iterable|QueueInteropTransport[] $transports
     * @param PsrContext                       $psrContext
     *
     * @throws InvalidArgumentException
     */
    public function __construct(iterable $transports, PsrContext $psrContext)
    {
        if (!$psrContext instanceof AmqpContext) {
            throw new InvalidArgumentException(
                sprintf(
                    '$psrContext should be an instance of "%s" interface',
                    AmqpContext::class
                )
            );
        }

        $this->contextManager = new AmqpContextManager($psrContext);

        foreach ($transports as $transport) {
            $this->destinations[] = $transport->getDestination(null);
        }

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        foreach ($this->destinations as $destination) {
            $this->contextManager->ensureExists($destination);
        }
    }
}
