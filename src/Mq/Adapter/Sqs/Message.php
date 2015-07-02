<?php
namespace Eexit\Mq\Adapter\Sqs;

use Eexit\Mq\EnvelopeInterface;

class Message implements EnvelopeInterface
{
    const MAX_DELAY = 900;

    /** @var string */
    private $id;

    /** @var string */
    private $queue;

    /** @var string */
    private $receiptHandle;

    /** @var array */
    private $attributeBag = array();

    /** @var string */
    private $body = '';

    /** @var int */
    private $delay = 0;

    /**
     * These are technical attributes returned by SQS.
     * They may exist but must not be published as they will be
     * overridden by the broken upon reception.
     *
     * There is a safeguard in the Message::toVendor() method.
     *
     * @var array
     */
    private $forbiddenAttributeNames = array(
        'SenderId',
        'SentTimestamp',
        'ApproximateReceiveCount',
        'ApproximateFirstReceiveTimestamp',
        'MD5OfBody',
        'MD5OfMessageAttributes'
    );

    /**
     * @param string $queue
     */
    public function __construct($queue)
    {
        $this->queue = $queue;
    }

    /**
     * {@inheritdoc}
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getReceiptHandle()
    {
        return $this->receiptHandle;
    }

    /**
     * {@inheritdoc}
     */
    public function setReceiptHandle($receiptHandle)
    {
        $this->receiptHandle = $receiptHandle;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute($name, $value)
    {
        if ($name === 'DelaySeconds') {
            return $this->setDelay($value);
        }

        if (!is_scalar($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported type "%s" for attribute "%s". Only scalar types are supported',
                gettype($value),
                $name
            ));
        }

        $this->attributeBag[$name] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute($name, $default = null)
    {
        if ($this->hasAttribute($name)) {
            return $this->attributeBag[$name];
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes()
    {
        return $this->attributeBag;
    }

    /**
     * {@inheritdoc}
     */
    public function hasAttribute($name)
    {
        return array_key_exists($name, $this->attributeBag);
    }

    /**
     * {@inheritdoc}
     */
    public function removeAttribute($name)
    {
        if ($this->hasAttribute($name)) {
            unset($this->attributeBag[$name]);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function clearAttributes()
    {
        $this->attributeBag = array();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * {@inheritdoc}
     */
    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * Sets the SQS message delay
     *
     * @param int $delay
     * @return EnvelopeInterface
     */
    private function setDelay($delay)
    {
        if ($delay < 0) {
            $delay = 0;
        }

        if ($delay > self::MAX_DELAY) {
            $delay = self::MAX_DELAY;
        }

        $this->delay = $delay;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return array(
            'QueueUrl' => $this->getQueue(),
            'MessageId' => $this->getId(),
            'MessageBody' => $this->getBody(),
            'DelaySeconds' => $this->delay,
            'ReceiptHandle' => $this->getReceiptHandle(),
            'MessageAttributes' => $this->getAttributes()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function toVendor()
    {
        $keysToRemove = array('MessageId', 'ReceiptHandle');
        $payload = array_diff_key($this->toArray(), array_flip($keysToRemove));

        array_walk($payload['MessageAttributes'], function (&$value, $name) {
            if (in_array($name, $this->forbiddenAttributeNames)) {
                throw new \DomainException(sprintf(
                    'Attribute name "%s" is used internally. Please rename or remove the attribute before publishing.',
                    $name
                ));
            }

            $value = $this->buildAttributeValueBag($value);
        });

        return $payload;
    }

    /**
     * {@inheritdoc}
     */
    public static function fromVendor($data)
    {
        if (!is_array($data)) {
            throw new \InvalidArgumentException(sprintf(
                'Array type excpected from SQS message. Given type is: %s',
                gettype($data)
            ));
        }

        if (!array_key_exists('QueueUrl', $data)) {
            throw new \InvalidArgumentException('Missing mandatory QueueUrl key!');
        }

        $data = array_merge(array(
            'MessageId' => null,
            'Body' => '',
            'ReceiptHandle' => null,
            'MessageAttributes' => array(),
            'Attributes' => array(),
            'MD5OfBody' => null,
            'MD5OfMessageAttributes' => null,
        ), $data);

        /** @var Message $self */
        $self = new static($data['QueueUrl']);
        $self
            ->setId($data['MessageId'])
            ->setBody($data['Body'])
            ->setReceiptHandle($data['ReceiptHandle'])
            ->flattenSqsAttributes($data['MessageAttributes']);

        foreach ($data['Attributes'] as $name => $value) {
            $self->setAttribute($name, $value);
        }

        if ($data['MD5OfBody']) {
            $self->setAttribute('MD5OfBody', $data['MD5OfBody']);
        }

        if ($data['MD5OfMessageAttributes']) {
            $self->setAttribute('MD5OfMessageAttributes', $data['MD5OfMessageAttributes']);
        }

        return $self;
    }

    /**
     * Builds the appropriate value bag to comply with SQS
     *
     * @link http://docs.aws.amazon.com/aws-sdk-php/v2/api/class-Aws.Sqs.SqsClient.html#_sendMessage
     *
     * @param mixed $value
     * @return array
     */
    private function buildAttributeValueBag($value)
    {
        switch (gettype($value)) {
            case 'boolean':
                return array(
                    'DataType' => 'Number.Boolean',
                    'StringValue' => $value ? '1' : '0'
                );
            case 'integer':
                return array(
                    'DataType' => 'Number.Integer',
                    'StringValue' => strval($value)
                );
            case 'double':
            case 'float':
                return array(
                    'DataType' => 'Number.Float',
                    'StringValue' => strval($value)
            );
            default:
                return array(
                    'DataType' => 'String',
                    'StringValue' => $value
                );
        }
    }

    /**
     * @param array $messageAttributes
     * @return $this
     */
    private function flattenSqsAttributes(array $messageAttributes)
    {
        foreach ($messageAttributes as $name => $valueBag) {
            switch ($valueBag['DataType']) {
                case 'Number.Boolean':
                    $value = (bool) $valueBag['StringValue'];
                    break;
                case 'Number.Integer':
                    $value = intval($valueBag['StringValue']);
                    break;
                case 'Number.Float':
                    $value = floatval($valueBag['StringValue']);
                    break;
                default:
                    $value = $valueBag['StringValue'];
            }

            $this->setAttribute($name, $value);
        }

        return $this;
    }
}
