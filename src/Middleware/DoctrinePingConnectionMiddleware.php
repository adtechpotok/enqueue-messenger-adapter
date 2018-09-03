<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Middleware;

use Adtechpotok\Aware\Interfaces\DoctrineAwareInterface;
use Adtechpotok\Aware\Traits\DoctrineAwareTrait;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;

class DoctrinePingConnectionMiddleware implements MiddlewareInterface, DoctrineAwareInterface
{
    use DoctrineAwareTrait;

    /**
     * {@inheritdoc}
     *
     * @throws ORMException
     */
    public function handle($message, callable $next)
    {
        /** @var EntityManagerInterface $em */
        foreach ($this->doctrine->getManagers() as $name => $em) {
            if (!$em->isOpen()) {
                throw new ORMException(sprintf('EntityManager `%s` is closed', $name));
            }
        }

        /** @var Connection $connection */
        foreach ($this->doctrine->getConnections() as $connection) {
            if ($connection->getTransactionNestingLevel() > 0) {
                continue;
            }

            if (!$connection->isConnected()) {
                continue;
            }

            if (!$connection->ping()) {
                $connection->close();
                $connection->connect();
            }
        }

        return $next($message);
    }
}
