<?php
namespace Eexit\Mq\Adapter\Amqp;

use PhpAmqpLib\Connection\AMQPLazyConnection;

class Connection extends AMQPLazyConnection
{
    /**
     * @param string $url
     * @param int $connectionTimeout
     * @param bool $keepAlive Enables or disables keepalive
     * @param int|null $heartbeat Heartbeat interval in sec (0 = disabled, null = server negociation)
     * @throws \InvalidArgumentException
     */
    public function __construct($url, $connectionTimeout = 3, $keepAlive = false, $heartbeat = 0)
    {
        $connectionTimeout = (int) $connectionTimeout;
        $keepAlive = (bool) $keepAlive;

        $config = array_merge(
            array(
                'host' => '127.0.0.1',
                'port' => 5672,
                'user' => 'guest',
                'pass' => 'guest',
                'path' => '/'
            ),
            parse_url($url)
        );

        if (strtolower($config['scheme']) !== 'amqp') {
            throw new \InvalidArgumentException(sprintf(
                'Invalid AMQP URL: %s. Must be formatted as following: amqp://[user:password@]host[:port][/vhost]',
                $url
            ));
        }

        return parent::__construct(
            $config['host'],
            $config['port'],
            $config['user'],
            $config['pass'],
            $config['path'],
            false,
            'AMQPLAIN',
            null,
            'en_US',
            $connectionTimeout,
            ($connectionTimeout * 2), // See https://github.com/php-amqplib/php-amqplib/pull/191
            null,
            $keepAlive,
            $heartbeat
        );
    }

    /**
     * Opens the connection when needed (allows lazy connecting)
     *
     * @return Connection
     */
    public function connect()
    {
        parent::connect();

        return $this;
    }

    /**
     * Fetch a Channel object identified by the $channelId, or
     * create that object if it doesn't already exist
     *
     * @param int $channelId
     * @return Channel
     */
    public function channel($channelId = null)
    {
        if (array_key_exists($channelId, $this->channels)) {

            return $this->channels[$channelId];
        }

        $channelId = $this->get_free_channel_id();

        $channel = new Channel($this->connection, $channelId);
        $this->channels[$channelId] = $channel;

        return $channel;
    }
}
