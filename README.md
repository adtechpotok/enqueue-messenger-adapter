# Enqueue's transport for Symfony Messenger component

This Symfony Messenger transport allows you to use Enqueue to send and receive your messages from all the supported brokers.

## Usage

1. Install the transport

```
composer req enqueue/messenger-adapter
```

2. Configure the Enqueue bundle as you would normaly do ([see Enqueue's Bundle documentation](https://github.com/php-enqueue/enqueue-dev/blob/master/docs/bundle/quick_tour.md)). If you are using the recipes, you should
   just have to configure the environment variables to configure the `default` Enqueue transport:

```bash
# .env
# ...

###> enqueue/enqueue-bundle ###
ENQUEUE_DSN=amqp://guest:guest@localhost:5672/%2f
###< enqueue/enqueue-bundle ###
```

3. Configure Messenger's transport (that we will name `amqp`) to use Enqueue's `default` transport:
```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            amqp: enqueue://default
```

4. Route the messages that have to go through the message queue:
```yaml
# config/packages/framework.yaml
framework:
    messenger:
        # ...

        routing:
            'App\Message\MyMessage': amqp
```

5. Consume!

```bash
bin/console messenger:consume-messages amqp
```

## Advanced usage

### Configure the queue(s) and exchange(s)

In the transport DSN, you can add extra configuration. Here is the reference DSN (note that the values are just for the example):

```
enqueue://default
	?queue[routingKey][name]=queue_name
	&topic[name]=topic_name
    &topic[type]=topic|fanout|direct
    &deliveryDelay=1800
    &delayStrategy=Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Transport\RabbitMq375DelayPluginDelayStrategy
    &timeToLive=3600
    &receiveTimeout=1000
    &priority=1
    &maximumPriority=255
    &durability=1
```

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            events: enqueue://default?queue[*][name]=events&topic[name]=events&topic[type]=topic
            foo.events: enqueue://default?queue[foo][name]=foo.events&topic[name]=events&topic[type]=topic
            bar.events: enqueue://default?queue[bar][name]=bar.events&topic[name]=events&topic[type]=topic

        routing:
            App\Message\EventsMessage: events
            Foo\Message\EventsMessage: foo.events
            Bar\Message\EventsMessage: bar.events
```

### Send a message on a specific topic

You can send a message on a specific topic using `TransportConfiguration` envelope item with your message:
```php
use Enqueue\MessengerAdapter\EnvelopeItem\TransportConfiguration;

// ...

$this->bus->dispatch((new Envelope($message))->with(new TransportConfiguration(
    ['topic' => 'specific-topic']
)));
```

## Message duplication preventing

This package provide deduplication mechanism based on Redis optimistic locking (there is documentation https://redis.io/topics/transactions "Optimistic locking using check-and-set").
It uses two dependencies which you have to implement by yourself.


1. Configure UuidItemSetterMiddleware on produce bus.
It will store UUID-4 identifier in envelope items.

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        buses:
            messenger.produce:
                middleware:
                    - Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Middleware\UuidItemSetterMiddleware
```

2. Implement UniqueIdGetterInterface interface and register it as a service.

That interface will be used every time when LockBasedDeduplicationMiddleware will process a message.
The method getUniqueId have to return a unique id each time when it will be called.
LockBasedDeduplicationMiddleware will try to lock a message by this id.
If it succeeds the message will be processed next otherwise you will get an exception.


Example:
```php
class IdGenerator implements UniqueIdGetterInterface
{
    /** @var string */
    protected $id;

    public function __construct()
    {
        $this->generateId();
    }

    /**
     * @return string
     */
    public function getUniqueId(): string
    {
        return $this->id;
    }

    public function generateId(): void
    {
        $this->id = uniqid('', true);
    }
}
```

```yaml
#config/services.yaml
services:
    SystemBundle\Classes\IdGenerator:
```

3. Register redis-locker and deduplication middleware

```yaml
system.middleware.service.locker:
    class: Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Service\RedisLockService
    arguments:
        - '@REDIS_CLIENT'
        - 'rabbit_mq_'
        - 172800
        - 'worker_id'

messenger.middleware.lock_based_deduplication:
    class: Adtechpotok\Bundle\EnqueueMessengerAdapterBundle\Middleware\LockBasedDeduplicationMiddleware
    arguments:
        - '@system.middleware.service.locker'
        - '@SystemBundle\Classes\IdGenerator'
```

Where @REDIS_CLIENT is your configured redis client.

4. Configure LockBasedDeduplicationMiddleware

```yaml
common:
    messenger:
        buses:
            messenger.consume:
                middleware:
                    - messenger.middleware.lock_based_deduplication
```