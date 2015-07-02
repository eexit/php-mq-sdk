<?php
namespace Eexit\Mq\MetricDirectory;

/**
 * Connection metrics
 */
// Connection open success count
const CONNECTION_OPEN_SUCCEED = 'connection.open.succeed';
// Connection open duration
const CONNECTION_OPEN_TIME = 'connection.open_time';
// Connection open failure count
const CONNECTION_OPEN_FAILED = 'connection.open.failed';
// Connection stop success count
const CONNECTION_STOP_SUCCEED = 'connection.stop.succeed';
// Connection stop duration
const CONNECTION_STOP_TIME = 'connection.stop_time';
// Connection stop failure count
const CONNECTION_STOP_FAILED = 'connection.stop.failed';
// Connection close success count
const CONNECTION_CLOSE_SUCCEED = 'connection.close.succeed';
// Connection close duration
const CONNECTION_CLOSE_TIME = 'connection.close_time';
// Connection close failure count
const CONNECTION_CLOSE_FAILED = 'connection.close.failed';

/**
 * Message metrics
 */
// Message publication success count
const MESSAGE_PUBLISH_SUCCEED = 'message.publish.succeed';
// Message publication duration
const MESSAGE_PUBLISH_TIME = 'message.publish_time';
// Message publication failure count
const MESSAGE_PUBLISH_FAILED = 'message.publish.failed';
// Message fetch success count
const MESSAGE_FETCH_SUCCEED = 'message.fetch.succeed';
// Message fetch duration
const MESSAGE_FETCH_TIME = 'message.fetch_time';
// Message listen failure count
const MESSAGE_LISTEN_FAILED = 'message.listen.failed';
// Message ack success count
const MESSAGE_ACK_SUCCEED = 'message.ack.succeed';
// Message ack duration
const MESSAGE_ACK_TIME = 'message.ack_time';
// Message ack failure count
const MESSAGE_ACK_FAILED = 'message.ack.failed';
// Message nack success count
const MESSAGE_NACK_SUCCEED = 'message.nack.succeed';
// Message nack duration
const MESSAGE_NACK_TIME = 'message.nack_time';
// Message nack failure count
const MESSAGE_NACK_FAILED = 'message.nack.failed';
// Message processing duration
const MESSAGE_PROCESS_TIME = 'message.process_time';
