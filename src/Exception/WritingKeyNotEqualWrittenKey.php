<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception;

use Exception;

class WritingKeyNotEqualWrittenKey extends Exception
{
    /**
     * @var string
     */
    protected $writing = '';

    /**
     * @var string
     */
    protected $written = '';

    /**
     * @param string $writing
     * @param string $written
     *
     * @return $this
     */
    public function setLockKeys(string $writing, string $written)
    {
        $this->writing = $writing;
        $this->written = $written;

        return $this;
    }

    /**
     * @return string
     */
    public function getWrittenKey(): string
    {
        return $this->written;
    }

    /**
     * @return string
     */
    public function getWritingKey(): string
    {
        return $this->writing;
    }
}
