# PHP Message Queue SDK [![Build Status](https://travis-ci.org/eexit/php-mq-sdk.svg?branch=master)](https://travis-ci.org/eexit/php-mq-sdk)

This PHP SDK aims for easy integration of message queues in various developments such as microservices.
The use of this SDK allows only the publishing and fetching for messages, you cannot create, delete, purge queue or any other action.

### Available adapters

 - [Amazon SQS](http://aws.amazon.com/sqs/) v.2
 - [AMQP](https://github.com/php-amqplib/php-amqplib) v.0.9.1

#### Adapter constraints

##### SQS

 - Batch sending, receiving and deletion not supported
 - Message attribute binary type not supported

##### AMQP

 - Publishing to exchange not supported

See the [CHANGE LOG](CHANGELOG.md) for version release information.

## Installation

Then run the command:

    $ composer require eexit/php-mq-sdk:~1.0

## Usage

See the `examples` directory content.

### Logging

Example with a [PSR-3](http://www.php-fig.org/psr/psr-3/) logger such as [Monolog](https://github.com/Seldaek/monolog):

```php
<?php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$handler = new StreamHandler(__DIR__ . '/sandbox.log', Logger::INFO);
$logger = new Logger('Sandbox');
$logger->pushHandler($handler);

/** \Eexit\Mq\MessageQueue $mq */
$mq->setLogger($logger);
```

Example of log with the `INFO` level:

```
[2015-07-08 13:33:57] Sandbox.INFO: Open a connection [] []
[2015-07-08 13:33:59] Sandbox.INFO: Published message abb12d0a-97c3-4dcd-a45f-8be097bbe6bf in 1.6550381183624 ms [] []
[2015-07-08 13:33:59] Sandbox.INFO: Start listening to on incoming messages [] []
[2015-07-08 13:33:59] Sandbox.INFO: Fetched message 5c13c13e-86e5-4100-8e50-5168a0bd9608 in 0.15714406967163 ms [] []
[2015-07-08 13:33:59] Sandbox.INFO: Acked message 5c13c13e-86e5-4100-8e50-5168a0bd9608 in 0.13068604469299 ms [] []
[2015-07-08 13:33:59] Sandbox.INFO: Close the connection [] []
```

If you use the `DEBUG` level, you'll get way more information such as the message content and error stack traces.

### Unix signal handling

The SDK supports Unix signal handling (via [PCNTL extension](http://php.net/manual/en/book.pcntl.php)) in order to gracefully shutdown your processes:

```php
<?php
// MQ signal handler:
$signalHandler = function ($signal) {
    switch ($signal) {
        case SIGINT:
        case SIGQUIT:
        case SIGTERM:
            $this->mq->stop();
            \pcntl_signal($signal, SIG_DFL); // Restores original signal handler
            break;
    }
};

// If the extension is loaded, registers the signal handlers
if (extension_loaded('pcntl')) {
    \pcntl_signal(SIGINT, $signalHandler);
    \pcntl_signal(SIGQUIT, $signalHandler);
    \pcntl_signal(SIGTERM, $signalHandler);
}

/*
  MQ bootstrap...
*/

$mq->listen($queue, function(EnvelopeInterface $message, MessageQueue $mq) {
    // The process can be stop from inside
    return $mq->stop();

    throw new WillNeverBeThrown();
});

// Closes the connections/gathers log & metrics accordingly!
$mq->close();
```

There is [a working example](examples/amqp/worker.php) of signal handling for AMQP.

### Metric collection

This library use the [Collector interface](https://github.com/beberlei/metrics/blob/master/src/Beberlei/Metrics/Collector/Collector.php) of [beberlei/metrics](https://github.com/beberlei/metrics) library. This allows you to use any of the supported metric backends.

Here's an example with StatsD:

```php
<?php
use Eexit\Mq\Adapter\Sqs\Sqs;
use Beberlei\Metrics\Collector\StatsD;

$collector = new StatsD(/* backend host */);

// Adds the collector and a prefix to avoid metric naming conflicts
// You can use the adapter prefix if you want
/** \Eexit\Mq\MessageQueue $mq */
$mq->setMetricCollector($collector, Sqs::METRIC_PREFIX);

// In your worker business code you can add other metrics
// Note: the metric prefix is only used internally. You may use you own prefix here
$mq->getMetricCollector()->increment('my_app.my_metric.succeed');
```

#### Internal metrics

| **Description**                   | **Metric name**                     |
|--------------------------------   |----------------------------------   |
| Connection open success count     | `{prefix}.connection.open.succeed`  |
| Connection open duration          | `{prefix}.connection.open_time`     |
| Connection open failure count     | `{prefix}.connection.open.failed`   |
| Connection stop success count     | `{prefix}.connection.stop.succeed`  |
| Connection stop duration          | `{prefix}.connection.stop_time`     |
| Connection stop failure count     | `{prefix}.connection.stop.failed`   |
| Connection close success count    | `{prefix}.connection.close.succeed` |
| Connection close duration         | `{prefix}.connection.close_time`    |
| Connection close failure count    | `{prefix}.connection.close.failed`  |
| Message publication success count | `{prefix}.message.publish.succeed`  |
| Message publication duration      | `{prefix}.message.publish_time`     |
| Message publication failure count | `{prefix}.message.publish.failed`   |
| Message fetch success count       | `{prefix}.message.fetch.succeed`    |
| Message fetch duration            | `{prefix}.message.fetch_time`       |
| Message listen failure count      | `{prefix}.message.listen.failed`    |
| Message ack success count         | `{prefix}.message.ack.succeed`      |
| Message ack duration              | `{prefix}.message.ack_time`         |
| Message ack failure count         | `{prefix}.message.ack.failed`       |
| Message nack success count        | `{prefix}.message.nack.succeed`     |
| Message nack duration             | `{prefix}.message.nack_time`        |
| Message nack failure count        | `{prefix}.message.nack.failed`      |
| Message processing duration       | `{prefix}.message.process_time`     |

For example, if you use the SQS adapter and use the `Sqs::METRIC_PREFIX` prefix, your metrics will look like this:

    mq.sqs.connection.open_time
    mq.sqs.message.publish.succeed
    mq.sqs.message.publish_time

