<?php
namespace Eexit\Mq\Adapter\InMemory;

use Eexit\Mq\EnvelopeInterface;

class Message implements EnvelopeInterface
{
    /** @var string */
    private $id;

    /** @var string */
    private $queue;

    /** @var string */
    private $receiptHandle;

    /** @var string */
    private $body = '';

    /** @var array */
    private $attributeBag = array();

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
    public function getId()
    {
        return $this->id;
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
    public function getQueue()
    {
        return $this->queue;
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
    public function getAttributes()
    {
        return $this->attributeBag;
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
    public function toArray()
    {
        return array(
            'id' => $this->getId(),
            'body' => $this->getBody(),
            'queue' => $this->getQueue(),
            'receiptHandle' => $this->getReceiptHandle(),
            'attributes' => $this->getAttributes()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function toVendor()
    {
        $keysToRemove = array('receiptHandle');

        return array_diff_key($this->toArray(), array_flip($keysToRemove));
    }

    /**
     * {@inheritdoc}
     */
    public static function fromVendor($data)
    {
        if (!is_array($data)) {
            throw new \InvalidArgumentException(sprintf(
                'Array type excpected from InMemory message. Given type is: %s',
                gettype($data)
            ));
        }

        if (!array_key_exists('queue', $data)) {
            throw new \InvalidArgumentException('Missing mandatory queue key!');
        }

        $data = array_merge(array(
            'id' => null,
            'body' => '',
            'receiptHandle' => null,
            'attributes' => array()
        ), $data);

        $self = new static($data['queue']);
        $self
            ->setId($data['id'])
            ->setBody($data['body'])
            ->setReceiptHandle($data['receiptHandle']);

        foreach ($data['attributes'] as $name => $value) {
            $self->setAttribute($name, $value);
        }

        return $self;
    }
}
