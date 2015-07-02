<?php
namespace Eexit\Mq\Adapter\InMemory;

class MessageTest extends \PHPUnit_Framework_TestCase
{
    public function testBlankMessageToArray()
    {
        $message = new Message(null);
        $expected = array(
            'id' => null,
            'body' => '',
            'queue' => null,
            'receiptHandle' => null,
            'attributes' => array()
        );

        $this->assertEquals($expected, $message->toArray());
    }

    public function testFullMessageToArray()
    {
        $message = new Message('test_queue');
        $message
            ->setBody('test_body')
            ->setId('test_id')
            ->setAttribute('test_attr_name', 'test_attr_value')
            ->setAttribute('ghost', 'casper')
            ->removeAttribute('ghost')
            ->setReceiptHandle('test_receipt_handle');

        $expected = array(
            'id' => 'test_id',
            'body' => 'test_body',
            'queue' => 'test_queue',
            'receiptHandle' => 'test_receipt_handle',
            'attributes' => array(
                'test_attr_name' => 'test_attr_value'
            )
        );

        $this->assertEquals($expected, $message->toArray());
    }

    public function testConvertToVendor()
    {
        $message = new Message('test_queue');
        $message
            ->setBody('test_body')
            ->setId('test_id')
            ->setAttribute('test_attr_name', 'test_attr_value')
            ->setReceiptHandle('test_receipt_handle');

        $expected = array(
            'id' => 'test_id',
            'body' => 'test_body',
            'queue' => 'test_queue',
            'attributes' => array(
                'test_attr_name' => 'test_attr_value'
            )
        );

        $this->assertEquals($expected, $message->toVendor());
    }

    public function testCreateMessageFromVendor()
    {
        $payload = array(
            'id' => 'test_id',
            'body' => 'test_body',
            'queue' => 'test_queue',
            'receiptHandle' => 'test_receipt_handle',
            'attributes' => array(
                'test_attr_name' => 'test_attr_value'
            )
        );

        $message = Message::fromVendor($payload);
        $this->assertEquals($payload, $message->toArray());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Array type excpected from InMemory message. Given type is: object
     */
    public function testFailCreateMessageFromSomethingElseThanArray()
    {
        Message::fromVendor(new \stdClass());
    }

    /**
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Missing mandatory queue key!
     */
    public function testFailCreateMessageWhenQueueIsMissing()
    {
        Message::fromVendor(array());
    }
}
