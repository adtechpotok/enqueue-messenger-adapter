<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception;

class WritingKeyNotEqualWrittenKey extends \Error
{
    protected $writing;
    protected $written;

    public function setLockKeys($writing, $written)
    {
        $this->writing = $writing;
        $this->written = $written;

        return $this;
    }
}
