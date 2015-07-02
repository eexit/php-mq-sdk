<?php
namespace Eexit\Mq;

interface EnvelopeInterface
{
    /**
     * @param string $id
     * @return EnvelopeInterface
     */
    public function setId($id);

    /**
     * @return string
     */
    public function getId();

    /**
     * @param string $receiptHandle
     * @return EnvelopeInterface
     */
    public function setReceiptHandle($receiptHandle);

    /**
     * @return string
     */
    public function getReceiptHandle();

    /**
     * @return string
     */
    public function getQueue();

    /**
     * @param string $name
     * @param mixed $value
     * @return EnvelopeInterface
     */
    public function setAttribute($name, $value);

    /**
     * @param string $name
     * @param null $default A value to be to returned as default it no value exists
     * @return mixed
     */
    public function getAttribute($name, $default = null);

    /**
     * @return \Traversable
     */
    public function getAttributes();

    /**
     * @param string $name
     * @return bool
     */
    public function hasAttribute($name);

    /**
     * @param string $name
     * @return EnvelopeInterface
     */
    public function removeAttribute($name);

    /**
     * @return EnvelopeInterface
     */
    public function clearAttributes();

    /**
     * @param mixed $body
     * @return EnvelopeInterface
     */
    public function setBody($body);

    /**
     * @return mixed
     */
    public function getBody();

    /**
     * @return array
     */
    public function toArray();

    /**
     * Converts the message to the adapter message format
     *
     * @return mixed
     */
    public function toVendor();

    /**
     * Creates a new message instance from the adapter message data
     *
     * @param mixed $data
     * @return EnvelopeInterface
     */
    public static function fromVendor($data);
}
