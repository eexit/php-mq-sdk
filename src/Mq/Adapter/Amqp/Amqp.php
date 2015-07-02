<?php
namespace Eexit\Mq\Adapter\Amqp;

use Eexit\Mq\Adapter\AdapterInterface;
use Eexit\Mq\EnvelopeInterface;
use Eexit\Mq\Adapter\AdapterException;

class Amqp implements AdapterInterface
{
    /** Nack option key for requeue flag */
    const NACK_OPT_REQUEUE = 'doRequeue';

    /** Used for metric namespacing */
    const METRIC_PREFIX = 'mq.amqp.';

    /** @var Connection */
    private $connection;

    /** @var Channel */
    private $channel;

    /** @var string */
    private $consumerId;

    /** @var bool */
    private $consuming;

    /**
     * @param Connection $connection
     * @param string $consumerId
     */
    public function __construct(Connection $connection, $consumerId)
    {
        $this->connection = $connection;
        $this->consumerId = $consumerId;
    }

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        try {
            $this->connection->connect();
            $this->channel = $this->connection->channel();

            return $this;
        } catch (\Exception $e) {
            throw new AdapterException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        $this->consuming = false;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if ($this->channel) {
            $this->channel->close();
        }

        if ($this->connection) {
            $this->connection->close();
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function listen($queue, \Closure $onMessage, array $params = array())
    {
        try {
            $this->consuming = true;

            while ($this->consuming) {
                /** @var \PhpAmqpLib\Message\AMQPMessage $payload */
                $payload = $this->channel->basic_get($queue);
                $onMessage(Message::fromVendor($payload));
            }
        } catch (\Exception $e) {
            throw new AdapterException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function publish(EnvelopeInterface $message)
    {
        try {
            if (!$message->hasAttribute('delivery_mode')) {
                $message->setAttribute('delivery_mode', 2);
            }

            if (!$message->hasAttribute('message_id')) {
                $message->setAttribute('message_id', uniqid($this->consumerId . '_'));
            }

            if (!$message->hasAttribute('timestamp')) {
                $message->setAttribute('timestamp', time());
            }

            $this->channel->basic_publish($message->toVendor(), '', $message->getQueue());

            return $message;
        } catch (\Exception $e) {
            throw new AdapterException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function ack(EnvelopeInterface $message)
    {
        try {
            $this->channel->ack($message->getReceiptHandle());

            return $this;
        } catch (\Exception $e) {
            throw new AdapterException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function nack(EnvelopeInterface $message, array $params = array())
    {
        $requeue = false;

        if (array_key_exists(self::NACK_OPT_REQUEUE, $params)) {
            $requeue = (bool) $params[self::NACK_OPT_REQUEUE];
        }

        try {
            $this->channel->nack($message->getReceiptHandle(), $requeue);

            return $this;
        } catch (\Exception $e) {
            throw new AdapterException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
