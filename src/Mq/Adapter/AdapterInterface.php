<?php
namespace Eexit\Mq\Adapter;

use Eexit\Mq\EnvelopeInterface;

interface AdapterInterface
{
    /**
     * Connects to message queue underlying transport
     *
     * @return AdapterInterface
     */
    public function connect();

    /**
     * Stops listening for incoming messages
     *
     * @return AdapterInterface
     */
    public function stop();

    /**
     * Closes the message queue underlying transport (when supported)
     *
     * @return AdapterInterface
     */
    public function close();

    /**
     * Listen to incoming messages from the given queue with given params
     * When a message is fetched, the callback will be called with the message
     * as argument
     *
     * @param string $queue
     * @param \Closure $onMessage
     * @param array $params
     * @return null
     */
    public function listen($queue, \Closure $onMessage, array $params = array());

    /**
     * Publishes a message and returns it with possible additional metadata
     *
     * @param EnvelopeInterface $message
     * @return EnvelopeInterface
     */
    public function publish(EnvelopeInterface $message);

    /**
     * Acknowledges a message (tells the message queue broker to remove it)
     *
     * @param EnvelopeInterface $message
     * @return AdapterInterface
     */
    public function ack(EnvelopeInterface $message);

    /**
     * Reject a message (tells the message broker to re-dispatch the message to other consumers)
     *
     * @param EnvelopeInterface $message
     * @param array $params Params such as requeue flag, availability timeout, etc.
     *      Use used adapter constants for keys, e.g. array(Sqs::NACK_OPT_TIMEOUT => 2)
     * @return \Eexit\Mq\Adapter\AdapterInterface
     */
    public function nack(EnvelopeInterface $message, array $params = array());
}
