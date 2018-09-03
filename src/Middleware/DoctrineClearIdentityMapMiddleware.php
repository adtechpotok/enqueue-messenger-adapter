<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Middleware;

use Adtechpotok\Aware\Interfaces\DoctrineAwareInterface;
use Adtechpotok\Aware\Traits\DoctrineAwareTrait;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;

class DoctrineClearIdentityMapMiddleware implements MiddlewareInterface, DoctrineAwareInterface
{
    use DoctrineAwareTrait;

    /**
     * {@inheritdoc}
     */
    public function handle($message, callable $next)
    {
        $result = $next($message);

        foreach ($this->doctrine->getManagers() as $name => $manager) {
            $manager->clear();
        }

        return $result;
    }
}
