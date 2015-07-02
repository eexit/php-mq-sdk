<?php
namespace Eexit\Mq\Adapter\Amqp;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class MessageTest extends \PHPUnit_Framework_TestCase
{
    public function testBlankMessageToArray()
    {
        $message = new Message(null);
        $expected = array(
            'queue' => null,
            'body' => '',
            'delivery_tag' => null,
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
            ->setAttribute('app_id', 'test_app_id')
            ->setAttribute('foo', 'bar')
            ->removeAttribute('foo')
            ->setReceiptHandle(9223372036854775807);

        $expected = array(
            'queue' => 'test_queue',
            'body' => 'test_body',
            'delivery_tag' => 9223372036854775807,
            'attributes' => array(
                'message_id' => 'test_id',
                'app_id' => 'test_app_id'
            )
        );

        $this->assertEquals($expected, $message->toArray());
    }

    public function testMessageIdIsAnAttribute()
    {
        $message = new Message(null);
        $message->setId('test_id');

        $this->assertTrue($message->hasAttribute('message_id'));
        $this->assertEquals('test_id', $message->getId());
        $this->assertEquals($message->getId(), $message->getAttribute('message_id'));

        // Clears attributes
        $message->clearAttributes();
        $this->assertFalse($message->hasAttribute('message_id'));
        $this->assertNull($message->getId());
    }

    public function testConvertToVendor()
    {
        $message = new Message('test_queue');
        $message
            ->setBody('test_body')
            ->setId('test_id')
            ->setAttribute('app_id', 'test_app_id')
            ->setReceiptHandle(9223372036854775807);

        /** @var AMQPMessage $vendorMessage */
        $vendorMessage = $message->toVendor();

        $this->assertInstanceOf('\PhpAmqpLib\Message\AMQPMessage', $vendorMessage);
        $this->assertEquals('test_body', $vendorMessage->body);

        $this->assertTrue($vendorMessage->has('message_id'));
        $this->assertEquals('test_id', $vendorMessage->get('message_id'));

        $this->assertTrue($vendorMessage->has('app_id'));
        $this->assertEquals('test_app_id', $vendorMessage->get('app_id'));

        $this->assertFalse($vendorMessage->has('delivery_tag'));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testFailsConvertToVendorWhenWrongAttributesNames()
    {
        $message = new Message('');
        $message->setAttribute('some_attribute', 'some_value');

        $message->toVendor();
    }

    public function testCreateFromVendor()
    {
        $vendorMessage = new AMQPMessage('test_body');

        // Simulates \PhpAmqpLib\Channel\AMQPChannel::basic_deliver()
        $vendorMessage->delivery_info['delivery_tag'] = 9223372036854775807;
        $vendorMessage->delivery_info['routing_key'] = 'test_queue';

        $message = Message::fromVendor($vendorMessage);

        $expected = array(
            'queue' => 'test_queue',
            'body' => 'test_body',
            'delivery_tag' => 9223372036854775807,
            'attributes' => array()
        );

        $this->assertEquals($expected, $message->toArray());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage AMQPMessage class instance excpected. Given type is: array
     */
    public function testFailCreateMessageFromSomethingElseThanVendorInstance()
    {
        Message::fromVendor(array());
    }

    public function testDecodeAmqpComplexProperties()
    {
        $complex = array(
            'x-foo' => array('S', 'bar'),
            'x-header' => array('I', 128),
            'x-array' => array('A', array('yux' => 'pux'))
        );

        $vendorMessage = new AMQPMessage('test_body');
        // Re-creates a complex properties when delivered by the broker
        $vendorMessage->set('application_headers', new AMQPTable($complex));

        // Simulates \PhpAmqpLib\Channel\AMQPChannel::basic_deliver()
        $vendorMessage->delivery_info['delivery_tag'] = 9223372036854775807;
        $vendorMessage->delivery_info['routing_key'] = 'test_queue';

        $message = Message::fromVendor($vendorMessage);
        $this->assertTrue($message->hasAttribute('application_headers'));
        $this->assertInternalType('array', $message->getAttribute('application_headers'));
        $this->assertEquals($complex, $message->getAttribute('application_headers'));
    }
}
