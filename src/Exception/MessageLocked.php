<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception;

use Exception;

class MessageLocked extends Exception
{
    /**
     * @var string
     */
    protected $key = '';

    /**
     * @param string $key
     *
     * @return self
     */
    public function setRedisKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }
}
