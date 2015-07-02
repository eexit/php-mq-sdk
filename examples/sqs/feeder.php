<?php
use Eexit\Mq\MessageQueue;
use Eexit\Mq\Adapter\Sqs\Sqs;
use Eexit\Mq\Adapter\Sqs\Message;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

require_once __DIR__ . '/../../vendor/autoload.php';

$queue = '';

$logger = new Logger('Feeder');
$logger->pushHandler(new StreamHandler(__DIR__ . '/sandbox.log', Logger::INFO));

$mq = new MessageQueue(new Sqs('region', 'key', 'secret'));
$mq
    ->setLogger($logger)
    ->connect();

for (;;) {
    $message = new Message($queue);
    $message
        ->setBody('Hello world!')
        ->setAttribute('Foo', 'Bar')
        ->setAttribute('UID', uniqid())
        ->setAttribute('DelaySeconds', rand(0, 10))
        ->setAttribute('Boolean', true);

    $message = $mq->publish($message);
}

$mq->close();
