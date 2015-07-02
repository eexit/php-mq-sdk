<?php
namespace Eexit\Mq\Adapter\Amqp;

use Eexit\Mq\EnvelopeInterface;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPAbstractCollection;

class Message implements EnvelopeInterface
{
    /** @var string */
    private $queue;

    /** @var string */
    private $receiptHandle;

    /** @var array */
    private $attributeBag = array();

    /** @var string */
    private $body = '';

    /**
     * @param string $queue
     */
    public function __construct($queue)
    {
        $this->queue = $queue;
    }

    /**
     * Assigned as the message_id message attribute
     *
     * {@inheritdoc}
     */
    public function setId($id)
    {
        $this->setAttribute('message_id', $id);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->getAttribute('message_id');
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
    public function getReceiptHandle()
    {
        return $this->receiptHandle;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute($name, $value)
    {
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
    public function setBody($body)
    {
        $this->body = $body;

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
    public function toArray()
    {
        return array(
            'queue' => $this->getQueue(),
            'body' => $this->getBody(),
            'delivery_tag' => $this->getReceiptHandle(),
            'attributes' => $this->getAttributes()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function toVendor()
    {
        $message = new AMQPMessage($this->getBody());

        foreach ($this->getAttributes() as $name => $value) {
            $message->set($name, $value);
        }

        return $message;
    }

    /**
     * {@inheritdoc}
     */
    public static function fromVendor($data)
    {
        if (!$data instanceof AMQPMessage) {
            throw new \InvalidArgumentException(sprintf(
                'AMQPMessage class instance excpected. Given type is: %s',
                gettype($data)
            ));
        }

        /** @var Message $self */
        $self = new static($data->get('routing_key'));
        $self
            ->setBody($data->body)
            ->setReceiptHandle($data->get('delivery_tag'));

        foreach ($data->get_properties() as $name => $value) {
            if ($value instanceof AMQPAbstractCollection) {
                $value = $value->getNativeData();
            }

            $self->setAttribute($name, $value);
        }

        return $self;
    }
}
