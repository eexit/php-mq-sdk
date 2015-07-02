<?php
namespace Eexit\Mq\Adapter\InMemory;

use Eexit\Mq\Adapter\AdapterException;
use Eexit\Mq\Adapter\AdapterInterface;
use Eexit\Mq\EnvelopeInterface;

class InMemory implements AdapterInterface, \Countable
{
    /** Used for metric namespacing */
    const METRIC_PREFIX = 'mq.in_memory.';

    /** @var MessageIterator */
    private $readyStack;

    /** @var MessageIterator */
    private $delivered;

    /** @var bool */
    private $connected = false;

    /** @var bool */
    private $consuming = false;

    /**
     * @param \Traversable $messages
     */
    public function __construct(\Traversable $messages = null)
    {
        $this->readyStack = new MessageIterator();
        $this->delivered = new MessageIterator();

        // Fills the broker with some available messages
        if (!empty($messages)) {
            $this->setConnected(true);
            foreach ($messages as $message) {
                $this->publish($message);
            }
            $this->setConnected(false);
        }
    }

    /**
     * @param int $mode
     * @return int
     */
    public function count($mode = COUNT_NORMAL)
    {
        return count($this->readyStack, $mode);
    }

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        $this->setConnected(true);

        return $this;
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
        $this->setConnected(false);

        return $this;
    }

    /**
     * @param boolean $connected
     * @return InMemory
     */
    public function setConnected($connected)
    {
        $this->connected = (bool) $connected;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isConnected()
    {
        return $this->connected;
    }

    /**
     * @return boolean
     */
    public function isConsuming()
    {
        return $this->consuming;
    }

    /**
     * @param string $queue
     * @param \Closure $onMessage
     * @param array $params Can contains:
     *  - [ timeout => int ] In seconds, the max number of sec of listen time
     *  - [ filter => \Closure ] A closure that must return a boolean (like array_filter)
     * @return null
     * @throws AdapterException
     */
    public function listen($queue, \Closure $onMessage, array $params = array())
    {
        if (!$queue) {
            throw new AdapterException('No message queue name provided');
        }

        $this->consuming = true;
        $time = time();

        while ($this->consuming) {
            if (isset($params['timeout'])) {
                if ((time() - $time) >= abs($params['timeout'])) {
                    break;
                }
            }

            if (!count($this->readyStack)) {
                continue;
            }

            $this->readyStack->rewind();

            while ($this->readyStack->valid() && $this->consuming) {

                if (!$this->connected) {
                    throw new AdapterException('Connection outage');
                }

                $message = Message::fromVendor($this->readyStack->current());

                if (isset($params['filter'])) {
                    $filter = $params['filter'];
                    if (!$filter($message)) {
                        $this->readyStack->next();
                        continue;
                    }
                }

                // Receipt handle = message id
                $message->setReceiptHandle($message->getId());
                $this->delivered->offsetSet($message->getReceiptHandle(), $message->toVendor());
                $this->readyStack->next();

                $onMessage($message);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function publish(EnvelopeInterface $message)
    {
        $message->setId($this->generateUuid());
        $this->readyStack->offsetSet($message->getId(), $message->toVendor());

        return $message;
    }

    /**
     * {@inheritdoc}
     */
    public function ack(EnvelopeInterface $message)
    {
        if ($this->readyStack->offsetExists($message->getReceiptHandle())) {
            $this->readyStack->offsetUnset($message->getReceiptHandle());
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function nack(EnvelopeInterface $message, array $params = array())
    {
        if ($this->delivered->offsetExists($message->getReceiptHandle())) {
            $internalMessage = $this->delivered->offsetGet($message->getReceiptHandle());
            $this->readyStack->offsetSet($message->getReceiptHandle(), $internalMessage);
            $this->delivered->offsetUnset($message->getReceiptHandle());
        }

        return $this;
    }

    /**
     * @return string
     */
    private function generateUuid()
    {
        return uniqid();
    }
}
