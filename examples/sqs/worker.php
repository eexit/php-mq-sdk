<?php
use Eexit\Mq\MessageQueue;
use Eexit\Mq\Adapter\Sqs\Sqs;
use Eexit\Mq\EnvelopeInterface;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

require_once __DIR__ . '/../../vendor/autoload.php';

$queue = '';

$logger = new Logger('Worker');
$logger->pushHandler(new StreamHandler(__DIR__ . '/sandbox.log', Logger::INFO));

$mq = new MessageQueue(new Sqs('region', 'key', 'secret'));
$mq
    ->setLogger($logger)
    ->connect();

$mq->listen($queue, function(EnvelopeInterface $message, MessageQueue $mq) {
    try {
        sleep(rand(1, 3));

        if (!rand(0, 10) % 4) {
            throw new \RuntimeException('Some error occurred');
        }

        $mq->ack($message);
    } catch (\Exception $e) {
        $mq->nack(
            $message,
            array(
                Sqs::NACK_OPT_TIMEOUT => 20 // Message will be held for 2 sec before being fetched again
            )
        );
    }
});

$mq->close();
