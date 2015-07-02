<?php
namespace Eexit\Mq\Adapter\Amqp;

use PhpAmqpLib\Channel\AMQPChannel;

class Channel extends AMQPChannel
{
    /**
     * {@inheritdoc}
     */
    public function ack($messageId)
    {
        $this->basic_ack($messageId);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function nack($messageId, $requeue = false)
    {
        $this->basic_nack($messageId, false, $requeue);

        return $this;
    }
}
