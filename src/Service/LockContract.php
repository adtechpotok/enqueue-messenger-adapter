<?php

namespace Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Service;

use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception\MessageLocked;
use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception\MissedUuidEnvelopeItem;
use Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Exception\WritingKeyNotEqualWrittenKey;

interface LockContract
{
    /**
     * @param string      $uniqueId
     * @param string      $messageUuid
     * @param null|string $queueName
     *
     * @throws MissedUuidEnvelopeItem
     * @throws WritingKeyNotEqualWrittenKey
     * @throws MessageLocked
     *
     * @return bool
     */
    public function lock(string $uniqueId, string $messageUuid, ?string $queueName = null): bool;
}
