<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception;

class MessageLocked extends \Error
{
    protected $key;

    public function setRedisKey($key)
    {
        $this->key = $key;

        return $this;
    }
}
