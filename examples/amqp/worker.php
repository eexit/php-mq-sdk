<?php
use Eexit\Mq\MessageQueue;
use Eexit\Mq\Adapter\Amqp\Amqp;
use Eexit\Mq\Adapter\Amqp\Connection;
use Eexit\Mq\EnvelopeInterface;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Example of a worker class
 */
class MySignalAwareWorker
{
    /** @var MessageQueue */
    private $mq;

    /** @var Logger */
    private $appLogger;

    /**
     * @param \Eexit\Mq\MessageQueue $mq
     * @param \Monolog\Logger $appLogger
     */
    public function __construct(MessageQueue $mq, Logger $appLogger)
    {
        $this->mq = $mq;
        $this->appLogger = $appLogger;
        $this->registerSignalHandling();
    }

    /**
     * @param int $signal
     */
    public function handleSignal($signal)
    {
        switch ($signal) {
            case SIGINT:
            case SIGQUIT:
            case SIGTERM:
                $this->mq->stop();
                \pcntl_signal($signal, SIG_DFL); // Restores original signal handler
                break;
        }
    }

    /**
     * @param string $queue
     */
    public function listen($queue)
    {
        // Here we use the class method and not a closure:
        $this->mq->listen($queue, array($this, 'process'));
    }

    public function tearDown()
    {
        $this->mq->close();
        $this->appLogger->info('Worker terminated');
    }

    /**
     * @param \Eexit\Mq\EnvelopeInterface $message
     */
    public function process(EnvelopeInterface $message)
    {
        try {
            sleep(rand(1, 3));

            if (!rand(0, 10) % 4) {
                throw new \RuntimeException('Some error occurred');
            }

            $this->mq->ack($message);
            $this->appLogger->info('Message process succeed');
        } catch (\Exception $e) {
            $this->mq->nack(
                $message,
                array(
                    Amqp::NACK_OPT_REQUEUE => true // Requeue the message
                )
            );
            $this->appLogger->error(sprintf(
                'Message process error: %s',
                $e->getMessage()
            ));
        }
    }

    private function registerSignalHandling()
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        \pcntl_signal(SIGTERM, [ $this, 'handleSignal' ]);
        \pcntl_signal(SIGINT, [ $this, 'handleSignal' ]);
        \pcntl_signal(SIGQUIT, [ $this, 'handleSignal' ]);
    }
}

/**
 * Worker implementation
 */
$logFile = __DIR__ . '/sandbox.log';

$mqLogger = new Logger('Worker');
$mqLogger->pushHandler(new StreamHandler($logFile));

$appLogger = new Logger('AppWorker');
$appLogger->pushHandler(new StreamHandler($logFile));

$adapter = new Amqp(new Connection('amqp://localhost'), 'mq.amqp.example');
$mq = new MessageQueue($adapter);
$mq->setLogger($mqLogger)->connect();

$worker = new MySignalAwareWorker($mq, $appLogger);
$worker->listen('sandbox');
$worker->tearDown();
