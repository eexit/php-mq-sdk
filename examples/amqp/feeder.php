<?php
use Eexit\Mq\MessageQueue;
use Eexit\Mq\Adapter\Amqp\Amqp;
use Eexit\Mq\Adapter\Amqp\Connection;
use Eexit\Mq\Adapter\Amqp\Message;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

require_once __DIR__ . '/../../vendor/autoload.php';

$queue = 'sandbox';

$logger = new Logger('Feeder');
$logger->pushHandler(new StreamHandler(__DIR__ . '/sandbox.log', Logger::INFO));


$adapter = new Amqp(
    new Connection('amqp://localhost'),
    'my_consumer_id' // Consumer ID
);

$mq = new MessageQueue($adapter);
$mq
    ->setLogger($logger)
    ->connect();

for (;;) {
    $message = new Message($queue);
    $message
        ->setBody('Hello world!')
        ->setAttribute('content_type', 'text/plain')
        ->setAttribute('delivery_mode', 2) // Will be set by default if ommitted
        ->setAttribute('timestamp', time()) // Will be set by default if omitted
        ->setAttribute('app_id', 'amqp_example')
        ->setAttribute('application_headers', array('x-foo' => array('S', 'bar')));

    $message = $mq->publish($message);
    usleep(50000); // 0.5 sleep because of local AMQP broker
}

$mq->close();
